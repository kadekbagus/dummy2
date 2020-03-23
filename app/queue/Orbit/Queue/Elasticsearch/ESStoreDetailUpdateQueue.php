<?php namespace Orbit\Queue\Elasticsearch;
/**
 * Update Elasticsearch index when store/tenant has been updated.
 *
 * @author kadek <kadek@dominopos.com>
 */
use Elasticsearch\ClientBuilder as ESBuilder;
use Config;
use DB;
use Tenant;
use BaseStore;
use Orbit\Helper\Elasticsearch\ElasticsearchErrorChecker;
use Orbit\Helper\Util\JobBurier;
use Exception;
use Log;
use Queue;
use Orbit\FakeJob;

class ESStoreDetailUpdateQueue
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
        $updateRelated = (empty($data['update_related']) ? FALSE : $data['update_related']);

        $storeName = $data['name'];
        $countryName = $data['country'];
        $store = Tenant::with('keywords','translations','adverts','campaignObjectPartners', 'categories', 'product_tags')
                        ->select(
                            'merchants.merchant_id',
                            'merchants.name',
                            'merchants.description',
                            'merchants.phone',
                            'merchants.floor',
                            'merchants.unit',
                            'merchants.url',
                            'merchants.object_type',
                            'merchants.created_at',
                            'merchants.updated_at',
                            'merchants.mobile_default_language',
                            'merchants.disable_ads',
                            'merchants.disable_ymal',
                            'media.path',
                            'media.cdn_url',
                            DB::raw("x({$prefix}merchant_geofences.position) as latitude"),
                            DB::raw("y({$prefix}merchant_geofences.position) as longitude"),
                            DB::raw('oms.merchant_id as mall_id'),
                            DB::raw('oms.name as mall_name'),
                            DB::raw('oms.city'),
                            DB::raw('oms.province'),
                            DB::raw('oms.country'),
                            DB::raw('oms.address_line1 as address'),
                            DB::raw('oms.operating_hours'))
                        ->join(DB::raw("(
                            select merchant_id, name, status, parent_id, city,
                                   province, country, address_line1, operating_hours
                            from {$prefix}merchants
                            where status = 'active'
                                and object_type = 'mall'
                            ) as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                        ->leftJoin('media', function($q) {
                            $q->on('media.media_name_long', '=', DB::raw("'retailer_logo_orig'"));
                            $q->on('media.object_id', '=', 'merchants.merchant_id');
                        })
                        ->leftJoin('merchant_geofences', function($q) {
                            $q->on('merchant_geofences.merchant_id', '=', 'merchants.parent_id');
                        })
                        ->whereRaw("{$prefix}merchants.status = 'active'")
                        ->whereRaw("oms.status = 'active'")
                        ->where('merchants.name', '=', $storeName)
                        ->whereRaw("oms.country = '{$countryName}'")
                        ->orderBy('merchants.created_at', 'asc')
                        ->get();

        try {
            // check exist elasticsearch index
            $params_search = [
                'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.store_details.index'),
                'type' => Config::get('orbit.elasticsearch.indices.store_details.type'),
                'body' => [
                    'query' => [
                        'filtered' => [
                            'filter' => [
                                'and' => [
                                    [
                                        'match' => [
                                            'name.raw' => $storeName
                                        ]
                                    ],
                                    [
                                        'match' => [
                                            'country.raw' => $countryName
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ];

            $response_search = $this->poster->search($params_search);

            // delete the store document if exist
            if ($response_search['hits']['total'] > 0) {
                foreach ($response_search['hits']['hits'] as $hits) {
                    $deleteParams = [
                        'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.store_details.index'),
                        'type' => Config::get('orbit.elasticsearch.indices.store_details.type'),
                        'id' => $hits['_id']
                    ];

                    $deleteResponse = $this->poster->delete($deleteParams);
                }
            }

            if ($store->isEmpty()) {
                $job->delete();

                return [
                    'status' => 'fail',
                    'message' => sprintf('[Job ID: `%s`] Store Name %s is not found.', $job->getJobId(), $storeName)
                ];
            }

            $body = array();
            foreach ($store as $_store) {

                $advertIds = array();
                foreach ($_store->adverts as $advert) {
                     $advertIds[] = $advert->advert_id;
                }

                $categoryIds = array();
                foreach ($_store->categories as $category) {
                     $categoryIds[] = $category->category_id;
                }

                $keywords = array();
                foreach ($_store->keywords as $keyword) {
                     $keywords[] = $keyword->keyword;
                }

                $productTags = array();
                foreach ($_store->product_tags as $product_tag) {
                    $productTags[] = $product_tag->product_tag;
                }

                $partnerIds = array();
                foreach ($_store->campaignObjectPartners as $campaignObjectPartner) {
                    $partnerIds[] = $campaignObjectPartner->partner_id;
                }

                $translations = array();
                foreach ($_store->translations as $translation) {
                    $trans = array(
                                    "description" => $translation->description,
                                    "language_id" => $translation->language_id,
                                    "language_code" => $translation->name
                                  );
                    $translations[] = $trans;
                }

                $baseStore = BaseStore::where('base_store_id', $_store->merchant_id)->first();
                $baseMerchantId = null;
                if (! empty($baseStore)) {
                    $baseMerchantId = $baseStore->base_merchant_id;
                }

                $body = array(
                    'merchant_id'      => $_store->merchant_id,
                    'name'             => $_store->name,
                    'description'      => $_store->description,
                    'phone'            => $_store->phone,
                    'logo'             => $_store->path,
                    'logo_cdn'         => $_store->cdn_url,
                    'object_type'      => $_store->object_type,
                    'category'         => $categoryIds,
                    'keywords'         => $keywords,
                    'product_tag'      => $productTags,
                    'partner_ids'      => $partnerIds,
                    'mall_id'          => $_store->mall_id,
                    'mall_name'        => $_store->mall_name,
                    'city'             => $_store->city,
                    'province'         => $_store->province,
                    'country'          => $_store->country,
                    'advert_ids'       => $advertIds,
                    'address'          => $_store->address,
                    'position'         => ['lon' => $_store->longitude, 'lat' => $_store->latitude ],
                    'floor'            => $_store->floor,
                    'unit'             => $_store->unit,
                    'operating_hours'  => $_store->operating_hours,
                    'logo'             => $_store->path,
                    'logo_cdn'         => $_store->cdn_url,
                    'url'              => $_store->url,
                    'default_lang'     => $_store->mobile_default_language,
                    'disable_ads'      => $_store->disable_ads,
                    'disable_ymal'     => $_store->disable_ymal,
                    'created_at'       => date('Y-m-d', strtotime($_store->created_at)) . 'T' . date('H:i:s', strtotime($_store->created_at)) . 'Z',
                    'updated_at'       => date('Y-m-d', strtotime($_store->updated_at)) . 'T' . date('H:i:s', strtotime($_store->updated_at)) . 'Z',
                    'translation'      => $translations,
                    'base_merchant_id' => $baseMerchantId
                );

                $response = NULL;
                $params = [
                    'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.store_details.index'),
                    'type' => Config::get('orbit.elasticsearch.indices.store_details.type'),
                    'id' => $_store->merchant_id,
                    'body' => []
                ];

                $params['body'] = $body;
                $response = $this->poster->index($params);

                // The indexing considered successful is attribute `successful` on `_shard` is more than 0.
                ElasticsearchErrorChecker::throwExceptionOnDocumentError($response);
            }

            // Safely delete the object
            $job->delete();

            $message = sprintf('[Job ID: `%s`] Elasticsearch Update Index; Status: OK; ES Index Name: %s; ES Index Type: %s; Store ID : %s; Store Name: %s',
                                $job->getJobId(),
                                $esConfig['indices']['stores']['index'],
                                $esConfig['indices']['stores']['type'],
                                $store[0]->merchant_id,
                                $store[0]->name);
            Log::info($message);

            return [
                'status' => 'ok',
                'message' => $message
            ];
        } catch (Exception $e) {
            $message = sprintf('[Job ID: `%s`] Elasticsearch Update Index; Status: FAIL; ES Index Name: %s; ES Index Type: %s; Code: %s; Message: %s',
                                $job->getJobId(),
                                $esConfig['indices']['stores']['index'],
                                $esConfig['indices']['stores']['type'],
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