<?php namespace Orbit\Controller\API\v1\Pub;

use OrbitShop\API\v1\ControllerAPI;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use Validator;
use Config;
use Exception;
use IssuedCoupon;
use Coupon;
use Activity;
use Log;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Response;
use stdclass;

/**
 * Sepulsa Redeem Callback Controller
 */
class SepulsaRedeemCallbackController extends ControllerAPI
{
    protected $responseCode;

    public function validate()
    {
        $this->responseCode = 200;
        $customResponse = new stdclass();
        $customResponse->status = false;
        $customResponse->action = 'redeem-voucher';
        $customResponse->result = new stdclass();

        try {
            if (strtolower(Request::header('Content-Type')) !== 'application/json') {
                $this->responseCode = 415;
                throw new Exception("Unsupported Media Type.", 1);
            }
            $status = Input::get('status');
            $result = Input::get('result');

            $this->registerCustomValidation();
            $validator = Validator::make(
                array(
                    'status' => $status,
                    'result' => $result,
                ),
                array(
                    'status' => 'required',
                    'result' => 'required|array|result_check',
                ),
                array(
                    'result_check' => 'result data is not complete.'
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $this->responseCode = 400;
                $errorMessage = $validator->messages()->first();
                throw new Exception($errorMessage, 1);
            }

            // get the issued coupon
            $issuedCoupon = IssuedCoupon::with(['user', 'coupon'])->leftJoin('promotions', 'promotions.promotion_id', '=', 'issued_coupons.promotion_id')
                ->leftJoin('coupon_sepulsa', 'coupon_sepulsa.promotion_id', '=', 'promotions.promotion_id')
                ->where('issued_coupons.status', IssuedCoupon::STATUS_ISSUED)
                ->where('issued_coupon_code', $result['code'])
                ->where('issued_coupons.redeem_verification_code', $result['id'])
                ->where('coupon_sepulsa.token', $result['token'])
                ->first();

            if (is_object($issuedCoupon)) {
                $issuedCoupon->redeemed_date = date('Y-m-d H:i:s');
                $issuedCoupon->status = IssuedCoupon::STATUS_REDEEMED;
                $issuedCoupon->save();
                Log::error(sprintf('>> SEPULSA REDEEM OK FOR CODE: %s; TOKEN: %s', $result['code'], $result['token']));

                $customResponse->status = true;
                $customResponse->result->id = $result['id'];
                $customResponse->result->token = $result['token'];
                $customResponse->result->code = $result['code'];
                $customResponse->result->delivered_date = date('Y-m-d H:i:s');

                Activity::mobileci()
                            ->setUser($issuedCoupon->user)
                            ->setActivityType('coupon')
                            ->setActivityName('redeem_coupon')
                            ->setActivityNameLong('Coupon Redemption (Successful)')
                            ->setObject($issuedCoupon->coupon)
                            ->setNotes(Coupon::TYPE_SEPULSA)
                            ->setModuleName('Coupon')
                            ->responseOK()
                            ->save();
            } else {
                $this->responseCode = 400;
                Log::error('>> SEPULSA REDEEM FAILED: Issued coupon is not found');
                $customResponse->message = 'Issued coupon is not found';

                Activity::mobileci()
                            ->setActivityType('coupon')
                            ->setActivityName('redeem_coupon')
                            ->setActivityNameLong('Coupon Redemption (Failed)')
                            ->setNotes("Specific issued_coupon not found! issuedCouponCode: {$result['code']} - redeemVerificationCode: {$result['id']} - token: {$result['token']}")
                            ->setModuleName('Coupon')
                            ->responseFailed()
                            ->save();
            }
        } catch (Exception $e) {
            $this->responseCode = $this->responseCode === 200 ? 500 : $this->responseCode;
            $customResponse->message = $e->getMessage();
            Log::error(sprintf('>> SEPULSA REDEEM FAILED: %s, in %s:%s', $e->getMessage(), $e->getFile(), $e->getLine()));

            $user = isset($issuedCoupon) && ! empty($issuedCoupon->user) ? $issuedCoupon->user : null;

            Activity::mobileci()
                        ->setUser($user)
                        ->setActivityType('coupon')
                        ->setActivityName('redeem_coupon')
                        ->setActivityNameLong('Coupon Redemption (Failed)')
                        ->setNotes("Exception: {$e->getMessage()}")
                        ->setModuleName('Coupon')
                        ->responseFailed()
                        ->save();
        }

        return Response::json($customResponse, $this->responseCode);
    }

    /**
     * @return boolean
     * @throws Exception
     */
    private function registerCustomValidation()
    {
        Validator::extend('result_check', function ($attribute, $value, $parameters) {
            if (! isset($value['id']) || empty($value['id'])) {
                return FALSE;
            }
            if (! isset($value['token']) || empty($value['token'])) {
                return FALSE;
            }
            if (! isset($value['code']) || empty($value['code'])) {
                return FALSE;
            }

            return TRUE;
        });
    }
}
