<?php namespace Orbit\Controller\API\v1\Pub\Coupon;

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

            $coupon_id = OrbitInput::get('coupon_id');
            $with_reserved = OrbitInput::get('with_reserved', 'N');

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
                                            ->where('status', IssuedCoupon::STATUS_ISSUED)
                                            ->first();

            if (! empty($userIssuedCoupon)) {
                OrbitShopAPI::throwInvalidArgument('This user already issued this coupon, you cannot get twice coupon before you redeem the coupon');
            } else {
                $response = $userIssuedCoupon;
            }

            $availableCoupon = Coupon::where('promotion_id', $coupon_id)
                                ->first();

            if ($availableCoupon->available == 0) {
                OrbitShopAPI::throwInvalidArgument('This coupon has been sold out');
            }


            if ($with_reserved === 'Y') {

                $this->beginTransaction();

                if (empty($userIssuedCoupon)) {
                    // Insert to oeb_issued_coupon for reserve the coupon
                    $issuedCoupon = new IssuedCoupon;
                    $issuedCoupon->promotion_id             = $coupon_id;
                    $issuedCoupon->user_id                  = $user->user_id;
                    $issuedCoupon->user_email               = $user->user_email;
                    $issuedCoupon->issued_date              = date('Y-m-d H:i:s');
                    $issuedCoupon->status                   = IssuedCoupon::STATUS_ISSUED;
                    $issuedCoupon->record_exists            = 'Y';
                    $issuedCoupon->save();

                    // Update available coupon -1
                    $availableCoupon->available = $availableCoupon->available - 1;
                    $availableCoupon->setUpdatedAt($availableCoupon->freshTimestamp());
                    $availableCoupon->save();

                    // Re sync the coupon data to make sure deleted when coupon sold out
                    if ($availableCoupon->available > 0) {
                        // Re sync the coupon data
                        Queue::push('Orbit\\Queue\\Elasticsearch\\ESCouponUpdateQueue', [
                            'coupon_id' => $coupon_id
                        ]);
                    } elseif ($availableCoupon->available == 0) {
                        // Delete the coupon and also suggestion
                        Queue::push('Orbit\\Queue\\Elasticsearch\\ESCouponDeleteQueue', [
                            'coupon_id' => $coupon->promotion_id
                        ]);

                        Queue::push('Orbit\\Queue\\Elasticsearch\\ESCouponSuggestionDeleteQueue', [
                            'coupon_id' => $coupon->promotion_id
                        ]);
                    }

                    // Register to queue for check payment progress, time will be set configurable
                    // $delay = Config::get('orbit.partners_api.midtrans.transaction_status_timeout', 10);
                    $date = Carbon::now()->addMinutes(15);
                    Queue::later(
                        $date,
                        'Orbit\\Queue\\Coupon\\CheckReservedCoupon',
                        ['coupon_id' => $coupon_id, 'user_id' => $user->user_id]
                    );

                    // Return the data
                    $response = $issuedCoupon;
                }
            }

            $this->commit();

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
