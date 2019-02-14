<?php namespace Orbit\Controller\API\v1\Pub\Rating;
/**
 * @author firmansyah <firmansyah@dominopos.com>
 * @desc Controller for get reply rating review list
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
use Validator;
use User;
use Orbit\Helper\Util\PaginationNumber;
use Activity;
use Carbon\Carbon as Carbon;
use Orbit\Helper\MongoDB\Client as MongoClient;
use stdClass;
use Country;
use Tenant;
use News;

class ReplyRatingReviewListAPIController extends PubControllerAPI
{
    protected $withoutScore = FALSE;

    /**
     * GET - get reply rating review list
     *
     * @author Firmansyayh <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string sortby
     * @param string sortmode
     * @param string take
     * @param string skip
     * @param string keyword
     * @param string filter_name
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getReplyRatingReviewList()
    {
        $httpCode = 200;

        try {
            $user = $this->getUser();
            $objectId = OrbitInput::get('object_id', null);
            $objectType = OrbitInput::get('object_type', null);
            $cityFilters = OrbitInput::get('cities', null);
            $countryFilter = OrbitInput::get('country', null);
            $take = PaginationNumber::parseTakeFromGet('news');
            $skip = PaginationNumber::parseSkipFromGet();
            $mongoConfig = Config::get('database.mongodb');
            $mallId = OrbitInput::get('mall_id', null);
            $parentId = OrbitInput::get('parent_id', null);

            // search by key word or filter or sort by flag
            $searchFlag = FALSE;

            $validator = Validator::make(
                array(
                    'object_id'   => $objectId,
                    'object_type' => $objectType,
                ),
                array(
                    'object_id' => 'required',
                    'object_type' => 'required'
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $queryString = [
                'object_id'   => $objectId,
                'object_type' => $objectType,
                'take'        => $take,
                'skip'        => $skip,
                'parent_id'   => $parentId,
                'sortBy'      => 'created_at',
                'sortMode'    => 'desc'
            ];

            $arrayQuery = '';
            if ($objectType === 'store') {
                $prefix = DB::getTablePrefix();
                $storeInfo = Tenant::select('merchants.name', DB::raw("oms.country"))
                            ->leftJoin(DB::raw("{$prefix}merchants as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                            ->where('merchants.merchant_id', $objectId)
                            ->first();

                if (! is_object($storeInfo)) {
                    throw new OrbitCustomException('Unable to find store.', Tenant::NOT_FOUND_ERROR_CODE, NULL);
                }

                $storeIds = [];
                $storeIdList = Tenant::select('merchants.merchant_id')
                                ->leftJoin(DB::raw("{$prefix}merchants as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                                ->where('merchants.status', '=', 'active')
                                ->where(DB::raw('oms.status'), '=', 'active')
                                ->where('merchants.name', $storeInfo->name)
                                ->where(DB::raw("oms.country"), $storeInfo->country)
                                ->get();

                foreach ($storeIdList as $storeId) {
                    $storeIds[] = $storeId->merchant_id;
                }

                $arrayQuery = 'object_id[]=' . implode('&object_id[]=', $storeIds);
                unset($queryString['object_id']);
            }

            if (empty($mallId)) {
                if (! empty($cityFilters)) $queryString['cities'] = $cityFilters;
                if (! empty($countryFilter)) {
                    $country = Country::where('name', $countryFilter)->first();
                    if (is_object($country)) $queryString['country_id'] = $country->country_id;
                }
            } else {
                $queryString['location_id'] = $mallId;
            }

            $mongoClient = MongoClient::create($mongoConfig);

            $endPoint = "reviews";
            if (! empty($arrayQuery)) {
                $endPoint = "reviews?" . $arrayQuery;
                $mongoClient = $mongoClient->setCustomQuery(TRUE);
            }

            // check if promotional event, remove filter location
            if ($objectType === 'news') {
                $news = News::where('news_id', $objectId)->first();
                $isPromotionalEvent = $news->is_having_reward;

                if ($isPromotionalEvent === 'Y') {
                    unset($queryString['cities']);
                    unset($queryString['country_id']);
                    unset($queryString['location_id']);
                }
            }

            $response = $mongoClient->setQueryString($queryString)
                                    ->setEndPoint($endPoint)
                                    ->request('GET');

            $listOfRec = $response->data;

            if (! empty($listOfRec->records)) {
                $userIds = array();
                $userIdReplies = array();
                foreach ($listOfRec->records as $rating) {
                    $userIds[] = $rating->user_id;
                    $userIdReplies[] = $rating->user_id_replied;
                }

                // get user name and photo
                $prefix = DB::getTablePrefix();
                $usingCdn = Config::get('orbit.cdn.enable_cdn', FALSE);
                $defaultUrlPrefix = Config::get('orbit.cdn.providers.default.url_prefix', '');
                $urlPrefix = ($defaultUrlPrefix != '') ? $defaultUrlPrefix . '/' : '';

                $image = "(CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path)) as user_picture";
                if ($usingCdn) {
                    $image = "(CASE WHEN {$prefix}media.cdn_url IS NULL THEN CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path) ELSE {$prefix}media.cdn_url END) as user_picture";
                }

                $userList = User::select('users.user_id', 'users.user_email', 'roles.role_name', DB::raw("(CONCAT({$prefix}users.user_firstname, ' ', {$prefix}users.user_lastname)) as user_name"), DB::raw($image))
                                  ->leftJoin('media', function ($q) {
                                        $q->on('media.object_id', '=', 'users.user_id')
                                          ->on('media.media_name_long', '=', DB::raw("'user_profile_picture_orig'"));
                                    })
                                  ->join('roles', 'roles.role_id', '=', 'users.user_role_id')
                                  ->whereIn('users.user_id', $userIds)
                                  ->groupBy('users.user_id')
                                  ->get();

                $userRepliedList = User::select('users.user_id', DB::raw("(CONCAT({$prefix}users.user_firstname, ' ', {$prefix}users.user_lastname)) as user_name_replied"))
                                  ->whereIn('users.user_id', $userIdReplies)
                                  ->get();

                $roleOfficial = ['Merchant Review Admin', 'Master Review Admin'];
                $isOfficialUser = 'n';
                $userRating = array();
                foreach ($userList as $list) {
                    $userRating[$list->user_id]['user_email'] = $list->user_email;
                    $userRating[$list->user_id]['user_name'] = $list->user_name;
                    $userRating[$list->user_id]['user_picture'] = $list->user_picture;

                    if (in_array($list->role_name, $roleOfficial)) {
                        $isOfficialUser = 'y';
                    }
                    $userRating[$list->user_id]['is_official_user'] = $isOfficialUser;
                }

                $userRatingReplied = array();
                foreach ($userRepliedList as $userReplied) {
                    $userRatingReplied[$userReplied->user_id]['user_name_replied'] = $userReplied->user_name_replied;
                }

                foreach ($listOfRec->records as $rating) {
                    $rating->user_name = '';
                    if (! empty($userRating[$rating->user_id]['user_name'])) {
                        $rating->user_name = $userRating[$rating->user_id]['user_name'];
                    }

                    $rating->user_picture = '';
                    if (! empty($userRating[$rating->user_id]['user_picture'])) {
                        $rating->user_picture = $userRating[$rating->user_id]['user_picture'];
                    }

                    $rating->user_name_replied = '';
                    if (! empty($userRatingReplied[$rating->user_id_replied]['user_name_replied'])) {
                        $rating->user_name_replied = $userRatingReplied[$rating->user_id_replied]['user_name_replied'];
                    }

                    $rating->is_official_user = '';
                    if (! empty($userRating[$rating->user_id]['is_official_user'])) {
                        $rating->is_official_user = $userRating[$rating->user_id]['is_official_user'];
                    }

                    $rating->user_email = '';
                    if (! empty($userRating[$rating->user_id]['user_email'])) {
                        $rating->user_email = $userRating[$rating->user_id]['user_email'];
                    }
                }
            }

            $data = new \stdclass();
            $data->returned_records = $listOfRec->returned_records;
            $data->total_records = $listOfRec->total_records;
            $data->records = $listOfRec->records;
            $data->user_status = $user->status;

            $this->response->data = $data;
            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Request Ok';

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

    /**
     * Force $withScore value to FALSE, ignoring previously set value
     * @param $bool boolean
     */
    public function setWithOutScore()
    {
        $this->withoutScore = TRUE;

        return $this;
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }
}
