<?php namespace Orbit\Controller\API\v1\Pub\Coupon;

use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use \Orbit\Helper\Exception\OrbitCustomException;
use Illuminate\Database\QueryException;
use Config;
use stdClass;
use DB;
use Validator;
use Activity;
use Mall;
use Lang;
use \Exception;
use Orbit\Controller\API\v1\Pub\Coupon\CouponHelper;
use Coupon;
use IssuedCoupon;
use \Queue;
use Carbon\Carbon as Carbon;
use Log;

class CouponBuyAPIController extends PubControllerAPI
{
    /**
     * GET - get all coupon wallet in all mall
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string coupon_id
     * @param string with_reserved
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postCouponBuy()
    {
        $httpCode = 200;
        $activity = Activity::mobileci()->setActivityType('view');
        $user = NULL;
        $response = NULL;

        try {
            $user = $this->getUser();

            // should always check the role
            $role = $user->role->role_name;
            if (strtolower($role) !== 'consumer') {
                $message = 'You have to login to continue';
                OrbitShopAPI::throwInvalidArgument($message);
            }

            $coupon_id = OrbitInput::post('coupon_id');
            $with_reserved = OrbitInput::post('with_reserved', 'N');

            $couponHelper = CouponHelper::create();
            $couponHelper->couponCustomValidator();
            $validator = Validator::make(
                array(
                    'coupon_id' => $coupon_id,
                    'with_reserved' => $with_reserved,
                ),
                array(
                    'coupon_id' => 'required|orbit.exists.coupon',
                    'with_reserved' => 'required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // Check the user already have coupon or not
            $userIssuedCoupon = IssuedCoupon::where('user_id', $user->user_id)
                                            ->where('promotion_id', $coupon_id)
                                            ->where('status', IssuedCoupon::STATUS_RESERVED)
                                            ->first();

            if (! empty($userIssuedCoupon)) {
                OrbitShopAPI::throwInvalidArgument('This user already issued this coupon, you cannot get twice coupon before you redeem the coupon');
            } else {
                $response = $userIssuedCoupon;
            }

            $coupon = Coupon::where('promotion_id', $coupon_id)
                                ->first();

            if ($coupon->available == 0) {
                OrbitShopAPI::throwInvalidArgument('This coupon has been sold out');
            }


            if ($with_reserved === 'Y') {


                if (empty($userIssuedCoupon)) {
                    $this->beginTransaction();

                    //insert for sepulsa and update for hot_deals
                    if ($coupon->promotion_type === 'sepulsa') {

                        $issuedCoupon = new IssuedCoupon;
                        $issuedCoupon->promotion_id  = $coupon_id;
                        $issuedCoupon->user_id       = $user->user_id;
                        $issuedCoupon->user_email    = $user->user_email;
                        $issuedCoupon->issued_date   = date('Y-m-d H:i:s');
                        $issuedCoupon->status        = IssuedCoupon::STATUS_RESERVED;
                        $issuedCoupon->record_exists = 'Y';
                        $issuedCoupon->save();

                    } elseif ($coupon->promotion_type === 'hot_deals') {

                        $issuedCoupon = IssuedCoupon::where('promotion_id', $coupon_id)
                                                        ->where('user_id', NULL)
                                                        ->where('user_email', NULL)
                                                        ->first();

                        $issuedCoupon->user_id     = $user->user_id;
                        $issuedCoupon->user_email  = $user->user_email;
                        $issuedCoupon->issued_date = date('Y-m-d H:i:s');
                        $issuedCoupon->status      = IssuedCoupon::STATUS_RESERVED;
                        $issuedCoupon->save();

                    }

                    // Update available coupon -1
                    $coupon->available = $coupon->available - 1;
                    $coupon->setUpdatedAt($coupon->freshTimestamp());
                    $coupon->save();

                    // Re sync the coupon data to make sure deleted when coupon sold out
                    if ($coupon->available > 0) {
                        // Re sync the coupon data
                        Queue::push('Orbit\\Queue\\Elasticsearch\\ESCouponUpdateQueue', [
                            'coupon_id' => $coupon_id
                        ]);
                    } elseif ($coupon->available == 0) {
                        // Delete the coupon and also suggestion
                        Queue::push('Orbit\\Queue\\Elasticsearch\\ESCouponDeleteQueue', [
                            'coupon_id' => $coupon_id
                        ]);

                        Queue::push('Orbit\\Queue\\Elasticsearch\\ESCouponSuggestionDeleteQueue', [
                            'coupon_id' => $coupon_id
                        ]);

                        // To Do : Delete all coupon cache
                        /* if (Config::get('orbit.cache.ng_redis_enabled', FALSE)) {
                            $redis = Cache::getRedis();
                            $keyName = array('coupon','home');
                            foreach ($keyName as $value) {
                                $keys = $redis->keys("*$value*");
                                if (! empty($keys)) {
                                    foreach ($keys as $key) {
                                        $redis->del($key);
                                    }
                                }
                            }
                        } */

                    }

                    $this->commit();

                    // Register to queue for check payment progress, time will be set configurable
                    $date = Carbon::now()->addMinutes(10);
                    Log::info(' ======= Send queue for check reserved issued_coupon_id =  '. $issuedCoupon->issued_coupon_id .', will running at = ' . $date . ' ========');

                    Queue::later(
                        $date,
                        'Orbit\\Queue\\Coupon\\CheckReservedCoupon',
                        ['coupon_id' => $coupon_id, 'user_id' => $user->user_id]
                    );

                    $issuedCoupon->limit_time = date('Y-m-d H:i:s', strtotime("+10 minutes", strtotime($issuedCoupon->issued_date)));

                    // Return the data
                    $response = $issuedCoupon;
                }

            }


            $this->response->data = $response;

        } catch (ACLForbiddenException $e) {

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
            $this->rollBack();
        } catch (InvalidArgsException $e) {

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
            $this->rollBack();
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
            $this->rollBack();
        } catch (Exception $e) {

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 500;
            $this->rollBack();
        }

        $output = $this->render($httpCode);

        return $output;
    }
}
