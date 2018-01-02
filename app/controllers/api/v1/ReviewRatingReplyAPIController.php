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

use Orbit\Helper\Util\PaginationNumber;

class ReviewRatingReplyAPIController extends ControllerAPI
{
    protected $viewRoles = ['merchant review admin', 'master review admin'];

    /**
     * POST - Reply to a Review/Rating
     * 
     * @author budi <budi@dominopos.com>
     *
     * @param string        location_id         parent/main review 
     *
     * @return Illuminate\Support\Facades\Response
     *
     */
    public function postReplyReviewRating()
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
            
            // Set logged in user as review admin for testing purpose
            // Should be removed when committing to remote, and use auth lines above.
            // $user = User::where('user_id', 'K_QTs_LOVMF0NSWR')->first();

            $objectId = OrbitInput::post('object_id', NULL);
            $objectType = OrbitInput::post('object_type', NULL);
            $locationId = OrbitInput::post('location_id', NULL);
            $rating = 0;
            $review = OrbitInput::post('review', NULL);
            $status = OrbitInput::post('status', 'active');
            $approvalStatus = OrbitInput::post('approval_status', 'approved');

            // For Reply
            $parentId = OrbitInput::post('parent_id', NULL);
            $userIdReplied = OrbitInput::post('user_id_replied', NULL);
            $reviewIdReplied = OrbitInput::post('review_id_replied', NULL);

            $validatorColumn = array(
                                'object_id'   => $objectId,
                                'object_type' => $objectType,
                                'rating'      => $rating,
                                'location_id' => $locationId,
                                'parent_id'   => $parentId,
                                'user_id_replied' => $userIdReplied,
                                'review_id_replied' => $reviewIdReplied,
                            );

            $validation = array(
                            'object_id'   => 'required',
                            'object_type' => 'required',
                            'rating'      => 'required',
                            'location_id' => 'required',
                            'parent_id'   => 'required',
                            'user_id_replied' => 'required',
                            'review_id_replied' => 'required',
                        );

            // check if object_id is promotional event, no nedd to check location_id
            $isPromotionalEvent = 'N';
            if ($objectType === 'news') {
                $news = News::where('news_id', $objectId)->first();
                $isPromotionalEvent = $news->is_having_reward;

                if ($isPromotionalEvent === 'Y') {
                    unset($validatorColumn['location_id']);
                    unset($validation['location_id']);
                }
            }

            $validator = Validator::make($validatorColumn, $validation);

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $prefix = DB::getTablePrefix();
            $timestamp = date("Y-m-d H:i:s");
            $date = Carbon::createFromFormat('Y-m-d H:i:s', $timestamp, 'UTC');
            $dateTime = $date->toDateTimeString();

            $body = [
                'object_id'       => $objectId,
                'object_type'     => $objectType,
                'user_id'         => $user->user_id,
                'rating'          => $rating,
                'review'          => $review,
                'status'          => $status,
                'approval_status' => $approvalStatus,
                'created_at'      => $dateTime,
                'updated_at'      => $dateTime,
                'is_reply'        => 'y',
                'parent_id'       => $parentId,
                'user_id_replied' => $userIdReplied,
                'review_id_replied' => $reviewIdReplied,
            ];

            $bodyLocation = array();
            if ($isPromotionalEvent === 'N') {
                $location = CampaignLocation::select('merchants.name', 'merchants.country', 'merchants.object_type', DB::raw("IF({$prefix}merchants.object_type = 'tenant', oms.merchant_id, {$prefix}merchants.merchant_id) as location_id, IF({$prefix}merchants.object_type = 'tenant', {$prefix}merchants.name, '') as store_name, IF({$prefix}merchants.object_type = 'tenant', oms.name, {$prefix}merchants.name) as mall_name, IF({$prefix}merchants.object_type = 'tenant', oms.city, {$prefix}merchants.city) as city, IF({$prefix}merchants.object_type = 'tenant', oms.country_id, {$prefix}merchants.country_id) as country_id"))
                                          ->leftJoin(DB::raw("{$prefix}merchants as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                                          ->where('merchants.merchant_id', '=', $locationId)
                                          ->first();

                if (! empty($location)) {
                    $bodyLocation = [
                        'location_id'     => $location->location_id,
                        'store_id'        => $locationId,
                        'store_name'      => $location->store_name,
                        'mall_name'       => $location->mall_name,
                        'city'            => $location->city,
                        'country_id'      => $location->country_id,
                    ];
                }
            }

            $mongoConfig = Config::get('database.mongodb');
            $mongoClient = MongoClient::create($mongoConfig);

            // Reply...
            $response = $mongoClient->setFormParam($body + $bodyLocation)
                                    ->setEndPoint('reviews') // express endpoint
                                    ->request('POST');

            $this->response->data = $response->data;
            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Request Ok';
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.mall.getsearchmallcountry.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = 0;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.mall.getsearchmallcountry.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.mall.getsearchmallcountry.query.error', array($this, $e));

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
            Event::fire('orbit.mall.getsearchmallcountry.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        // Event::fire('orbit.mall.getsearchmallcountry.before.render', array($this, &$output));

        return $output;
    }
}
