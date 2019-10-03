<?php

use Orbit\Helper\Elasticsearch\Search;
use Orbit\Helper\Util\FollowStatusChecker;

/**
* Implementation of ES search for campaign...
*/
abstract class CampaignSearch extends ObjectTypeSearch
{
    /**
     * Make sure the promotion is active.
     *
     * @return [type] [description]
     */
    public function isActive($params = [])
    {
        $this->must([ 'match' => ['status' => 'active'] ]);
        $this->must([ 'range' => ['begin_date' => ['lte' => $params['dateTimeEs']]] ]);
        $this->must([ 'range' => ['end_date' => ['gte' => $params['dateTimeEs']]] ]);
    }

    /**
     * Filter by Mall...
     *
     * @param  string $mallId [description]
     * @return [type]         [description]
     */
    public function filterByMall($mallId = '')
    {
        $this->must([
            'nested' => [
                'path' => 'link_to_tenant',
                'query' => [
                    'match' => [
                        'link_to_tenant.parent_id' => $mallId
                    ]
                ],
                // 'inner_hits' => new \stdClass(),
            ]
        ]);
    }

    /**
     * Filter by selected stores Categories..
     *
     * @return [type] [description]
     */
    public function filterByCategories($categories = [])
    {
        $arrCategories = [];

        foreach($categories as $category) {
            $arrCategories[] = ['match' => ['category_ids' => $category]];
        }

        $this->must([
            'bool' => [
                'should' => $arrCategories
            ]
        ]);
    }

    protected function setPriorityForQueryStr($objType, $keyword)
    {
        $priorityName = isset($this->esConfig['priority'][$objType]['name']) ?
            $this->esConfig['priority'][$objType]['name'] : '^6';

        $priorityObjectType = isset($this->esConfig['priority'][$objType]['object_type']) ?
            $this->esConfig['priority'][$objType]['object_type'] : '^5';

        $priorityDescription = isset($this->esConfig['priority'][$objType]['description']) ?
            $this->esConfig['priority'][$objType]['description'] : '^4';

        $priorityKeywords = isset($this->esConfig['priority'][$objType]['keywords']) ?
            $this->esConfig['priority'][$objType]['keywords'] : '^4';

        $priorityProductTags = isset($this->esConfig['priority'][$objType]['product_tags']) ?
            $this->esConfig['priority'][$objType]['product_tags'] : '^3';

        $this->must([
            'bool' => [
                'should' => [
                    [
                        'query_string' => [
                            'query' => '*' . $keyword . '*',
                            'fields' => [
                                'name' . $priorityName,
                                'object_type' . $priorityObjectType,
                                'description' . $priorityDescription,
                                'keywords' . $priorityKeywords,
                                'product_tags' . $priorityProductTags,
                            ]
                        ]
                    ],
                    [
                        'nested' => [
                            'path' => 'translation',
                            'query' => [
                                'match' => [
                                    'translation.description' . $priorityDescription => $keyword
                                ]
                            ]
                        ]
                    ],
                ]
            ]
        ]);
    }

    protected function setPriorityForLinkToTenant($objType, $keyword)
    {
        $priorityCountry = isset($this->esConfig['priority'][$objType]['country']) ?
            $this->esConfig['priority'][$objType]['country'] : '^2';

        $priorityProvince = isset($this->esConfig['priority'][$objType]['province']) ?
            $this->esConfig['priority'][$objType]['province'] : '^2';

        $priorityCity = isset($this->esConfig['priority'][$objType]['city']) ?
            $this->esConfig['priority'][$objType]['city'] : '^2';

        $priorityMallName = isset($this->esConfig['priority'][$objType]['mall_name']) ?
            $this->esConfig['priority'][$objType]['mall_name'] : '^3';

        $this->should([
            'nested' => [
                'path' => 'link_to_tenant',
                'query' => [
                    'query_string' => [
                        'query' => '*' . $keyword . '*',
                        'fields' => [
                            'link_to_tenant.country' . $priorityCountry,
                            'link_to_tenant.province' . $priorityProvince,
                            'link_to_tenant.city' . $priorityCity,
                            'link_to_tenant.mall_name' . $priorityMallName,
                        ]
                    ]
                ]
            ]
        ]);
    }

