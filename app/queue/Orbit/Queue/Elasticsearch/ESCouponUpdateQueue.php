<?php namespace Orbit\Queue\Elasticsearch;
/**
 * Update Elasticsearch index when coupon has been updated.
 *
 * @author Ahmad Anshori <ahmad@dominopos.com>
 */
use Elasticsearch\ClientBuilder as ESBuilder;
use Config;
use DB;
use Coupon;
use Advert;
use IssuedCoupon;
use CouponPaymentProvider;
use Orbit\Helper\Elasticsearch\ElasticsearchErrorChecker;
use Orbit\Helper\Util\JobBurier;
use Exception;
use Orbit\FakeJob;
use Log;
use Orbit\Helper\MongoDB\Client as MongoClient;
use ObjectSponsor;
use SponsorCreditCard;
use ObjectSponsorCreditCard;
use Queue;

class ESCouponUpdateQueue
{
    /**
     * Poster. The object which post the data to external system.
     *
     * @var poster.
     */
    protected $poster = NULL;

    /**
     * Class constructor.
     *
     * @param string $poster Object used to post the data.
     * @return void
     */
    public function __construct($poster = 'default')
    {
        if ($poster === 'default') {
            $this->poster = ESBuilder::create()
                                     ->setHosts(Config::get('orbit.elasticsearch.hosts'))
                                     ->build();
        } else {
            $this->poster = $poster;
        }
    }

