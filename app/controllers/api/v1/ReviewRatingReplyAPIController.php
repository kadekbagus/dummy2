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

            // // Try to check access control list, does this user allowed to
            // // perform this action
            $user = $this->api->user;

            // // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->viewRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            // Set logged in user as review admin for testing purpose
            // Should be removed when committing to remote, and use auth lines above.
            // $user = User::where('user_id', 'LZBVROXoxP0tlX5Y')->first();

            $rating = 0;
            $review = OrbitInput::post('review', NULL);
            $status = 'active';
            $approvalStatus = 'approved';

            // For Reply
            $parentId = OrbitInput::post('parent_id', NULL);
            $userIdReplied = OrbitInput::post('user_id_replied', NULL);
            $reviewIdReplied = OrbitInput::post('review_id_replied', NULL);

            $validatorColumn = array(
                                'review' => $review,
                                'parent_id'   => $parentId,
                                'user_id_replied' => $userIdReplied,
                                'review_id_replied' => $reviewIdReplied,
                            );

            $validation = array(
                            'review' => 'required',
                            'parent_id'   => 'required',
                            'user_id_replied' => 'required',
                            'review_id_replied' => 'required',
                        );

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

            $mongoConfig = Config::get('database.mongodb');
            $mongoClient = MongoClient::create($mongoConfig);

            // Get parent review.
            $mainReview = $mongoClient->setEndPoint('reviews/' . $parentId)->request('GET');

            if (! is_object($mainReview)) {
                $errorMessage = 'Main Review (' . $parentId . ') not found.';
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $objectId = $mainReview->data->object_id;
            $objectType = $mainReview->data->object_type;

            $body['object_id'] = $objectId;
            $body['object_type'] = $objectType;

            // check if object_id is promotional event, no nedd to check location_id
            $isPromotionalEvent = 'N';
            if ($objectType === 'news') {
                $news = News::where('news_id', $objectId)->first();
                $isPromotionalEvent = $news->is_having_reward;
            }

            if ($isPromotionalEvent === 'N') {
                $bodyLocation = [
                    'location_id'     => $mainReview->data->location_id,
                    'store_id'        => $mainReview->data->store_id,
                    'store_name'      => isset($mainReview->data->store_name) ? $mainReview->data->store_name : '',
                    'mall_name'       => $mainReview->data->mall_name,
                    'city'            => $mainReview->data->city,
                    'country_id'      => $mainReview->data->country_id,
                ];
            }

            // Reply...
            $response = $mongoClient->setFormParam($body + $bodyLocation)
                                    ->setEndPoint('reviews') // express endpoint
                                    ->request('POST');

            $response->data->user_name = $user->user_firstname . ' ' . $user->user_lastname;

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
