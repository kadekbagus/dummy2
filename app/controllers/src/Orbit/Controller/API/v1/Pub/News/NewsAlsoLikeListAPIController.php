<?php namespace Orbit\Controller\API\v1\Pub\News;

/**
 * @author Irianto <irianto@dominopos.com>
 * @author Rio Astamal <rio@dominopos.com>
 * @desc Controller for news list you might also like
 */

use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use Helper\EloquentRecordCounter as RecordCounter;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use \Config;
use \Exception;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use \DB;
use \URL;
use News;
use NewsMerchant;
use Language;
use Validator;
use Orbit\Helper\Util\PaginationNumber;
use Activity;
use Orbit\Controller\API\v1\Pub\SocMedAPIController;
use Orbit\Controller\API\v1\Pub\News\NewsHelper;
use Mall;
use Orbit\Helper\Util\CdnUrlGeneratorWithCloudfront;
use Orbit\Helper\Database\Cache as OrbitDBCache;
use Orbit\Helper\Util\ObjectPartnerBuilder;
use Elasticsearch\ClientBuilder;
use Orbit\Helper\Elasticsearch\IndexNameBuilder;
use Orbit\Controller\API\v1\Pub\News\NewsListNewAPIController;

class NewsAlsoLikeListAPIController extends PubControllerAPI
{
    protected $valid_language = NULL;
    /**
     * GET - get news you might also like
     *
     * @author Irianto <irianto@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string sortby
     * @param string sortmode
     * @param string take
     * @param string skip
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchNews()
    {
        $httpCode = 200;
        $user = NULL;
        $mall = NULL;

        try {
            $this->checkAuth();
            $user = $this->api->user;
            $show_total_record = OrbitInput::get('show_total_record', NULL);

            $exceptId = OrbitInput::get('except_id');
            $categoryId = OrbitInput::get('category_id', null);
            $partnerId = OrbitInput::get('partner_id');
            $country = OrbitInput::get('country', NULL);
            $cities = OrbitInput::get('cities', []);
            $ul = OrbitInput::get('ul', NULL);
            $sortBy = OrbitInput::get('sortby', NULL);
            $sortMode = OrbitInput::get('sortmode', NULL);
            $language = OrbitInput::get('language', 'id');
            $token = OrbitInput::get('token', NULL);
            $take = OrbitInput::get('take', 4); // @todo take from config
            $isHotEvent = OrbitInput::get('is_hot_event', 'no');

            //+1 to allow front end doing simple test
            //to display see all button or not
            $take = $take + 1;

            $lon = '';
            $lat = '';
            $mallId = OrbitInput::get('mall_id', NULL);
            $esConfig = Config::get('orbit.elasticsearch');
            $esIndex = 'news';

            $esNameBuilder = new IndexNameBuilder($esConfig);
            $esCurrentCampaignParams = [
                'index' => $esNameBuilder->getIndexPrefixAndName($esIndex),
                'type' => $esNameBuilder->getTypeName($esIndex),
                'id' => $exceptId
            ];

            $elasticClient = ClientBuilder::create()
                        ->setHosts($esConfig['hosts'])
                        ->build();

            $params = [
                'except_id'   => $exceptId,
                'category_id' => $categoryId,
                'partner_id'  => $partnerId,
                'cities'      => $cities,
                'country'     => $country,
                'ul'          => $ul,
                'sort_by'     => $sortBy,
                'sort_mode'   => $sortMode,
                'language'    => $language,
                'lon'         => $lon,
                'lat'         => $lat,
                'mall_id'     => $mallId,
                'token'       => $token,
                'filter'      => 'Y',
                'primary_key' => 'news_id',
                'take'        => $take,
                'is_hot_event' => $isHotEvent,
            ];

            // Reset the index of the array by array_values so it does not decode as an object
            // when returned as JSON
            $similarItems = array_values($this->getItemListUsingES($params));

            $data = new \stdclass();
            $data->returned_records = count($similarItems);
            $data->total_records = $data->returned_records;
            $data->records = $similarItems;

            $this->updateResponseForMall($elasticClient, $esNameBuilder, $data, $mallId);

            $this->response->data = $data;
            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Request Ok';

        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;
            $httpCode = 403;

        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = NULL;

            $this->response->data = $result;
            $httpCode = 403;

        } catch (QueryException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            // Only shows full query error when we are in debug mode
            if (Config::get('app.debug')) {
                $this->response->message = $e->getMessage();
            } else {
                $this->response->message = Lang::get('validation.orbit.queryerror');
            }
            $this->response->data = NULL;
            $httpCode = 500;

        } catch (Exception $e) {
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = $e->getLine();
            $httpCode = 500;
        }

        return $this->render($httpCode);
    }

    /**
     * @return array
     */
    protected function getItemListUsingES($params)
    {
        $_OLD_GET = $_GET;
        $_GET = [];
        $_GET['take'] = $params['take'];
        $_GET['skip'] = 0;
        $_GET['country'] = $params['country'];
        $_GET['cities'] = $params['cities'];
        $_GET['category_id'] = empty($params['category_id']) ? NULL : $params['category_id'];
        $_GET['mall_id'] = $params['mall_id'];
        $_GET['ul'] = $params['ul'];
        $_GET['sortby'] = $params['sort_by'];
        $_GET['sortmode'] = $params['sort_mode'];
        $_GET['language'] = $params['language'];
        $_GET['token'] = $params['token'];
        $_GET['from_homepage'] = 'y';   // prevent activity recording
        $_GET['excluded_ids'] = (array)$params['except_id'];
        $_GET['is_hot_event'] = $params['is_hot_event'];

        if ($params['sort_by'] === 'location') {
            unset($_GET['cities']);
        }

        if (! empty($params['mall_id'])) {
            $_GET['sortby'] = 'created_date';
            $_GET['sortmode'] = 'desc';
        }

        foreach ($_GET as $key=>$value) {
            if (empty($_GET[$key])) {
                unset($_GET[$key]);
            }
        }

        Config::set('orbit.cache.context.event-list.enable', FALSE);

        $responseSameCategory = NewsListNewAPIController::create('raw')
                    ->setUser($this->api->user)
                    ->getSearchNews();

        if ($responseSameCategory->code !== 0) {
            throw new Exception($responseSameCategory->message, $responseSameCategory->code);
        }

        $sameCategoryRecords = $responseSameCategory->data->records;
        $sameCategoryRecords = $this->removeUnusedProperty($sameCategoryRecords);

        // Get promotion list excluding the category
        if (count($sameCategoryRecords) < $_GET['take']) {
            unset($_GET['category_id']);

            // set excluded ids
            foreach ($sameCategoryRecords as $sameCategoryRecord) {
                $_GET['excluded_ids'][] = $sameCategoryRecord->{$params['primary_key']};
            }

            // get news
            $responseSameType = NewsListNewAPIController::create('raw')
                    ->setUser($this->api->user)
                    ->getSearchNews();

            if ($responseSameType->code !== 0) {
                throw new Exception($responseSameType->message, $responseSameCategory->code);
            }

            $sameCampaignTypeRecords = $responseSameType->data->records;
            $sameCampaignTypeRecords = $this->removeUnusedProperty($sameCampaignTypeRecords);

            foreach ($sameCampaignTypeRecords as $sameCampaignTypeRecord) {
                $sameCategoryRecords[] = $sameCampaignTypeRecord;
            }
        }

        $sameCategoryRecords = array_slice($sameCategoryRecords, 0, $_GET['take'], true);

        $_GET = $_OLD_GET;

        return $sameCategoryRecords;
    }