    /**
     * Implement filter by keyword...
     *
     * @param  string $keyword [description]
     * @return [type]          [description]
     */
    public function filterByKeyword($keyword = '')
    {
        $this->setPriorityForQueryStr($this->objectType, $keyword);
        $this->setPriorityForLinkToTenant($this->objectType, $keyword);
    }

    /**
     * Filte by Country and Cities...
     *
     * @param  array  $area [description]
     * @return [type]       [description]
     */
    public function filterByCountryAndCities($area = [])
    {
        if (! empty($area['country'])) {
            $this->must([
                'nested' => [
                    'path' => 'link_to_tenant',
                    'query' => [
                        'match' => [
                            'link_to_tenant.country.raw' => $area['country']
                        ]
                    ],
                    'inner_hits' => [
                        'name' => 'country_city_hits'
                    ],
                ]
            ]);
        }

        if (count($area['cities']) > 0) {

            $citiesQuery['bool']['should'] = [];

            foreach($area['cities'] as $city) {
                $citiesQuery['bool']['should'][] = [
                    'nested' => [
                        'path' => 'link_to_tenant',
                        'query' => [
                            'match' => [
                                'link_to_tenant.city.raw' => $city
                            ]
                        ]
                    ]
                ];
            }

            $this->must($citiesQuery);
        }
    }

    /**
     * Filter by Partner...
     *
     * @param  string $partnerId [description]
     * @return [type]            [description]
     */
    public function filterByPartner($partnerId = '')
    {
        if (empty($partnerId)) {
            return;
        }

        $partnerAffected = PartnerAffectedGroup::join('affected_group_names', function($join) {
                                                    $join->on('affected_group_names.affected_group_name_id', '=', 'partner_affected_group.affected_group_name_id')
                                                            ->where('affected_group_names.group_type', '=', $this->objectTypeAlias);
                                                })
                                                ->where('partner_id', $partnerId)
                                                ->first();

        if (is_object($partnerAffected)) {
            $exception = Config::get('orbit.partner.exception_behaviour.partner_ids', []);

            if (in_array($partnerId, $exception)) {
                $partnerIds = PartnerCompetitor::where('partner_id', $partnerId)->lists('competitor_id');

                $this->mustNot([
                    'terms' => [
                        'partner_ids' => $partnerIds
                    ]
                ]);
            }
            else {
                $this->must([
                    'match' => [
                        'partner_ids' => $partnerId
                    ]
                ]);
            }
        }
    }

    /**
     * Filter by CC..?
     *
     * @return [type] [description]
     */
    public function filterByMyCC($params = [])
    {
        $role = $params['user']->role->role_name;
        if (strtolower($role) === 'consumer') {
            $userId = $params['user']->user_id;
            $sponsorProviderIds = array();

            // get user ewallet
            $userEwallet = UserSponsor::select('sponsor_providers.sponsor_provider_id as ewallet_id')
                                      ->join('sponsor_providers', 'sponsor_providers.sponsor_provider_id', '=', 'user_sponsor.sponsor_id')
                                      ->where('user_sponsor.sponsor_type', 'ewallet')
                                      ->where('sponsor_providers.status', 'active')
                                      ->where('user_sponsor.user_id', $userId)
                                      ->get();

            if (! $userEwallet->isEmpty()) {
              foreach ($userEwallet as $ewallet) {
                $sponsorProviderIds[] = $ewallet->ewallet_id;
              }
            }

            $userCreditCard = UserSponsor::select('sponsor_credit_cards.sponsor_credit_card_id as credit_card_id')
                                      ->join('sponsor_credit_cards', 'sponsor_credit_cards.sponsor_credit_card_id', '=', 'user_sponsor.sponsor_id')
                                      ->join('sponsor_providers', 'sponsor_providers.sponsor_provider_id', '=', 'sponsor_credit_cards.sponsor_provider_id')
                                      ->where('user_sponsor.sponsor_type', 'credit_card')
                                      ->where('sponsor_credit_cards.status', 'active')
                                      ->where('sponsor_providers.status', 'active')
                                      ->where('user_sponsor.user_id', $userId)
                                      ->get();

            if (! $userCreditCard->isEmpty()) {
              foreach ($userCreditCard as $creditCard) {
                $sponsorProviderIds[] = $creditCard->credit_card_id;
              }
            }

            if (! empty($sponsorProviderIds) && is_array($sponsorProviderIds)) {
                $this->must([
                    'nested' => [
                        'path' => 'sponsor_provider',
                        'query' => [
                            'terms' => [
                                'sponsor_provider.sponsor_id' => $sponsorProviderIds
                            ]
                        ]
                    ],
                ]);

                return $sponsorProviderIds;
            }
        }

        return [];
    }

