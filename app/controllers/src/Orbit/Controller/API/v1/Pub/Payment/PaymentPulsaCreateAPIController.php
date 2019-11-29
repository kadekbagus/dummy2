<?php namespace Orbit\Controller\API\v1\Pub\Payment;

use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use Helper\EloquentRecordCounter as RecordCounter;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use \Config;
use \Exception;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use \DB;
use Validator;
use Pulsa;
use PaymentTransaction;
use PaymentTransactionDetail;
use PaymentTransactionDetailNormalPaypro;
use PaymentMidtrans;
use Mall;
use Activity;
use Country;
use Carbon\Carbon as Carbon;
use Orbit\Helper\Util\CampaignSourceParser;
use Request;
use Log;
use App;
use Discount;

/**
 * Create payment record for Pulsa purchase.
 *
 * Intentionally uses a different class/handler to prevent breaking
 * current implementation.
 *
 * @author Budi <budi@dominopos.com>
 */
class PaymentPulsaCreateAPIController extends PubControllerAPI
{
    private $objectType = 'pulsa';

    public function postPaymentPulsaCreate()
    {
        $httpCode = 200;
        try {
            $this->checkAuth();
            $user = $this->api->user;

            // should always check the role
            $role = $user->role->role_name;
            if (strtolower($role) !== 'consumer') {
                $message = 'You have to login to continue';
                OrbitShopAPI::throwInvalidArgument($message);
            }

            $this->registerCustomValidation();

            $user_id = $user->user_id;
            $first_name = OrbitInput::post('first_name');
            $last_name = OrbitInput::post('last_name');
            $email = OrbitInput::post('email');
            $phone = OrbitInput::post('phone');
            $pulsa_phone = OrbitInput::post('pulsa_phone');
            $country_id = OrbitInput::post('country_id');
            $quantity = OrbitInput::post('quantity');
            $amount = OrbitInput::post('amount');
            $mall_id = OrbitInput::post('mall_id', 'gtm');
            $currency_id = OrbitInput::post('currency_id', '1');
            $currency = OrbitInput::post('currency', 'IDR');
            $post_data = OrbitInput::post('post_data');
            $object_id = OrbitInput::post('object_id');
            $object_type = OrbitInput::post('object_type');
            $object_name = OrbitInput::post('object_name');
            $user_name = (!empty($last_name) ? $first_name.' '.$last_name : $first_name);
            $mallId = OrbitInput::post('mall_id', null);
            $promoCode = OrbitInput::post('promo_code', null);

            $validator = Validator::make(
                array(
                    'first_name' => $first_name,
                    'last_name'  => $last_name,
                    'email'      => $email,
                    'phone'      => $phone,
                    'pulsa_phone'      => $pulsa_phone,
                    'amount'     => $amount,
                    'post_data'  => $post_data,
                    'mall_id'    => $mall_id,
                    'object_id'  => $object_id,
                    'object_type'  => $object_type,
                    'quantity'   => $quantity,
                    'promo_code' => $promoCode,
                ),
                array(
                    'first_name' => 'required',
                    'last_name'  => 'required',
                    'email'      => 'required',
                    'phone'      => 'required',
                    'pulsa_phone'      => 'required',
                    'amount'     => 'required',
                    'post_data'  => 'required',
                    'mall_id'    => 'required',
                    'object_type'  => 'required|in:pulsa,data_plan',
                    'object_id'  => 'required|orbit.exists.pulsa',
                    'quantity'   => 'required|min:1',
                    'promo_code' => 'orbit.reserved.promo',
                ),
                array(
                    'orbit.exists.pulsa' => 'REQUESTED_ITEM_NOT_FOUND',
                    'orbit.reserved.promo' => 'RESERVED_PROMO_CODE_NOT_FOUND',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // Begin database transaction
            $this->beginTransaction();

            // Get pulsa detail from DB.
            $pulsa = Pulsa::select('pulsa_item_id', 'price', 'vendor_price', 'object_type')
                ->where('status', 'active')
                ->where('displayed', 'yes')
                ->findOrFail($object_id);

            $this->objectType = $object_type;

            // Resolve country_id
            if ($country_id !== '0' || $country_id !== 0) {
                $country = Country::where('name', $country_id)->first();
                if (! empty($country)) {
                    $country_id = $country->country_id;
                }
            }

            // Get mall timezone
            $mallTimeZone = 'Asia/Jakarta';
            $mall = null;
            if ($mall_id !== 'gtm') {
                $mall = Mall::where('merchant_id', $mall_id)->first();
                if (!empty($mall)) {
                    $mallTimeZone = $mall->getTimezone($mall_id);
                    $country_id = $mall->country_id;
                }
            }

            // Get utm
            $referer = NULL;
            if (isset($_SERVER['HTTP_REFERER']) && ! empty($_SERVER['HTTP_REFERER'])) {
                $referer = $_SERVER['HTTP_REFERER'];
            }
            $current_url = Request::fullUrl();
            $urlForTracking = [$referer, $current_url];
            $campaignData = CampaignSourceParser::create()
                                ->setUrls($urlForTracking)
                                ->getCampaignSource();

            $payment_new = new PaymentTransaction;
            $payment_new->user_email = $email;
            $payment_new->user_name = $user_name;
            $payment_new->user_id = $user_id;
            $payment_new->phone = $phone;
            $payment_new->country_id = $country_id;
            $payment_new->payment_method = 'midtrans';
            $payment_new->amount = $pulsa->price; // forced to 1 quantity
            $payment_new->currency = $currency;
            $payment_new->status = PaymentTransaction::STATUS_STARTING;
            $payment_new->timezone_name = $mallTimeZone;
            $payment_new->post_data = serialize($post_data);
            $payment_new->extra_data = $this->cleanPhone($pulsa_phone);

            $payment_new->utm_source = $campaignData['campaign_source'];
            $payment_new->utm_medium = $campaignData['campaign_medium'];
            $payment_new->utm_term = $campaignData['campaign_term'];
            $payment_new->utm_content = $campaignData['campaign_content'];
            $payment_new->utm_campaign = $campaignData['campaign_name'];

            $payment_new->save();

            // Insert detail information
            $paymentDetail = new PaymentTransactionDetail;
            $paymentDetail->payment_transaction_id = $payment_new->payment_transaction_id;
            $paymentDetail->currency = $currency;
            $paymentDetail->price = $pulsa->price;
            $paymentDetail->quantity = $quantity;
            $paymentDetail->vendor_price = $pulsa->vendor_price;

            OrbitInput::post('object_id', function($object_id) use ($paymentDetail) {
                $paymentDetail->object_id = $object_id;
            });

            OrbitInput::post('object_type', function($object_type) use ($paymentDetail) {
                $paymentDetail->object_type = $object_type;
            });

            OrbitInput::post('object_name', function($object_name) use ($paymentDetail) {
                $paymentDetail->object_name = $object_name;
            });

            $paymentDetail->save();

            // Insert normal/paypro details
            $paymentDetailNormalPaypro = new PaymentTransactionDetailNormalPaypro;
            $paymentDetail->normal_paypro_detail()->save($paymentDetailNormalPaypro);

            // Insert midtrans info
            $paymentMidtransDetail = new PaymentMidtrans;
            $payment_new->midtrans()->save($paymentMidtransDetail);

            // process discount if any.
            if (! empty($promoCode)) {
                Log::info("Trying to make pulsa purchase with promo code {$promoCode}...");

                // TODO: Move to PromoReservation helper?
                $reservedPromoCodes = $user->discountCodes()->with(['discount'])
                    ->where('discount_code', $promoCode)
                    ->whereNull('payment_transaction_id')
                    ->reserved()
                    ->get();

                $discount = $reservedPromoCodes->count() > 0
                    ? $reservedPromoCodes->first()->discount
                    : Discount::findOrFail($reservedPromoCodes->first()->discount_id);

                $discountRecord = new PaymentTransactionDetail;
                $discountRecord->payment_transaction_id = $payment_new->payment_transaction_id;
                $discountRecord->currency = $payment_new->currency;
                $discountRecord->price = $discount->value_in_percent / 100 * $payment_new->amount * -1.00;
                $discountRecord->quantity = 1;
                $discountRecord->object_id = $discount->discount_id;
                $discountRecord->object_type = 'discount';
                $discountRecord->object_name = $discount->discount_title;
                $discountRecord->save();

                // $reservedPromoCode->status = 'ready_to_issue';
                foreach($reservedPromoCodes as $reservedPromoCode) {
                    $reservedPromoCode->payment_transaction_id = $payment_new->payment_transaction_id;
                    $reservedPromoCode->save();

                    Log::info(sprintf("Promo Code %s (discountCodeId: %s) added to purchase %s",
                        $reservedPromoCode->discount_code,
                        $reservedPromoCode->discount_code_id,
                        $payment_new->payment_transaction_id
                    ));
                }

                $payment_new->amount = $payment_new->amount + $discountRecord->price;
                $payment_new->save();

                // Add additional property to indicate that this purchase is free,
                // so frontend can bypass payment steps.
                if ($discount->value_in_percent === 100) {
                    $payment_new->bypass_payment = true;
                }

                $payment_new->promo_code = $reservedPromoCode->discount_code;
            }

            // Commit the changes
            $this->commit();

            // TODO: Log activity
            $activity = Activity::mobileci()
                    ->setActivityType('transaction')
                    ->setUser($user)
                    ->setActivityName('transaction_status')
                    ->setActivityNameLong('Transaction is Starting')
                    ->setModuleName('Midtrans Transaction')
                    ->setObject($payment_new)
                    ->setNotes($this->objectType)
                    ->setLocation($mall)
                    ->responseOK()
                    ->save();

            $payment_new->quantity = $quantity;

            $this->response->data = $payment_new;
            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Request OK';

        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
            // Rollback the changes
            $this->rollBack();

        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
            // Rollback the changes
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
            // Rollback the changes
            $this->rollBack();

        } catch (Exception $e) {
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 500;
            // Rollback the changes
            $this->rollBack();
        }

        return $this->render($httpCode);
    }

    /**
     * Register custom validation.
     *
     * @return [type] [description]
     */
    private function registerCustomValidation()
    {
        $user = $this->getUser();

        // Check if pulsa is exists.
        Validator::extend('orbit.exists.pulsa', function ($attribute, $pulsaId, $parameters) {
            return Pulsa::where('pulsa_item_id', $pulsaId)->available()->first() !== null;
        });

        // Validator::extend('orbit.active_discount', function($attribute, $promoCode, $paramters) {
        //     // Assume discount is active if promoCode is empty/not exists in the request.
        //     if (empty($promocode)) return true;

        //     // Otherwise, check if discount is still active.
        //     return DiscountCode::whereHas('discount', function($discount) {
        //         $discount->active()->betweenExpiryDate();
        //     })->first() !== null;
        // });

        Validator::extend('orbit.reserved.promo', function($attribute, $promoCode, $parameters) use ($user)
        {
            // Assume true (or reserved) if promocode empty/not exists in the request.
            if (empty($promoCode)) return true;

            // Otherwise, check for reserved status.
            // TODO: Move to PromoReservation helper?
            return $user->discountCodes()
                ->where('discount_code', $promoCode)
                ->whereNull('payment_transaction_id')
                ->reserved()
                ->first() !== null;
        });
    }

    /**
     * Clean characters other than number 0-9.
     * Also replace '62' with '0'.
     *
     * @param  string $phoneNumber [description]
     * @return [type]              [description]
     */
    private function cleanPhone($phoneNumber = '')
    {
        $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
        $phoneNumber = preg_replace('/^[6][2]/', '0', $phoneNumber);

        return $phoneNumber;
    }
}
