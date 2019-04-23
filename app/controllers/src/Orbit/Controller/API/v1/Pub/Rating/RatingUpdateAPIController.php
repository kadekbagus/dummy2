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

class RatingUpdateAPIController extends PubControllerAPI
{
    /**
     * POST - Update Rating
     *
     * @param string object_id
     * @param string object_type
     * @param string rating
     * @param string review
     *
     * @return Illuminate\Support\Facades\Response
     *
     * @author shelgi <shelgi@dominopos.com>
     */
    public function postUpdateRating()
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
        $review = OrbitInput::post('review', '');
        $ratingId = OrbitInput::post('rating_id', NULL);
        $status = OrbitInput::post('status', 'active');
        $approvalStatus = OrbitInput::post('approval_status', 'approved');
        $mongoConfig = Config::get('database.mongodb');

        try {
            $user = $this->getUser();

            $session = SessionPreparer::prepareSession();

            // should always check the role
            $role = $user->role->role_name;
            if (strtolower($role) !== 'consumer') {
                $message = 'You must login to access this.';
                ACL::throwAccessForbidden($message);
            }

            $validator = Validator::make(
                array(
                    'review'    => $review,
                    'rating_id' => $ratingId,
                    'rating'    => $rating
                ),
                array(
                    'review'    => 'max:1000',
                    'rating'    => 'required',
                    'rating_id' => 'required', //TODO validate image limitation (might be in landing page).
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            if ($user->status === 'pending') {
                $errorMessage = 'Activate your account';
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // get review by id
            $mongoClient = MongoClient::create($mongoConfig);
            $oldRating = $mongoClient->setEndPoint("reviews/$ratingId")->request('GET');

            if (empty($oldRating)) {
                $errorMessage = 'Rating or Review ID not found';
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            if ($user->user_id != $oldRating->data->user_id) {
                $errorMessage = 'Different user_id, cannot update Rating/Review';
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // insert to mongo
            $prefix = DB::getTablePrefix();
            $timestamp = date("Y-m-d H:i:s");
            $date = Carbon::createFromFormat('Y-m-d H:i:s', $timestamp, 'UTC');
            $dateTime = $date->toDateTimeString();

            // cdn
            $usingCdn = Config::get('orbit.cdn.enable_cdn', FALSE);
            $defaultUrlPrefix = Config::get('orbit.cdn.providers.default.url_prefix', '');
            $urlPrefix = ($defaultUrlPrefix != '') ? $defaultUrlPrefix . '/' : '';

            // check if object_id is promotional event, no nedd to check location_id
            $isPromotionalEvent = 'N';
            if ($oldRating->data->object_type === 'news') {
                $news = News::where('news_id', $oldRating->data->object_id)->first();
                $isPromotionalEvent = $news->is_having_reward;
            }

            $body = [
                'rating'          => $rating,
                'review'          => $review,
                'status'          => $status,
                'approval_status' => $approvalStatus,
                'updated_at'      => $dateTime,
                '_id'             => $ratingId,
                'object_id'       => $oldRating->data->object_id,
                'object_type'     => $oldRating->data->object_type,
            ];

            $bodyLocation = array();
            if ($isPromotionalEvent === 'N') {
                $location = CampaignLocation::select('merchants.name', 'merchants.country', DB::raw("IF({$prefix}merchants.object_type = 'tenant', oms.city, {$prefix}merchants.city) as city,
                IF({$prefix}merchants.object_type = 'tenant', oms.country_id, {$prefix}merchants.country_id) as country_id"))
                                      ->leftJoin(DB::raw("{$prefix}merchants as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                                      ->where('merchants.merchant_id', '=', $oldRating->data->location_id)
                                      ->first();

                $bodyLocation = [
                    'location_id'     => $oldRating->data->location_id,
                    'city'            => $location->city,
                    'country_id'      => $location->country_id
                ];
            }

            // upload image
            $uploadMedias = Event::fire('orbit.rating.postnewmedia', array($this, $body));

            $newImages = [];

            if (count($uploadMedias[0]) > 0) {
                // Get old images...
                $getReview = $mongoClient->setEndPoint("reviews/$ratingId")->request('GET');

                $key = 0;
                foreach ($getReview->data->images as $key => $images) {
                    foreach ($images as $keyVar => $image) {
                        $newImages[$key][$keyVar]['media_id'] = $image->media_id;
                        $newImages[$key][$keyVar]['variant_name'] = $image->variant_name;
                        $newImages[$key][$keyVar]['url'] = $image->url;
                        $newImages[$key][$keyVar]['cdn_url'] = $image->cdn_url;
                        $newImages[$key][$keyVar]['metadata'] = $image->metadata;
                        $newImages[$key][$keyVar]['approval_status'] = $image->approval_status;
                        $newImages[$key][$keyVar]['rejection_message'] = $image->rejection_message;
                    }
                }

                // And then append them with new images...
                foreach ($uploadMedias[0] as $medias) {
                    $key++;
                    foreach ($medias->variants as $keyVar => $variant) {
                        $newImages[$key][$keyVar]['media_id'] = $variant->media_id;
                        $newImages[$key][$keyVar]['variant_name'] = $variant->media_name_long;
                        $newImages[$key][$keyVar]['url'] = $urlPrefix . $variant->path;
                        $newImages[$key][$keyVar]['cdn_url'] = '';
                        $newImages[$key][$keyVar]['metadata'] = $variant->metadata;
                        $newImages[$key][$keyVar]['approval_status'] = 'pending';
                        $newImages[$key][$keyVar]['rejection_message'] = '';
                    }
                }
            }

            if (! empty($newImages)) {
                $body['images'] = $newImages;
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
                                    ->request('PUT');

            if ($response->status === 'success') {
                $this->response->message = 'Request Ok';
                $this->response->data = $response->data;
            } else {
                $this->response->message = 'Add rating failed';
                $this->response->data = NULL;
            }

            if ($isPromotionalEvent === 'N') {
                $body['merchant_name'] = $location->name;
                $body['country'] = $location->country;
            }
            Event::fire('orbit.rating.postrating.after.commit', array($this, $body));

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
}