    /**
     * Laravel main method to fire a job on a queue.
     */
    public function fire($job, $data)
    {
        try {
            $prefix = DB::getTablePrefix();
            $esConfig = Config::get('orbit.elasticsearch');
            $esPrefix = Config::get('orbit.elasticsearch.indices_prefix');
            $mongoConfig = Config::get('database.mongodb');

            $couponId = $data['coupon_id'];
            $coupon = Coupon::with(
                                'translations.media_orig',
                                'campaignLocations.categories',
                                'esCampaignLocations.geofence',
                                'campaignObjectPartners',
                                'adverts.media_orig',
                                'total_page_views'
                            )
                        ->with (['keywords' => function ($q) {
                                    $q->groupBy('keyword');
                                }])
                        ->with (['product_tags' => function ($q) {
                                    $q->groupBy('product_tag');
                                }])
                        ->select(DB::raw("
                            {$prefix}promotions.*,
                            {$prefix}campaign_account.mobile_default_language,
                            CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired'
                                THEN {$prefix}campaign_status.campaign_status_name
                                ELSE (CASE WHEN {$prefix}promotions.end_date < (SELECT min(CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name))
                                                                                FROM {$prefix}promotion_retailer opt
                                                                                    LEFT JOIN {$prefix}merchants om ON om.merchant_id = opt.retailer_id
                                                                                    LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                                                                    LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                                                                WHERE opt.promotion_id = {$prefix}promotions.promotion_id
                                                                            )
                                THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END)
                            END AS campaign_status
                        "))
                        ->leftJoin('campaign_status', 'promotions.campaign_status_id', '=', 'campaign_status.campaign_status_id')
                        ->join('campaign_account', 'campaign_account.user_id', '=', 'promotions.created_by')
                        ->where('promotions.promotion_id', $couponId)
                        ->where('promotions.available', '!=', 0)
                        ->whereRaw("{$prefix}promotions.is_coupon = 'Y'")
                        ->whereRaw("{$prefix}promotions.is_visible = 'Y'")
                        ->orderBy('promotions.promotion_id', 'asc')
                        ->first();

            if (! is_object($coupon)) {

                $fakeJob = new FakeJob();

                $esCouponDelete = new \Orbit\Queue\Elasticsearch\ESCouponDeleteQueue();
                $doESCouponDelete = $esCouponDelete->fire($fakeJob, ['coupon_id' => $couponId]);

                $esAdvertCouponDelete = new \Orbit\Queue\Elasticsearch\ESAdvertCouponDeleteQueue();
                $doESAdvertCouponDelete = $esAdvertCouponDelete->fire($fakeJob, ['coupon_id' => $couponId]);

                $esCouponSuggestionDelete = new \Orbit\Queue\Elasticsearch\ESCouponSuggestionDeleteQueue();
                $doESCouponSuggestionDelete = $esCouponSuggestionDelete->fire($fakeJob, ['coupon_id' => $couponId]);

                $job->delete();

                return [
                    'status' => 'fail',
                    'message' => sprintf('[Job ID: `%s`] Coupon ID %s is not found.', $job->getJobId(), $couponId)
                ];
            }

            // check exist elasticsearch index
            $params_search = [
                'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.coupons.index'),
                'type' => Config::get('orbit.elasticsearch.indices.coupons.type'),
                'body' => [
                    'query' => [
                        'match' => [
                            '_id' => $coupon->promotion_id
                        ]
                    ]
                ]
            ];

            $response_search = $this->poster->search($params_search);

            $response = NULL;
            $params = [
                'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.coupons.index'),
                'type' => Config::get('orbit.elasticsearch.indices.coupons.type'),
                'id' => $coupon->promotion_id,
                'body' => []
            ];

            $featuredGtmScore = 0;
            $featuredMallScore = 0;
            $preferredGtmScore = 0;
            $preferredMallScore = 0;

            $featuredGtmType = '';
            $featuredMallType = '';
            $preferredGtmType = '';
            $preferredMallType = '';

            $categoryIds = array();
            foreach ($coupon->campaignLocations as $campaignLocation) {
                foreach ($campaignLocation->categories as $category) {
                    $categoryIds[] = $category->category_id;
                }
            }

            $linkToTenants = array();
            foreach($coupon->esCampaignLocations as $esCampaignLocation) {
                $linkToTenant = array(
                    "merchant_id" => $esCampaignLocation->merchant_id,
                    "parent_id" => $esCampaignLocation->parent_id,
                    "name" => $esCampaignLocation->name,
                    "mall_name" => $esCampaignLocation->mall_name,
                    "object_type" => $esCampaignLocation->object_type,
                    "city" => $esCampaignLocation->city,
                    "province" => $esCampaignLocation->province,
                    "country" => $esCampaignLocation->country,
                    "position" => [
                        'lon' => $esCampaignLocation->geofence->longitude,
                        'lat' => $esCampaignLocation->geofence->latitude
                    ]
                );

                $linkToTenants[] = $linkToTenant;
            }

            // wallet operator
            $walletOperators = CouponPaymentProvider::select('payment_providers.payment_name', 'media.cdn_url', 'media.path')
                                                    ->join('payment_providers', 'coupon_payment_provider.payment_provider_id', '=', 'payment_providers.payment_provider_id')
                                                    ->leftJoin('media', function ($q) {
                                                        $q->on('media.object_id', '=', 'payment_providers.payment_provider_id');
                                                        $q->on('media.media_name_id', '=', DB::Raw("'wallet_operator_logo'"));
                                                        $q->on('media.media_name_long', '=', DB::Raw("'wallet_operator_logo_orig'"));
                                                    })
                                                    ->leftJoin('promotion_retailer_redeem', 'promotion_retailer_redeem.promotion_retailer_redeem_id', '=', 'coupon_payment_provider.promotion_retailer_redeem_id')
                                                    ->where('promotion_retailer_redeem.promotion_id', $coupon->promotion_id)
                                                    ->groupBy('payment_providers.payment_provider_id')
                                                    ->get();

            $paymentOperator = array();
            if (! $walletOperators->isEmpty()) {
                foreach ($walletOperators as $walletOperator) {
                    $paymentOperator[] = array(
                        'operator_name' => $walletOperator->payment_name,
                        'operator_logo' => $walletOperator->path,
                        'operator_logo_cdn' => $walletOperator->cdn_url
                    );
                }
            }

            // get rating by location
            $locationRating = array();
            $queryString = [
                'object_id'   => $coupon->promotion_id,
                'object_type' => 'coupon'
            ];

            $mongoClient = MongoClient::create($mongoConfig);
            $endPoint = "review-counters";
            $response = $mongoClient->setQueryString($queryString)
                                    ->setEndPoint($endPoint)
                                    ->request('GET');

            $listOfRecLocation = $response->data;

            if (! empty($listOfRecLocation->records)) {
                $countryRating = array();
                foreach ($listOfRecLocation->records as $rating) {
                    // by country
                    $countryId = $rating->country_id;
                    $countryRating[$countryId]['total'] = (! empty($countryRating[$countryId]['total'])) ? $countryRating[$countryId]['total'] : 0;
                    $countryRating[$countryId]['review'] = (! empty($countryRating[$countryId]['review'])) ? $countryRating[$countryId]['review'] : 0;

                    $countryRating[$countryId]['total'] = $countryRating[$countryId]['total'] + ((double) $rating->average * (double) $rating->counter);
                    $countryRating[$countryId]['review'] = $countryRating[$countryId]['review'] + $rating->counter;

                    $locationRating['rating_' . $countryId] = ((double) $countryRating[$countryId]['total'] / (double) $countryRating[$countryId]['review']) + 0.00001;
                    $locationRating['review_' . $countryId] = (double) $countryRating[$countryId]['review'];

                    // by country and city
                    $locationRating['rating_' . $rating->country_id . '_' . str_replace(" ", "_", trim(strtolower($rating->city), " "))] = $rating->average + 0.00001;
                    $locationRating['review_' . $rating->country_id . '_' . str_replace(" ", "_", trim(strtolower($rating->city), " "))] = $rating->counter;
                }
            }

            // get rating by mall
            $mallRating = array();
            $endPoint = "mall-review-counters";
            $response = $mongoClient->setQueryString($queryString)
                                    ->setEndPoint($endPoint)
                                    ->request('GET');

            $listOfRecMall = $response->data;
            if(! empty($listOfRecMall->records)) {
                foreach ($listOfRecMall->records as $rating) {
                    $mallRating['rating_' . $rating->location_id] = $rating->average + 0.00001;
                    $mallRating['review_' . $rating->location_id] = $rating->counter;
                }
            }

            $keywords = array();
            foreach ($coupon->keywords as $keyword) {
                $keywords[] = $keyword->keyword;
            }

            $productTags = array();
            foreach ($coupon->product_tags as $product_tag) {
                $productTags[] = $product_tag->product_tag;
            }

            $partnerIds = array();
            $partnerTokens = array();
            foreach ($coupon->campaignObjectPartners as $campaignObjectPartner) {
                $partnerIds[] = $campaignObjectPartner->partner_id;
                if (! empty($campaignObjectPartner->token)) {
                    $partnerTokens[] = $campaignObjectPartner->token;
                }
            }

            $advertIds = array();
            foreach ($coupon->adverts as $advertCollection) {
                $advertIds[] = $advertCollection->advert_id;
            }

            // get translation from default lang
            $defaultTranslation = array();
            foreach ($coupon->translations as $defTranslation) {
                if ($defTranslation->name === $coupon->mobile_default_language) {
                    $defaultTranslation = array(
                        'name'          => $defTranslation->promotion_name,
                        'description'   => $defTranslation->description,
                        'language_id'   => $defTranslation->merchant_language_id,
                        'language_code' => $defTranslation->name,
                        'image_url'     => NULL
                    );
                }
            }

            $translations = array();
            $translationBody['name_default'] = preg_replace('/[^a-zA-Z0-9_]/', '', str_replace(" ", "_", strtolower($defaultTranslation['name'])));
            foreach ($coupon->translations as $translationCollection) {
                $translation = array(
                    'name' => $translationCollection->promotion_name,
                    'description' => $translationCollection->description,
                    'language_id' => $translationCollection->merchant_language_id,
                    'language_code' => $translationCollection->name,
                    'image_url' => NULL
                );

                foreach ($translationCollection->media_orig as $media) {
                    $translation['image_url'] = $media->path;
                    $translation['image_cdn_url'] = $media->cdn_url;
                }
                $translations[] = $translation;

                // for "sort A-Z" feature
                $couponName = $translationCollection->promotion_name;
                $couponDesc = $translationCollection->description;

                //if name and description is empty fill with name and desc from default translation
                if (empty($translationCollection->promotion_name) || $translationCollection->promotion_name === '') {
                    $couponName = $defaultTranslation['name'];
                }

                if (empty($translationCollection->description) || $translationCollection->description === '') {
                    $couponDesc = $defaultTranslation['description'];
                }

                $translationBody['name_' . $translationCollection->name] = preg_replace('/[^a-zA-Z0-9_]/', '', str_replace(" ", "_", strtolower($couponName)));
            }

            $total_view_on_gtm = 0;
            $total_view_on_mall = array();
            foreach ($coupon->total_page_views as $key => $total_page_view) {
                if ($total_page_view->location_id != '0') {
                    $total_views = array(
                        'total_views' => $total_page_view->total_view,
                        'location_id' => $total_page_view->location_id
                    );
                    $total_view_on_mall[] = $total_views;
                } else {
                    $total_view_on_gtm = $total_page_view->total_view;
                }
            }

            $emptyRedeem = FALSE;
            $emptyIssued = FALSE;

            if ($coupon->maximum_redeem > 0) {
                $notAvailable = IssuedCoupon::where('status', '=', 'redeemed')
                                            ->where('promotion_id', $coupon->promotion_id)
                                            ->count();

                $tmpAvailable = $coupon->maximum_redeem - $notAvailable;
                if ($notAvailable >= $coupon->maximum_redeem) {
                    $emptyRedeem = TRUE;
                }
            }
            if ($coupon->maximum_issued_coupon > 0) {
                $notAvailable = IssuedCoupon::where('status', '=', 'issued')
                                            ->where('promotion_id', $coupon->promotion_id)
                                            ->count();

                if ($notAvailable >= $coupon->maximum_issued_coupon) {
                    $emptyIssued = TRUE;
                }
                else {
                    // Update availability
                    $available = $coupon->maximum_issued_coupon - $notAvailable;
                }
            }
            if($emptyRedeem || $emptyIssued) {
                $available = 0;
            }

            // If it's sepulsa, don't count availability based on issued coupon because
            // sepulsa has no issued coupons before user buy it.
            if ($coupon->promotion_type === 'sepulsa') {
                $available = $coupon->available;
            }

            // Get url prefix
            $defaultUrlPrefix = Config::get('orbit.cdn.providers.default.url_prefix', '');
            $urlPrefix = ($defaultUrlPrefix != '') ? $defaultUrlPrefix . '/' : '';

            // Coupon sponsor provider
            // Get sponsor provider wallet
            $sponsorProviders = ObjectSponsor::select('object_sponsor.object_sponsor_id','sponsor_providers.sponsor_provider_id','media.path','media.cdn_url', 'object_sponsor.is_all_credit_card')
                                            ->leftJoin('sponsor_providers','sponsor_providers.sponsor_provider_id', '=', 'object_sponsor.sponsor_provider_id')
                                            ->leftJoin('media', function($q){
                                                    $q->on('media.object_id', '=', 'sponsor_providers.sponsor_provider_id')
                                                      ->on('media.media_name_long', '=', DB::raw('"sponsor_provider_logo_orig"'));
                                              })
                                            ->where('object_sponsor.object_type', 'coupon')
                                            ->where('sponsor_providers.status', 'active')
                                            ->where('object_sponsor.object_id', $coupon->promotion_id);

            $sponsorProviderWallets = clone $sponsorProviders;
            $sponsorProviderWallets = $sponsorProviderWallets->where('sponsor_providers.object_type', 'ewallet')
                                                            ->get();

            $sponsorProviderES = array();
            if (!$sponsorProviderWallets->isEmpty()){
                $ewallet = array();
                foreach ($sponsorProviderWallets as $sponsorProviderWallet) {
                    $logoUrl = $sponsorProviderWallet->path;
                    $logoCdnUrl = $sponsorProviderWallet->cdn_url;
                    if ($logoCdnUrl === null && $logoUrl != null) {
                        $logoCdnUrl = $urlPrefix . $logoUrl;
                    }

                    $ewallet['sponsor_id'] = $sponsorProviderWallet->sponsor_provider_id;
                    $ewallet['sponsor_type'] = 'ewallet';
                    $ewallet['bank_id'] = null;
                    $ewallet['logo_url'] = $logoUrl;
                    $ewallet['logo_cdn_url'] = $logoCdnUrl;

                    $sponsorProviderES[] = $ewallet;
                }
            }

            // Get sponsor provider bank
            $sponsorProviderBanks = $sponsorProviders->where('sponsor_providers.object_type', 'bank')
                                                     ->get();

            if (!$sponsorProviderBanks->isEmpty()){
                foreach ($sponsorProviderBanks as $sponsorProviderBank) {
                    if ($sponsorProviderBank->is_all_credit_card === 'Y') {
                        // get all credit_card
                        $sponsorProviderCC = SponsorCreditCard::select('sponsor_credit_card_id')
                                                              ->where('sponsor_provider_id', '=', $sponsorProviderBank->sponsor_provider_id);
                    } elseif ($sponsorProviderBank->is_all_credit_card === 'N') {
                        // get credit_card id by user selection
                        $sponsorProviderCC = ObjectSponsorCreditCard::select('sponsor_credit_cards.sponsor_credit_card_id')
                                                                    ->leftJoin('sponsor_credit_cards', 'sponsor_credit_cards.sponsor_credit_card_id', '=', 'object_sponsor_credit_card.sponsor_credit_card_id')
                                                                    ->where('object_sponsor_credit_card.object_sponsor_id', $sponsorProviderBank->object_sponsor_id);
                    }

                    $sponsorProviderCC = $sponsorProviderCC->get();

                    if (!$sponsorProviderCC->isEmpty()) {
                        $ccArray = array();
                        foreach ($sponsorProviderCC as $cc) {
                            $logoUrl = $sponsorProviderBank->path;
                            $logoCdnUrl = $sponsorProviderBank->cdn_url;
                            if ($logoCdnUrl === null && $logoUrl != null) {
                                $logoCdnUrl = $urlPrefix . $logoUrl;
                            }

                            $ccArray['sponsor_id'] = $cc->sponsor_credit_card_id;
                            $ccArray['sponsor_type'] = 'credit_card';
                            $ccArray['bank_id'] = $sponsorProviderBank->sponsor_provider_id;
                            $ccArray['logo_url'] = $logoUrl;
                            $ccArray['logo_cdn_url'] = $logoCdnUrl;

                            $sponsorProviderES[] = $ccArray;
                        }
                    }
                }
            }

            $body = [
                'promotion_id'            => $coupon->promotion_id,
                'name'                    => $coupon->promotion_name,
                'promotion_type'          => $coupon->promotion_type,
                'description'             => $coupon->description,
                'object_type'             => 'coupon',
                'begin_date'              => date('Y-m-d', strtotime($coupon->begin_date)) . 'T' . date('H:i:s', strtotime($coupon->begin_date)) . 'Z',
                'end_date'                => date('Y-m-d', strtotime($coupon->end_date)) . 'T' . date('H:i:s', strtotime($coupon->end_date)) . 'Z',
                'updated_at'              => date('Y-m-d', strtotime($coupon->updated_at)) . 'T' . date('H:i:s', strtotime($coupon->updated_at)) . 'Z',
                'coupon_validity_in_date' => date('Y-m-d', strtotime($coupon->coupon_validity_in_date)) . 'T' . date('H:i:s', strtotime($coupon->coupon_validity_in_date)) . 'Z',
                'status'                  => $coupon->status,
                'available'               => $available,
                'campaign_status'         => $coupon->campaign_status,
                'is_all_gender'           => $coupon->is_all_gender,
                'is_all_age'              => $coupon->is_all_age,
                'default_lang'            => $coupon->mobile_default_language,
                'category_ids'            => $categoryIds,
                'translation'             => $translations,
                'keywords'                => $keywords,
                'product_tags'            => $productTags,
                'partner_ids'             => $partnerIds,
                'partner_tokens'          => $partnerTokens,
                'advert_ids'              => $advertIds,
                'link_to_tenant'          => $linkToTenants,
                'is_exclusive'            => ! empty($coupon->is_exclusive) ? $coupon->is_exclusive : 'N',
                'gtm_page_views'          => $total_view_on_gtm,
                'mall_page_views'         => $total_view_on_mall,
                'featured_gtm_score'      => $featuredGtmScore,
                'featured_mall_score'     => $featuredMallScore,
                'preferred_gtm_score'     => $preferredGtmScore,
                'preferred_mall_score'    => $preferredMallScore,
                'featured_gtm_type'       => $featuredGtmType,
                'featured_mall_type'      => $featuredMallType,
                'preferred_gtm_type'      => $preferredGtmType,
                'preferred_mall_type'     => $preferredMallType,
                'location_rating'         => $locationRating,
                'mall_rating'             => $mallRating,
                'wallet_operator'         => $paymentOperator,
                'sponsor_provider'        => $sponsorProviderES,
                'price_old'               => $coupon->price_old,
                'merchant_commision'      => $coupon->merchant_commision,
                'price_selling'           => $coupon->price_selling
            ];

            $body = array_merge($body, $translationBody);

            if ($response_search['hits']['total'] > 0) {
                $params = [
                    'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.coupons.index'),
                    'type' => Config::get('orbit.elasticsearch.indices.coupons.type'),
                    'id' => $response_search['hits']['hits'][0]['_id']
                ];

                $response = $this->poster->delete($params);
            }