    /**
     * Remove unused properties from the result
     *
     * @param array
     * @return array
     */
    protected function removeUnusedProperty(array $records)
    {
        // Return only needed properties
        return array_map(function($element) {
            $properties = [
                'news_id',
                'news_name',
                'description',
                'object_type',
                'image_url',
                'campaign_status',
                'begin_date',
                'is_exclusive',
                'is_having_reward'
            ];

            $object = new \stdClass();
            $object->is_started = true;

            foreach ($properties as $property) {
                $object->{$property} = $element[$property];
            }

            return $object;
        }, $records);
    }

    /**
     * @return string
     */
    protected function getCategoryFromDocument($document)
    {
        return $document['_source']['category_ids'];
    }

    /**
     * @return string
     */
    protected function getCountryFromDocument($document)
    {
        return $document['_source']['link_to_tenant'][0]['country'];
    }

    /**
     * @return string
     */
    protected function getCitiesFromDocument($document)
    {
        $linkToTenants = $document['_source']['link_to_tenant'];
        $cities = [];

        foreach ( $linkToTenants as $linkToTenant) {
            $cities[] = $linkToTenant['city'];
        }

        return $cities;
    }

    /**
     * Update response property to include mall name
     *
     * @param object $esClient
     * @param object $data
     * @param string $mallId
     * @return void
     */
    protected function updateResponseForMall($esClient, $esIndexBuilder, $data, $mallId)
    {
        if (empty($mallId)) {
            return NULL;
        }

        $esMallParam = [
            'index' => $esIndexBuilder->getIndexPrefixAndName('malldata'),
            'type' => $esIndexBuilder->getTypeName('malldata'),
            'id' => $mallId
        ];
        // $mall = Mall::excludeDeleted()->where('merchant_id', $mallId)->first();
        $mall = $esClient->get($esMallParam);

        $data->mall_name = $mall['_source']['name'];
    }
}
