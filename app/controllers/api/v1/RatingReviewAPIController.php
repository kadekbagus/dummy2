<?php
/**
 * An API controller for mall location (country,city,etc).
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Text\Util\LineChecker;
use Helper\EloquentRecordCounter as RecordCounter;
use DominoPOS\OrbitUploader\Uploader as OrbitUploader;
use Carbon\Carbon as Carbon;
use Orbit\Helper\OneSignal\OneSignal;
use Orbit\Helper\MongoDB\Client as MongoClient;

class RatingReviewAPIController extends ControllerAPI
{
    protected $viewRoles = ['merchant review admin', 'master review admin'];
    /**
     * GET - rating review list for portal
     * @author kadek <kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string            `sort_by`                       (optional) - column order by
     * @param string            `sort_mode`                     (optional) - asc or desc
     * @param integer           `take`                          (optional) - limit
     * @param integer           `skip`                          (optional) - limit
     *
     * @return Illuminate\Support\Facades\Response
     *
     */
    public function getReviewList()
    {
        try {
            $httpCode = 200;

            // Require authentication
            $this->checkAuth();

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->viewRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.mall_country.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }

            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.mall_country.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            // Get the take args
            $take = $perPage;
            OrbitInput::get('take', function ($_take) use (&$take, $maxRecord) {
                if ($_take > $maxRecord) {
                    $_take = $maxRecord;
                }
                $take = $_take;

                if ((int)$take <= 0) {
                    $take = $maxRecord;
                }
            });

            $skip = 0;
            OrbitInput::get('skip', function ($_skip) use (&$skip) {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });

            $beginDate = OrbitInput::get('begin_date', null);
            $endDate = OrbitInput::get('end_date', null);
            $rating = OrbitInput::get('rating', null);
            $review = OrbitInput::get('review', null);
            $type = OrbitInput::get('type', null);

            // Default sort by
            $sortBy = 'created_at';
            // Default sort mode
            $sortMode = 'desc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'created_at' => 'created_at',
                    'rating'     => 'rating',
                    'review'     => 'review',
                    'type'       => 'object_type',
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) === 'asc') {
                    $sortMode = 'asc';
                }
            });

            $emptyStore = false;
            $queryString = [];

            // for user merchant (show only review for that merchant)
            if ($role->role_name == 'Merchant Review Admin') {
                $storeIds = [];
                $stores = User::select('base_stores.base_store_id')
                             ->join('user_merchant_reviews', 'user_merchant_reviews.user_id', '=', 'users.user_id')
                             ->join('base_merchants', 'base_merchants.base_merchant_id', '=', 'user_merchant_reviews.merchant_id')
                             ->join('base_stores', 'base_stores.base_merchant_id', '=', 'base_merchants.base_merchant_id')
                             ->where('users.user_id','=', $user->user_id)
                             ->get();

                if (!empty($stores)) {
                    foreach($stores as $key => $value) {
                        $storeIds[] = $value->base_store_id;
                    }
                } else {
                    $emptyStore = true;
                }

                $queryString = [
                    'take'         => $take,
                    'skip'         => $skip,
                    'sortBy'       => $sortBy,
                    'sortMode'     => $sortMode,
                    'store_ids'    => $storeIds
                ];
            }

            // for user gotomalls (show all review)
            if ($role->role_name == 'Master Review Admin') {
                $queryString = [
                    'take'         => $take,
                    'skip'         => $skip,
                    'sortBy'       => $sortBy,
                    'sortMode'     => $sortMode,
                ];
            }

            // filter date
            if (!empty($beginDate) && !empty($endDate)) {
                $queryString['begin_date'] = $beginDate;
                $queryString['end_date'] = $endDate;
            }

            // filter rating
            if (!empty($rating)) {
                $queryString['rating_portal'] = $rating;
            }

            // filter review
            if (!empty($review)) {
                $queryString['review_portal'] = $review;
            }

            // filter type
            if (!empty($type)) {
                $queryString['object_type_portal'] = $type;
            }

            $mongoConfig = Config::get('database.mongodb');
            $mongoClient = MongoClient::create($mongoConfig);
            $response = $mongoClient->setQueryString($queryString)
                                    ->setEndPoint('reviews')
                                    ->request('GET');

            $listOfRec = $response->data;

            // get username and object name from mysql
            if (!empty($listOfRec->records)) {
                foreach ($listOfRec->records as $key => $value) {
                    $userId = $listOfRec->records[$key]->user_id;
                    $objectId = $listOfRec->records[$key]->object_id;
                    $objectType = $listOfRec->records[$key]->object_type;

                    $userName = '';
                    $objectName = '';
                    $user = User::select('user_firstname', 'user_lastname')->where('user_id', '=', $userId)->first();
                    if (is_object($user)) {
                        $userName = $user->user_firstname.' '.$user->user_lastname;
                    }
                    $listOfRec->records[$key]->user_name = $userName;

                    switch(strtolower($objectType)) {
                        case 'coupon':
                            $object = Coupon::select('promotion_name as object_name')->where('promotion_id', '=', $objectId)->first();
                            break;
                        case 'store':
                            $object = Tenant::select('name as object_name')->where('merchant_id', '=', $objectId)->first();
                            break;
                        case 'mall':
                            $object = Mall::select('name as object_name')->where('merchant_id', '=', $objectId)->first();
                            break;
                        default:
                            $object = News::select('news_name as object_name')->where('news_id', '=', $objectId)->first();
                    }

                    if (is_object($object)) {
                        $objectName = $object->object_name;
                    }
                    $listOfRec->records[$key]->object_name = $objectName;
                }
            }

            if (count($listOfRec->records) === 0 || $emptyStore) {
                $data = null;
            } else {
                $data = new \stdclass();
                $data->returned_records = $listOfRec->returned_records;
                $data->total_records = $listOfRec->total_records;
                $data->records = $listOfRec->records;
            }

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
        }

        $output = $this->render($httpCode);

        return $output;
    }

    /**
     * POST - update reply
     * @author kadek <kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string     `id`                       (required) - id of the mongo db review
     * @param string     `review`                   (required) - the review or reply (same thing)
     *
     * @return Illuminate\Support\Facades\Response
     *
     */
    public function postUpdateReply()
    {
        try {
            $httpCode = 200;

            // Require authentication
            $this->checkAuth();

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->viewRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            $reviewId = OrbitInput::post('id');
            $review = OrbitInput::post('review');

            $this->registerCustomValidation();

            $validator = Validator::make(
                array(
                    'reviewId' => $reviewId,
                    'review'   => $review
                ),
                array(
                    'reviewId' => 'required|orbit.exist.review',
                    'review'   => 'required'
                ),
                array(
                    'orbit.exist.review' => 'reply not found'
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $mongoConfig = Config::get('database.mongodb');
            $mongoClient = MongoClient::create($mongoConfig);

            $updateDataReview = [
                '_id' => $reviewId,
                'review' => $review,
            ];

            $updateReview = $mongoClient->setFormParam($updateDataReview)
                                        ->setEndPoint('reviews')
                                        ->request('PUT');

            $getReview = $mongoClient->setEndPoint("reviews/$reviewId")->request('GET');

            $this->response->data = $getReview->data;
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
            $this->response->data = null;
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
            $httpCode = 50;
        } catch (Exception $e) {
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);

        return $output;
    }

    protected function registerCustomValidation()
    {
        // Check the existance of review id in mongodb
        Validator::extend('orbit.exist.review', function ($attribute, $value, $parameters) {
            // check string object id valid or not
            if (!preg_match('/^[a-f\d]{24}$/i', $value)) {
                return false;
            }

            $mongoConfig = Config::get('database.mongodb');
            $mongoClient = MongoClient::create($mongoConfig);
            $review = $mongoClient->setEndPoint("reviews/$value")->request('GET');

            if (empty($review->data)) {
                return false;
            }

            App::instance('orbit.exist.review', $review);

            return true;
        });
    }
}