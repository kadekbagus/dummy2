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
use DominoPOS\OrbitUploader\Uploader as OrbitUploader;
use Carbon\Carbon as Carbon;
use Orbit\Helper\MongoDB\Client as MongoClient;

class ReviewRatingImageApprovalAPIController extends ControllerAPI
{
    protected $viewRoles = ['merchant review admin', 'master review admin'];

    /**
     * POST - Review image of rating review
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * @param string        location_id         parent/main review
     *
     * @return Illuminate\Support\Facades\Response
     *
     */
    public function postImageApproval()
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
            $imagesIds = OrbitInput::post('image_ids');
            $approvalType = OrbitInput::post('approval_type'); // reject/ pending
            $rejectedMessage = OrbitInput::post('rejection_message');

            $this->registerCustomValidation();
            $validator = Validator::make(
                array(
                    'review_id' => $reviewId,
                    'image_ids' => $imagesIds,
                    'approval_type' => $approvalType,
                ),
                array(
                    // 'review_id' => 'required',
                    'review_id' => 'required|orbit.exist.review',
                    'image_ids' => 'required',
                    'approval_type' => 'required|in:pending,approved,rejected',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $mongoConfig = Config::get('database.mongodb');
            $mongoClient = MongoClient::create($mongoConfig);

            $getReview = $mongoClient->setEndPoint("reviews/$reviewId")->request('GET');

            $newImages = '';
            $is_image_reviewing = 'y';

            // Update status image, or deleted when rejected
            foreach ($getReview->data->images as $key => $images) {

                $status = $images[0]->approval_status;
                if (in_array($images[0]->media_id, $imagesIds)) {
                    $status = $approvalType;
                }

                if ($status == 'rejected') {
                    // delete from db
                    $deleteMedia = Event::fire('orbit.rating.postdeletemedia', array($this, $images));
                    // $is_image_reviewing = 'y';

                } else {
                    foreach ($images as $keyVar => $image) {
                        $newImages[$key][$keyVar]['media_id'] = $image->media_id ;
                        $newImages[$key][$keyVar]['variant_name'] = $image->variant_name ;
                        $newImages[$key][$keyVar]['url'] = $image->url ;
                        $newImages[$key][$keyVar]['cdn_url'] = '' ;
                        $newImages[$key][$keyVar]['metadata'] = $image->metadata;
                        $newImages[$key][$keyVar]['approval_status'] = $status;
                        $newImages[$key][$keyVar]['rejection_message'] = '';
                    }

                    if ($status == 'pending') {
                        $is_image_reviewing = 'n';
                    }
                }
            }

            $updateDataReview = [
                '_id' => $reviewId,
                'images' => $newImages,
                'status' => 'active',
                'is_image_reviewing' => $is_image_reviewing,
                'approval_by' => $user->user_id,

            ];

            $updateReview = $mongoClient->setFormParam($updateDataReview)
                                        ->setEndPoint('reviews')
                                        ->request('PUT');

            $getReview = $mongoClient->setEndPoint("reviews/$reviewId")->request('GET');

            // Send email
            $userReview = User::where('user_id', $getReview->data->user_id)->first();
            $urlDetail = Config::get('app.url') .'/'. $getReview->data->object_type .'/'. $getReview->data->object_id . '/' . $getReview->data->object_type;

            $subject = 'Your review image(s) has been approved';
            if ($approvalType == 'rejected') {
                $subject = 'Your review image(s) has been rejected';
            }

            Queue::push('Orbit\\Queue\\ReviewImageApprovalMailQueue', [
                'subject' => $subject,
                'reject_reason' => $rejectedMessage,
                'approval_type' => $approvalType,
                'review_id' => $getReview->data->_id,
                'fullname' => $userReview->user_firstname .' '. $userReview->user_lastname,
                'email' => $userReview->user_email,
                'object_id' => $getReview->data->object_id,
                'object_type' => $getReview->data->object_type,
                'review' => isset($getReview->data->review) ? $getReview->data->review : null,
                'url_detail' => $urlDetail,
                'store_name' => isset($getReview->data->store_name) ? $getReview->data->store_name : '',
                'mall_name' => isset($getReview->data->mall_name) ? $getReview->data->mall_name : '',
            ]);

            $this->response->data = $getReview->data;
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