            $params['body'] = $body;
            $response = $this->poster->index($params);

            // The indexing considered successful is attribute `successful` on `_shard` is more than 0.
            ElasticsearchErrorChecker::throwExceptionOnDocumentError($response);

            $fakeJob = new FakeJob();
            $esQueue = new \Orbit\Queue\Elasticsearch\ESCouponSuggestionUpdateQueue();
            $suggestion = $esQueue->fire($fakeJob, ['coupon_id' => $couponId]);

            // Update suggestion for mall level
            $esSuggestionMallLevelQueue = new \Orbit\Queue\Elasticsearch\ESCouponMallLevelSuggestionUpdateQueue();
            $suggestionMallLevel = $esSuggestionMallLevelQueue->fire($fakeJob, ['coupon_id' => $couponId]);

            $esAdvertQueue = new \Orbit\Queue\Elasticsearch\ESAdvertCouponUpdateQueue();
            $advertUpdate = $esAdvertQueue->fire($fakeJob, ['coupon_id' => $couponId]);

            // Safely delete the object
            $job->delete();

            $message = sprintf('[Job ID: `%s`] Elasticsearch Coupon Update Index; Status: OK; ES Index Name: %s; ES Index Type: %s; Coupon ID: %s; Coupon Name: %s',
                                $job->getJobId(),
                                $esConfig['indices']['coupons']['index'],
                                $esConfig['indices']['coupons']['type'],
                                $coupon->promotion_id,
                                $coupon->promotion_name);
            Log::info($message);

            return [
                'status' => 'ok',
                'message' => $message
            ];
        } catch (Exception $e) {
            $message = sprintf('[Job ID: `%s`] Elasticsearch Coupon Update Index; Status: FAIL; ES Index Name: %s; ES Index Type: %s; Code: %s; Message: %s',
                                $job->getJobId(),
                                $esConfig['indices']['coupons']['index'],
                                $esConfig['indices']['coupons']['type'],
                                $e->getCode(),
                                $e->getMessage());
            Log::info($message);
        }

        // Bury the job for later inspection
        JobBurier::create($job, function($theJob) {
            // The queue driver does not support bury.
            $theJob->delete();
        })->bury();

        return [
            'status' => 'fail',
            'message' => $message
        ];
    }
}
