<?php namespace Orbit\Controller\API\v1\Pub\Rating;

use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Config;
use stdClass;
use DB;
use Validator;
use Orbit\Helper\Net\SessionPreparer;
use Lang;
use \Exception;
use Activity;
use Orbit\Helper\Security\Encrypter;
use \Orbit\Helper\Exception\OrbitCustomException;
use CampaignLocation;
use Carbon\Carbon as Carbon;
use Orbit\Helper\MongoDB\Client as MongoClient;
use Event;
use News;
use Queue;

class RatingNewAPIController extends PubControllerAPI
{
    private $mongoClient = null;

    /**
     * POST - New Rating Review and Reply the Review
     *
     * @param string object_id
     * @param string object_type
     * @param string rating
     * @param string review
     *
     * @return Illuminate\Support\Facades\Response
     *
     * @author shelgi <shelgi@dominopos.com>
     * @author firmansyah <firmansyah@dominopos.com>
     */
    public function postNewRating()
    {
        $activity = Activity::mobileci()
                            ->setActivityType('click');
        $user = NULL;
        $coupon = NULL;
        $issuedCoupon = NULL;
        $retailer = null;
        $issued_coupon_code = null;
        $objectId = OrbitInput::post('object_id', NULL);
        $objectType = OrbitInput::post('object_type', NULL);
        $locationId = OrbitInput::post('location_id', NULL);
        $rating = OrbitInput::post('rating', NULL);
        $review = OrbitInput::post('review', NULL);
        $status = OrbitInput::post('status', 'active');
        $approvalStatus = OrbitInput::post('approval_status', 'approved');
        $mongoConfig = Config::get('database.mongodb');

        // For Reply
        $isReply = OrbitInput::post('is_reply', false);
        $parentId = OrbitInput::post('parent_id', NULL);
        $userIdReplied = OrbitInput::post('user_id_replied', NULL);
        $reviewIdReplied = OrbitInput::post('review_id_replied', NULL);

        // Cdn
        $prefix = DB::getTablePrefix();
        $usingCdn = Config::get('orbit.cdn.enable_cdn', FALSE);
        $defaultUrlPrefix = Config::get('orbit.cdn.providers.default.url_prefix', '');
        $urlPrefix = ($defaultUrlPrefix != '') ? $defaultUrlPrefix . '/' : '';

        if ($isReply) {
            //No rating when reply, so need overide the rating
            $rating = 0;
        }

        try {
            $user = $this->getUser();

            $session = SessionPreparer::prepareSession();

            // should always check the role
            $role = $user->role->role_name;
            if (strtolower($role) !== 'consumer') {
                $message = 'You must login to access this.';
                ACL::throwAccessForbidden($message);
            }

            $this->registerCustomValidations();

            $validatorColumn = array(
                                'review'      => str_replace(["\r", "\n"], '', $review),
                                'object_id'   => $objectId,
                                'object_type' => $objectType,
                                'rating'      => $rating,
                                'location_id' => $locationId,
                            );

            $validation = array(
                            'review'      => 'max:1000',
                            'object_id'   => 'required|orbit.rating.unique',
                            'object_type' => 'required',
                            'rating'      => 'required',
                            'location_id' => 'required',
                        );

            // Add validation rule for reply review
            if ($isReply) {
                $validatorColumn['parent_id']  = $parentId;
                $validatorColumn['user_id_replied']  = $userIdReplied;
                $validatorColumn['review_id_replied']  = $reviewIdReplied;

                $validation['parent_id'] = 'required';
                $validation['user_id_replied'] = 'required';
                $validation['review_id_replied'] = 'required';

                unset($validatorColumn['location_id']);
                unset($validation['location_id']);
            }

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

            $validator = Validator::make($validatorColumn, $validation, [
                'max' => 'REVIEW_FAILED_MAX_CHAR_EXCEEDED',
            ]);

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            if ($user->status === 'pending') {
                $errorMessage = 'Activate your account';
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // insert to mongo
            $prefix = DB::getTablePrefix();
            $timestamp = date("Y-m-d H:i:s");
            $date = Carbon::createFromFormat('Y-m-d H:i:s', $timestamp, 'UTC');
            $dateTime = $date->toDateTimeString();

            $body = [
                'object_id'          => $objectId,
                'object_type'        => $objectType,
                'user_id'            => $user->user_id,
                'rating'             => $rating,
                'review'             => $review,
                'status'             => $status,
                'approval_status'    => $approvalStatus,
                'created_at'         => $dateTime,
                'updated_at'         => $dateTime,
                'is_reply'           => 'n',
            ];

            // Adding new parameter for reply
            if ($isReply) {
                $body['is_reply'] = 'y';
                $body['parent_id'] = $parentId;
                $body['user_id_replied'] = $userIdReplied;
                $body['review_id_replied'] = $reviewIdReplied;
            }

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

            // upload image
            $uploadMedias = Event::fire('orbit.rating.postnewmedia', array($this, $body));

            $images = array();

            if (count($uploadMedias[0]) > 0) {
                foreach ($uploadMedias[0] as $key => $medias) {
                    foreach ($medias->variants as $keyVar => $variant) {
                        $images[$key][$keyVar]['media_id'] = $variant->media_id ;
                        $images[$key][$keyVar]['variant_name'] = $variant->media_name_long ;
                        $images[$key][$keyVar]['url'] = $urlPrefix . $variant->path ;
                        $images[$key][$keyVar]['cdn_url'] = '' ;
                        $images[$key][$keyVar]['metadata'] = $variant->metadata;
                        $images[$key][$keyVar]['approval_status'] = 'pending';
                        $images[$key][$keyVar]['rejection_message'] = '';
                    }
                }
            }

            if (! empty($images)) {
                $body['images'] = $images;
                $body['is_image_reviewing'] = 'n';

                //send email to admin
                Queue::push('Orbit\\Queue\\ReviewImageNeedApprovalMailQueue', [
                    'subject' => 'There is a review with image(s) that needs your approval',
                    'object_id' => $objectId,
                    'user_email' => $user->user_email,
                    'user_fullname' => $user->user_firstname .' '. $user->user_lastname,
                ]);
            }

            $mongoClient = MongoClient::create($mongoConfig)->setFormParam($body + $bodyLocation);
            $response = $mongoClient->setEndPoint('reviews') // express endpoint
                                    ->request('POST');

            if ($response->status === 'success') {
                $this->response->message = 'Request Ok';
                $this->response->data = $response->data;
            } else {
                $this->response->message = 'Add rating failed';
                $this->response->data = NULL;
            }

            if ($isPromotionalEvent === 'N' && ! empty($location)) {
                $body['merchant_name'] = $location->name;
                $body['country'] = $location->country;
            }
            Event::fire('orbit.rating.postrating.after.commit', array($this, $body, $user));

        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;
            $this->rollback();
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;
        } catch (\Orbit\Helper\Exception\OrbitCustomException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;

        } catch (Exception $e) {
            $this->response->code = $e->getCode();
            $this->response->status = $e->getLine();
            $this->response->message = $e->getMessage();
            $this->response->data = $e->getFile();
        }

        return $this->render();
    }

    private function registerCustomValidations()
    {
        // Validate that current rating is unique to specific location.
        Validator::extend(
            'orbit.rating.unique',
            function($attr, $objectId, $params, $validator) {
                $validatorData = $validator->getData();
                $user = $this->getUser();

                // If reply, skip the validation (assume unique).
                if (isset($validatorData['is_reply'])) {
                    return true;
                }

                $locationId = isset($validatorData['location_id'])
                    ? $validatorData['location_id'] : null;

                if (empty($locationId)) {
                    return false;
                }

                $mongoUniqueParam = [
                    'user_id' => $user->user_id,
                    'object_id' => $objectId,
                    'store_id' => $locationId,
                    'status' => 'active',
                ];

                $duplicateRating = $this->mongo()
                    ->setQueryString($mongoUniqueParam)
                    ->setEndPoint('reviews')
                    ->request('GET');

                // Mark validation as true (unique) if no record found for given
                // user, object and location.
                // Otherwise, always assume it is false (not unique/duplicate).
                if (isset($duplicateRating->data)
                    && ! empty($duplicateRating->data)
                    && $duplicateRating->data->returned_records === 0
                ) {
                    return true;
                }

                return false;
            }
        );
    }

    private function mongo()
    {
        if (empty($this->mongoClient)) {
            $mongoConfig = Config::get('database.mongodb');
            $this->mongoClient = MongoClient::create($mongoConfig);
        }

        return $this->mongoClient;
    }
}
