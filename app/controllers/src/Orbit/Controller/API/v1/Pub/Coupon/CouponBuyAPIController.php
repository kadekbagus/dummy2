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
     * POST - For check and reserved the coupon per user
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
            $limitTimeCfg = Config::get('orbit.coupon_reserved_limit_time');

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

            $isUserHavingReservedCoupon = false;
            if (! empty($userIssuedCoupon)) {
                $isUserHavingReservedCoupon = true;
                $userIssuedCoupon->limit_time = date('Y-m-d H:i:s', strtotime("+$limitTimeCfg minutes", strtotime($userIssuedCoupon->issued_date)));
            }

            $coupon = Coupon::where('promotion_id', $coupon_id)->first();

            $availableCoupon = $coupon->available;

            if ($availableCoupon == 0 && ! $isUserHavingReservedCoupon) {
                OrbitShopAPI::throwInvalidArgument('This coupon has been sold out');
            }


            if ($with_reserved === 'N') {

                $response = $userIssuedCoupon;

            } elseif ($with_reserved === 'Y') {

                if ($isUserHavingReservedCoupon) {

                    $response = $userIssuedCoupon;

                } else {

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
                    $availableCoupon = $availableCoupon - 1;
                    $coupon->available = $availableCoupon;
                    $coupon->setUpdatedAt($coupon->freshTimestamp());
                    $coupon->save();

                    // Re sync the coupon data to make sure deleted when coupon sold out
                    if ($availableCoupon > 0) {
                        // Re sync the coupon data
                        Queue::push('Orbit\\Queue\\Elasticsearch\\ESCouponUpdateQueue', [
                            'coupon_id' => $coupon_id
                        ]);
                    } elseif ($availableCoupon == 0) {
                        // Delete the coupon and also suggestion
                        Queue::push('Orbit\\Queue\\Elasticsearch\\ESCouponDeleteQueue', [
                            'coupon_id' => $coupon_id
                        ]);

                        Queue::push('Orbit\\Queue\\Elasticsearch\\ESCouponSuggestionDeleteQueue', [
                            'coupon_id' => $coupon_id
                        ]);
                    }

                    $this->commit();

                    // Register to queue for check payment progress, time will be set configurable
                    $date = Carbon::now()->addMinutes($limitTimeCfg);
                    // Log::info('Send CheckReservedCoupon queue, issued_coupon_id =  '. $issuedCoupon->issued_coupon_id .', will running at = ' . $date);

                    // Queue::later(
                    //     $date,
                    //     'Orbit\\Queue\\Coupon\\CheckReservedCoupon',
                    //     ['coupon_id' => $coupon_id, 'user_id' => $user->user_id]
                    // );

                    $issuedCoupon->limit_time = date('Y-m-d H:i:s', strtotime("+$limitTimeCfg minutes", strtotime($issuedCoupon->issued_date)));

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