    public function filterBySponsors($sponsorProviderIds = [])
    {
        $sponsorProviderIds = array_values($sponsorProviderIds);

        $this->must([
            'nested' => [
                'path' => 'sponsor_provider',
                'query' => [
                    'terms' => [
                        'sponsor_provider.sponsor_id' => $sponsorProviderIds
                    ]
                ]
            ]
        ]);
    }

    protected function filterAdvertCampaign($options = [])
    {
        $this->must([
            'bool' => [
                'should' => [
                    [
                        'bool' => [
                            'must' => [
                                [
                                    'query' => [
                                        'match' => [
                                            'advert_status' => 'active'
                                        ]
                                    ]
                                ],
                                [
                                    'range' => [
                                        'advert_start_date' => [
                                            'lte' => $options['dateTimeEs']
                                        ]
                                    ]
                                ],
                                [
                                    'range' => [
                                        'advert_end_date' => [
                                            'gte' => $options['dateTimeEs']
                                        ]
                                    ]
                                ],
                                [
                                    'match' => [
                                        'advert_location_ids' => $options['locationId']
                                    ]
                                ],
                                [
                                    'terms' => [
                                        'advert_type' => $options['advertType']
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        'bool' => [
                            'must_not' => [
                                [
                                    'exists' => [
                                        'field' => 'advert_status'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]);
    }

    /**
     * Sort by name..
     *
     * @return [type] [description]
     */
    public function sortByName($language = 'id', $sortMode = 'asc')
    {
        $sortScript =  "if(doc['name_" . $language . "'].value != null) { return doc['name_" . $language . "'].value } else { doc['name_default'].value }";

        $this->sort([
            '_script' => [
                'script' => $sortScript,
                'type' => 'string',
                'order' => $sortMode
            ]
        ]);
    }

    /**
     * Sort store by rating.
     *
     * @param  string $sortingScript [description]
     * @return [type]                [description]
     */
    public function sortByRating($sortingScript = '')
    {
        $this->sort([
            '_script' => [
                'script' => $sortingScript,
                'type' => 'number',
                'order' => 'desc'
            ]
        ]);
    }

    /**
     * Sort store by created date.
     *
     * @param  string $sortingScript [description]
     * @return [type]                [description]
     */
    public function sortByCreatedDate($order = 'desc')
    {
        $this->sort([
            'begin_date' => [
                'order' => $order
            ]
        ]);
    }

    /**
     * Sort store by updated date.
     *
     * @param  string $sortingScript [description]
     * @return [type]                [description]
     */
    public function sortByUpdatedDate($order = 'desc')
    {
        $this->sort([
            'updated_at' => [
                'order' => $order
            ]
        ]);
    }

    /**
     * Sort by relevance..
     *
     * @return [type] [description]
     */
    public function sortByRelevance()
    {
        $this->sort(['_score' => ['order' => 'desc']]);
    }

    /**
     * Sort by Nearest..
     *
     * @return [type] [description]
     */
    public function sortByNearest($ul = null)
    {
        $this->nearestSort('link_to_tenant', 'link_to_tenant.position', $ul);
    }

    /**
     * filter by gender
     *
     * @return void
     */
    public function filterByGender($gender = '')
    {
        switch ($gender){
            case 'male':
                 $this->mustNot(['match' => ['is_all_gender' => 'F']]);
                 break;
            case 'female':
                 $this->mustNot(['match' => ['is_all_gender' => 'M']]);
                 break;
            default:
                // do nothing
        }
    }

}
