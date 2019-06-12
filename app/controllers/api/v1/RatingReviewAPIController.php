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
use Log;
use Orbit\Notifications\RatingReview\RatingReviewRejectedNotification;

class RatingReviewAPIController extends ControllerAPI
{
    protected $viewRoles = ['merchant review admin', 'master review admin'];
    /**
     * GET - rating review list for portal
     * @author kadek <kadek@dominopos.com>
     * @author budi <budi@dominopos.com>
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

            $beginDate = OrbitInput::get('start_date', null);
            $endDate = OrbitInput::get('end_date', null);
            $rating = OrbitInput::get('rating', null);
            $review = OrbitInput::get('review', null);
            $type = OrbitInput::get('type', null);
            $is_image_reviewing = OrbitInput::get('is_image_reviewing', null);

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
            $userType = null;
            $linkMerchantId = null;

            // get user type: merchant, mall, or gtm
            $userMerchantReview = User::select('user_merchant_reviews.merchant_id', 'user_merchant_reviews.object_type')
                                     ->join('user_merchant_reviews', 'user_merchant_reviews.user_id', '=', 'users.user_id')
                                     ->where('users.user_id','=', $user->user_id)
                                     ->first();

            if (is_object($userMerchantReview)) {
                $userType = isset($userMerchantReview->object_type) ? $userMerchantReview->object_type : null;
                $linkMerchantId = isset($userMerchantReview->merchant_id) ? $userMerchantReview->merchant_id : null;
            }

            // for user merchant (show only review for that merchant)
            if ($role->role_name === 'Merchant Review Admin' && $userType === 'merchant') {
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
                    'store_ids'    => json_encode($storeIds)
                ];
            }

            // for user mall (show only review for that mall)
            if ($role->role_name === 'Merchant Review Admin' && $userType === 'mall' && !empty($linkMerchantId)) {
                $mallExist = Mall::where('merchant_id', '=', $linkMerchantId)->first();
                $queryString = [
                    'take'         => $take,
                    'skip'         => $skip,
                    'sortBy'       => $sortBy,
                    'sortMode'     => $sortMode,
                    'location_id'  => $linkMerchantId
                ];
            }

            // for user gotomalls (show all review)
            if ($role->role_name === 'Master Review Admin' && $userType === 'gtm') {
                $queryString = [
                    'take'         => $take,
                    'skip'         => $skip,
                    'sortBy'       => $sortBy,
                    'sortMode'     => $sortMode,
                ];
            }

            // filter date
            if (!empty($beginDate) && !empty($endDate)) {
                $queryString['begin_date'] = $beginDate.' 00:00:00';
                $queryString['end_date'] = $endDate.' 23:59:59';
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

            // filter type
            if (!empty($is_image_reviewing)) {
                $queryString['is_image_reviewing'] = $is_image_reviewing;
                $queryString['status'] = 'active';
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
                    $userId = isset($listOfRec->records[$key]->user_id) ? $listOfRec->records[$key]->user_id : null;
                    $objectId = isset($listOfRec->records[$key]->object_id) ? $listOfRec->records[$key]->object_id : null;
                    $objectType = isset($listOfRec->records[$key]->object_type) ? $listOfRec->records[$key]->object_type : null;

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

            if ($role->role_name === 'Merchant Review Admin') {
                if ($userType === 'merchant' && (count($listOfRec->records) === 0 || $emptyStore)) {
                    $data = null;
                } else {
                    $data = new \stdclass();
                    $data->returned_records = $listOfRec->returned_records;
                    $data->total_records = $listOfRec->total_records;
                    $data->records = $listOfRec->records;
                }

                if ($userType === 'mall' && !is_object($mallExist)) {
                    $data = null;
                } else {
                    $data = new \stdclass();
                    $data->returned_records = $listOfRec->returned_records;
                    $data->total_records = $listOfRec->total_records;
                    $data->records = $listOfRec->records;
                }
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
            $this->response->message = $e->getMessage().' '.$e->getLine();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage().' '.$e->getLine();
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
                $this->response->message = $e->getMessage().' '.$e->getLine();
            } else {
                $this->response->message = Lang::get('validation.orbit.queryerror');
            }
            $this->response->data = null;
            $httpCode = 500;
        } catch (Exception $e) {
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage().' '.$e->getLine();
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

    /**
     * test if review has at least one image that has been approved
     * @return boolean true if at least one image that has been approved
     */
    private function hasApprovedImages($review)
    {
        if (! empty($review->images) && is_array($review->images)) {
            Log::info('hasApprovedImages', [$review->images]);
            return count(array_filter(function($img) {
                return ($img[0]->approval_status === 'approved');
            }, $review->images)) > 0;
        }
        return false;
    }

    /**
     * POST - delete review
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string     `id`                       (required) - id of the mongo db review
     *
     * @return Illuminate\Support\Facades\Response
     *
     */
    public function postDeleteReview()
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

            $reviewId = OrbitInput::post('review_id');

            $this->registerCustomValidation();

            $validator = Validator::make(
                array(
                    'review_id' => $reviewId
                ),
                array(
                    'review_id' => 'required|orbit.exist.review'
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
                'status' => 'deleted',
            ];

            $updateReview = $mongoClient->setFormParam($updateDataReview)
                                        ->setEndPoint('reviews')
                                        ->request('PUT');

            $getReview = $mongoClient->setEndPoint("reviews/$reviewId")->request('GET');

            // Send notification to Customer.
            (new RatingReviewRejectedNotification($getReview->data))->send();

            $reviewer = User::where('users.user_id', $getReview->data->user_id)->first();
            $body = [
                'object_id' => $getReview->data->object_id,
                'object_type' => $getReview->data->object_type,
            ];

            $this->response->data = $getReview->data;
            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Request Ok';

            Event::fire('orbit.rating.postrating.reject', [$reviewer, $body]);

            if ($this->hasApprovedImages($getReview->data)) {
                Event::fire('orbit.rating.postrating.rejectimage', [$reviewer, $body]);
            }

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

    /**
     * Delete my reply.
     *
     * @author  budi <budi@dominopos.com>
     *
     * @return [type] [description]
     */
    public function postDeleteReply()
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

            $this->registerCustomValidation();

            $validator = Validator::make(
                array(
                    'reviewId' => $reviewId,
                ),
                array(
                    'reviewId' => 'required|orbit.exist.review',
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

            $reply = $mongoClient->setEndPoint('reviews/' . $reviewId)
                                    ->request('GET');

            $deleteReply = $mongoClient->setEndPoint('reviews/' . $reviewId)
                                        ->request('DELETE');

            $this->response->data = $reply->data;
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
