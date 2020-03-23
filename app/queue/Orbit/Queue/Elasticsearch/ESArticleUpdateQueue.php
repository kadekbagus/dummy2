<?php namespace Orbit\Queue\Elasticsearch;
/**
 * Update Elasticsearch index when article has been updated.
 *
 * @author Firmansyah <firmansyah@dominopos.com>
 */
use Elasticsearch\ClientBuilder as ESBuilder;
use Config;
use DB;
use Article;
use Orbit\Helper\Elasticsearch\ElasticsearchErrorChecker;
use Orbit\Helper\Util\JobBurier;
use Exception;
use Log;
use Orbit\FakeJob;

class ESArticleUpdateQueue
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
        $prefix = DB::getTablePrefix();
        $esConfig = Config::get('orbit.elasticsearch');
        $esPrefix = Config::get('orbit.elasticsearch.indices_prefix');

        $articleId = $data['article_id'];

        $article = Article::select(DB::raw("
                                            {$prefix}articles.*,
                                            {$prefix}countries.name as country_name
                                            "))
                            ->join('countries', 'countries.country_id', '=', 'articles.country_id')
                            ->where('article_id', $articleId)
                            // call the all link object
                            ->with('objectNews')
                            ->with('objectPromotion')
                            ->with('objectCoupon')
                            ->with('objectMall')
                            ->with('objectMerchant')
                            ->with('category')
                            ->with('mediaCover')
                            ->with('mediaContent')
                            ->with('video')
                            ->with('cities')
                            ->with('objectPartner')
                            ->first();

        if (! is_object($article)) {
            $job->delete();

            return [
                'status' => 'fail',
                'message' => sprintf('[Job ID: `%s`] Article ID %s is not found.', $job->getJobId(), $articleId)
            ];
        }

        try {
            // check exist elasticsearch index
            $params_search = [
                'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.articles.index'),
                'type' => Config::get('orbit.elasticsearch.indices.articles.type'),
                'body' => [
                    'query' => [
                        'match' => [
                            '_id' => $article->article_id
                        ]
                    ]
                ]
            ];

            $response_search = $this->poster->search($params_search);

            $response = NULL;
            $params  = [
                'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.articles.index'),
                'type' => Config::get('orbit.elasticsearch.indices.articles.type'),
                'id' => $article->article_id,
                'body' => []
            ];

            $cities = array();
            foreach ($article->cities as $city) {
                $cities[] = $city->city;
            }

            // Article Objects

            $linkToEvents = array();
            foreach ($article->object_news as $news) {
                $linkToEvent = array(
                    "event_id" => $news->news_id,
                    "name" => $news->news_name,
                );

                $linkToEvents[] = $linkToEvent;
            }

            $linkToPromotions = array();
            foreach ($article->object_promotion as $promotion) {
                $linkToPromotion = array(
                    "promotion_id" => $promotion->news_id,
                    "name" => $promotion->news_name,
                );

                $linkToPromotions[] = $linkToPromotion;
            }

            $linkToCoupons = array();
            foreach ($article->object_coupon as $coupon) {
                $linkToCoupon = array(
                    "coupon_id" => $coupon->promotion_id,
                    "name" => $coupon->promotion_name,
                );

                $linkToCoupons[] = $linkToCoupon;
            }

            $linkToMalls = array();
            foreach ($article->object_mall as $mall) {
                $linkToMall = array(
                    "mall_id" => $mall->merchant_id,
                    "name" => $mall->name,
                );

                $linkToMalls[] = $linkToMall;
            }

            $linkToMerchants = array();
            foreach ($article->object_merchant as $merchant) {
                $linkToMerchant = array(
                    "brand_id" => $merchant->merchant_id,
                    "name" => $merchant->name,
                );

                $linkToMerchants[] = $linkToMerchant;
            }

            // Categories

            $linkToCategories = array();
            foreach ($article->category as $category) {
                $linkToCategory = array(
                    "category_id" => $category->category_id,
                    "name" => $category->category_name,
                );

                $linkToCategories[] = $linkToCategory;
            }

            // Medias
            $linkToMedias = array();
            foreach ($article->media_cover as $media) {
                $linkToMedia = array(
                    "media_name_long" => $media->media_name_long,
                    "image_url" => $media->path,
                    "image_cdn_url" => $media->cdn_url,
                );

                $linkToMedias[] = $linkToMedia;
            }

            // Partners
            $linkToPartners = array();
            foreach ($article->object_partner as $partner) {
                $linkToPartner = array(
                    "partner_id" => $partner->partner_id,
                    "name" => $partner->partner_name,
                );

                $linkToPartners[] = $linkToPartner;
            }

            $body = [
                'article_id' => $article->article_id,
                'title' => $article->title,
                'slug' => $article->slug,
                'body' => $article->body,
                'meta_title' => $article->meta_title,
                'meta_description' => $article->meta_description,
                'country' => $article->country_name,
                'status' => $article->status,
                'published_at' => date('Y-m-d', strtotime($article->published_at)) . 'T' . date('H:i:s', strtotime($article->published_at)) . 'Z',

                'city' => $cities,
                'link_to_events' => $linkToEvents,
                'link_to_promotions' => $linkToPromotions,
                'link_to_coupons' => $linkToCoupons,
                'link_to_malls' => $linkToMalls,
                'link_to_brands' => $linkToMerchants,
                'link_to_categories' => $linkToCategories,
                'link_to_partners' => $linkToPartners,
                'media' => $linkToMedias
            ];

            if ($response_search['hits']['total'] > 0) {
                $params = [
                    'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.articles.index'),
                    'type' => Config::get('orbit.elasticsearch.indices.articles.type'),
                    'id' => $response_search['hits']['hits'][0]['_id']
                ];

                $response = $this->poster->delete($params);
            }

            $params['body'] = $body;
            $response = $this->poster->index($params);

            // The indexing considered successful is attribute `successful` on `_shard` is more than 0.
            ElasticsearchErrorChecker::throwExceptionOnDocumentError($response);

            // Safely delete the object
            $job->delete();

            $message = sprintf('[Job ID: `%s`] Elasticsearch Update Index; Status: OK; ES Index Name: %s; ES Index Type: %s; Article ID: %s; Article Name: %s',
                                $job->getJobId(),
                                $esConfig['indices']['articles']['index'],
                                $esConfig['indices']['articles']['type'],
                                $article->article_id,
                                $article->title);
            Log::info($message);

            return [
                'status' => 'ok',
                'message' => $message
            ];
        } catch (Exception $e) {
            $message = sprintf('[Job ID: `%s`] Elasticsearch Update Index; Status: FAIL; ES Index Name: %s; ES Index Type: %s; Code: %s; Message: %s',
                                $job->getJobId(),
                                $esConfig['indices']['articles']['index'],
                                $esConfig['indices']['articles']['type'],
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