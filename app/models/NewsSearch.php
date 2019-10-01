<?php

use CampaignSearch;

use Orbit\Helper\Util\FollowStatusChecker;

/**
* Implementation of ES search for Stores...
*/
class NewsSearch extends CampaignSearch
{
    protected $objectType = 'news';
    protected $objectTypeAlias = 'news';


    public function filterWithAdvert($options = [])
    {
        $esAdvertIndex = $this->esConfig['indices_prefix'] . $this->esConfig['indices']['advert_news']['index'];
        $advertSearch = new AdvertSearch($this->esConfig, 'advert_news');

        $advertSearch->setPaginationParams(['from' => 0, 'size' => 100]);

        $advertSearch->filterNews($options);

        $this->filterAdvertCampaign($options);

        $advertSearchResult = $advertSearch->getResult();

        if ($advertSearchResult['hits']['total'] > 0) {
            $advertList = $advertSearchResult['hits']['hits'];
            $excludeId = array();
            $withPreferred = array();

            foreach ($advertList as $adverts) {
                $advertId = $adverts['_id'];
                $newsId = $adverts['_source']['news_id'];
                if(! in_array($newsId, $excludeId)) {
                    $excludeId[] = $newsId;
                } else {
                    $excludeId[] = $advertId;
                }

                // if featured options['list_type'] check preferred too
                if ($options['list_type'] === 'featured') {
                    if ($adverts['_source']['advert_type'] === 'preferred_list_regular' || $adverts['_source']['advert_type'] === 'preferred_list_large') {
                        if (empty($withPreferred[$newsId]) || $withPreferred[$newsId] != 'preferred_list_large') {
                            $withPreferred[$newsId] = 'preferred_list_regular';
                            if ($adverts['_source']['advert_type'] === 'preferred_list_large') {
                                $withPreferred[$newsId] = 'preferred_list_large';
                            }
                        }
                    }
                }
            }

            $this->exclude($excludeId);

            $this->sortBy($options['advertSorting']);

            $this->setIndex($this->getIndex() . ',' . $esAdvertIndex);
        }
    }

}
