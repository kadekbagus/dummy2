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
        $review = OrbitInput::post('review', NULL);
        $ratingId = OrbitInput::post('rating_id', NULL);
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
                $errorMessage = 'Rating ID not found';
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // insert to mongo
            $prefix = DB::getTablePrefix();
            $timestamp = date("Y-m-d H:i:s");
            $date = Carbon::createFromFormat('Y-m-d H:i:s', $timestamp, 'UTC');
            $dateTime = $date->toDateTimeString();

            $location = CampaignLocation::select('merchants.name', 'merchants.country', DB::raw("IF({$prefix}merchants.object_type = 'tenant', oms.city, {$prefix}merchants.city) as city,
                IF({$prefix}merchants.object_type = 'tenant', oms.country_id, {$prefix}merchants.country_id) as country_id"))
                                      ->leftJoin(DB::raw("{$prefix}merchants as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                                      ->where('merchants.merchant_id', '=', $oldRating->data->location_id)
                                      ->first();

            $body = [
                'rating'          => $rating,
                'review'          => $review,
                'status'          => 'active',
                'approval_status' => 'approved',
                'created_at'      => $dateTime,
                'updated_at'      => $dateTime,
                '_id'             => $ratingId,
            ];

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

            $body['merchant_name'] = $location->name;
            $body['country'] = $location->country;
            // Event::fire('orbit.rating.postrating.after.commit', array($this, $body));

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
