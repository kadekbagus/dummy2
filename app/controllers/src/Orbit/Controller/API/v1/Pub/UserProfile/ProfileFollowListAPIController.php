<?php namespace Orbit\Controller\API\v1\Pub\UserProfile;
/**
 * @author firmansyah <firmansyah@dominopos.com>
 * @desc Controller for follow mall and store list
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
use Language;
use Validator;
use User;
use Orbit\Helper\Util\PaginationNumber;
use Activity;
use Carbon\Carbon as Carbon;
use Orbit\Helper\MongoDB\Client as MongoClient;
use stdClass;
use Tenant;
use Mall;

class ProfileFollowListAPIController extends PubControllerAPI
{
    protected $valid_language = NULL;
    protected $withoutScore = FALSE;

    /**
     * GET - get follow mall and srore list per user
     *
     * @author Firmansyayh <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string object_type
     * @param string sortmode
     * @param string take
     * @param string skip
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getFollowList()
    {
        $httpCode = 200;

        try {
            $user = $this->getUser();

            // should always check the role
            $role = $user->role->role_name;
            if (strtolower($role) !== 'consumer') {
                $message = 'You have to login to continue';
                OrbitShopAPI::throwInvalidArgument($message);
            }

            $objectType = OrbitInput::get('object_type', null);
            $mongoConfig = Config::get('database.mongodb');
            $take = PaginationNumber::parseTakeFromGet('news');
            $skip = PaginationNumber::parseSkipFromGet();
            $userId = OrbitInput::get('user_id', $user->user_id);

            $validator = Validator::make(
                array(
                    'object_type' => $objectType
                ),
                array(
                    'object_type' => 'required|in:mall,store'
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // Collect data what user follow (mall and store) from monggo db
            $queryString['user_id'] = $userId;
            $queryString['object_type'] = $objectType;

            $mongoClient = MongoClient::create($mongoConfig);
            $endPoint = "user-follows";

            $merchantId = $mongoClient->setQueryString($queryString)
                                    ->setEndPoint($endPoint)
                                    ->request('GET');

            $merchantIds = array('-');
            if (! empty($merchantId->data->records) > 0) {
                foreach ($merchantId->data->records as $key => $value) {
                    $merchantIds[] = $value->object_id;
                }
            }

            $prefix = DB::getTablePrefix();
            $usingCdn = Config::get('orbit.cdn.enable_cdn', FALSE);
            $defaultUrlPrefix = Config::get('orbit.cdn.providers.default.url_prefix', '');
            $urlPrefix = ($defaultUrlPrefix != '') ? $defaultUrlPrefix . '/' : '';

            // check payment method / wallet operators
            $image = "CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path)";
            if ($usingCdn) {
                $image = "CASE WHEN {$prefix}media.cdn_url IS NULL THEN CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path) ELSE {$prefix}media.cdn_url END";
            }

            // Query to mysql where in merchant id and user id
            if ($objectType === 'mall') {
                $follows = Mall::select('merchant_id', 'name as mall_name', DB::raw("{$image} AS cdn_url"))
                                ->leftJoin('media', function ($q) {
                                                                    $q->on('media.object_id', '=', 'merchants.merchant_id')
                                                                    ->on('media.media_name_long', '=', DB::raw("'mall_logo_orig'"));
                                                                })
                                ->whereIn('merchant_id', $merchantIds)
                                ->excludeDeleted();
            } else if ($objectType === 'store') {
                $prefix = DB::getTablePrefix();
                $follows = Tenant::select('merchants.merchant_id', DB::raw("parent.merchant_id as mall_id"), DB::raw("CONCAT({$prefix}merchants.name,' at ', parent.name) as name"), DB::raw("{$image} AS cdn_url"), DB::raw("{$prefix}merchants.name as store_name"), DB::raw("COUNT({$prefix}merchants.name) as total_follow"))
                                ->leftJoin('media', function ($q) {
                                                                    $q->on('media.object_id', '=', 'merchants.merchant_id')
                                                                    ->on('media.media_name_long', '=', DB::raw("'retailer_logo_orig'"));
                                                                })
                                ->leftJoin('merchants as parent', DB::raw('parent.merchant_id'), '=', 'merchants.parent_id' )
                                ->whereIn('merchants.merchant_id', $merchantIds)
                                ->where('merchants.status', '=', 'active')
                                ->groupBy('merchants.name');
            }

            $_follows = clone($follows);

            $take = PaginationNumber::parseTakeFromGet('news');
            $follows->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $follows->skip($skip);

            $follows->orderBy('merchants.name', 'asc');

            $listOfRec = $follows->get();

            $data = new \stdclass();
            $data->returned_records = count($listOfRec);
            $data->total_records = RecordCounter::create($_follows)->count();

            $data->records = $listOfRec;

            $this->response->data = $data;
            $this->response->code = 0;
            $this->response->status = 'success';

        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

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
            $this->response->data = null;
            $httpCode = 500;

        } catch (Exception $e) {
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 500;
        }

        return $this->render($httpCode);
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }

}