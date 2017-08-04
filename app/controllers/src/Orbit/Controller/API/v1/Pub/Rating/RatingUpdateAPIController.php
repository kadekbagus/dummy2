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
                    'rating_id' => $ratingId,
                    'rating' => $rating
                ),
                array(
                    'rating' => 'required',
                    'rating_id' => 'required',
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
                'location_id'     => $oldRating->data->location_id,
                'object_id'       => $oldRating->data->object_id,
                'object_type'     => $oldRating->data->object_type,
            ];

            if ($isPromotionalEvent === 'N') {
                $location = CampaignLocation::select('merchants.name', 'merchants.country', DB::raw("IF({$prefix}merchants.object_type = 'tenant', oms.city, {$prefix}merchants.city) as city,
                IF({$prefix}merchants.object_type = 'tenant', oms.country_id, {$prefix}merchants.country_id) as country_id"))
                                      ->leftJoin(DB::raw("{$prefix}merchants as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                                      ->where('merchants.merchant_id', '=', $oldRating->data->location_id)
                                      ->first();

                $body = [
                    'city'            => $location->city,
                    'country_id'      => $location->country_id
                ];
            }

            $mongoClient = MongoClient::create($mongoConfig)->setFormParam($body);
            $response = $mongoClient->setEndPoint('reviews') // express endpoint
                                    ->request('PUT');

            if ($response->status === 'success') {
                $this->response->message = 'Request Ok';
                $this->response->data = NULL;
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
