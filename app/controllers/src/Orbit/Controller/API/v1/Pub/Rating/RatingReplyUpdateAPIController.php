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

class RatingReplyUpdateAPIController extends PubControllerAPI
{
    /**
     * POST - Update Rating Reply
     *
     * @param string object_id
     * @param string object_type
     * @param string rating
     * @param string review
     *
     * @return Illuminate\Support\Facades\Response
     *
     * @author Budi <budi@dominopos.com>
     */
    public function postUpdateRatingReply()
    {
        $activity = Activity::mobileci()
                            ->setActivityType('click');
        $user = NULL;
        $coupon = NULL;
        $issuedCoupon = NULL;
        $retailer = null;
        $issued_coupon_code = null;
        $review = OrbitInput::post('review', '');
        $replyId = OrbitInput::post('reply_id', NULL);
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
                    'reply_id' => $replyId,
                    'review' => $review
                ),
                array(
                    'reply_id' => 'required',
                    'review' => 'required|max:1000',
                ),
                array(
                    'max' => 'REVIEW_FAILED_MAX_CHAR_EXCEEDED',
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
            $oldRating = $mongoClient->setEndPoint("reviews/$replyId")->request('GET');

            if (empty($oldRating->data)) {
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

            $body = [
                'review'          => $review,
                'updated_at'      => $dateTime,
                '_id'             => $replyId,
            ];

            $mongoClient = MongoClient::create($mongoConfig)->setFormParam($body);
            $response = $mongoClient->setEndPoint('reviews') // express endpoint
                                    ->request('PUT');

            if ($response->status === 'success') {
                $this->response->message = 'Request Ok';
                $response->data->review = $review;
                $response->data->updated_at = $dateTime;
                $this->response->data = $response->data;
            } else {
                $this->response->message = 'Update reply failed';
                $this->response->data = NULL;
            }

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
