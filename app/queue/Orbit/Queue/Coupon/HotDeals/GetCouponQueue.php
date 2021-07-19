<?php namespace Orbit\Queue\Coupon\HotDeals;

use DB;
use Log;
use Event;
use Queue;
use Config;
use Exception;
use Carbon\Carbon;

use User;
use PaymentTransaction;
use IssuedCoupon;
use Coupon;
use Mall;
use Activity;

// Notifications
use Orbit\Notifications\Coupon\CouponNotAvailableNotification;
use Orbit\Notifications\Coupon\HotDeals\ReceiptNotification;
use Orbit\Notifications\Coupon\HotDeals\CouponNotAvailableNotification as HotDealsCouponNotAvailableNotification;

use Orbit\Helper\GoogleMeasurementProtocol\Client as GMP;
use App;
use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts\ReservationInterface;

/**
 * A job to get/issue Hot Deals Coupon after payment completed.
 * At this point, we assume the payment was completed (paid) so anything wrong
 * while trying to issue the coupon will make the status success_no_coupon_failed.
 *
 * @author Budi <budi@dominopos.com>
 */
class GetCouponQueue
{
    /**
     * Issue hot deals coupon.
     *
     * @param  Illuminate\Queue\Jobs\Job | Orbit\FakeJob $job  the job
     * @param  array $data the data needed to run this job
     * @return void
     */
    public function fire($job, $data)
    {
        $adminEmails = Config::get('orbit.transaction.notify_emails', ['developer@dominopos.com']);
        $mallId = isset($data['mall_id']) ? $data['mall_id'] : null;
        $mall = Mall::where('merchant_id', $mallId)->first();
        $payment = null;
        $discount = null;

        $activity = Activity::mobileci()
                            ->setActivityType('transaction')
                            ->setActivityName('transaction_status')
                            ->setCurrentUrl($data['current_url']);

        try {
            DB::connection()->beginTransaction();

            $paymentId = $data['paymentId'];

            Log::info("PaidCoupon: Getting coupon PaymentID: {$paymentId}");

            $payment = PaymentTransaction::with(['details.coupon', 'user', 'midtrans', 'issued_coupons', 'discount_code'])->findOrFail($paymentId);

            $activity->setUser($payment->user);

            // Dont issue coupon if after some delay the payment was canceled.
            if ($payment->denied() || $payment->failed() || $payment->expired()) {

                Log::info("PaidCoupon: Payment {$paymentId} was denied/canceled. We should not issue any coupon.");

                $payment->cleanUp();

                $payment->resetDiscount();

                DB::connection()->commit();

                $job->delete();

                return;
            }

            // It means we can not get related issued coupon.
            if ($payment->issued_coupons->count() === 0) {
                throw new Exception("Related IssuedCoupon not found. Might be put to stock again by system queue before customer completes the payment.", 1);
            }

            // If coupon already issued/redeemed...
            $issuedCouponCount = 0;
            foreach($payment->issued_coupons as $issuedCoupon) {
                if ($issuedCoupon->status === IssuedCoupon::STATUS_RESERVED) {
                    // Issue the coupon...
                    $issuedCoupon->issued_date = Carbon::now('UTC');
                    $issuedCoupon->status      = IssuedCoupon::STATUS_ISSUED;

                    $issuedCoupon->save();

                    $issuedCouponCount++;

                    Log::info("PaidCoupon: IssuedCoupon {$issuedCoupon->issued_coupon_id} issued for payment {$paymentId}... ({$issuedCouponCount})");
                }
                else {
                    Log::info("PaidCoupon: IssuedCoupon {$issuedCoupon->issued_coupon_id} status is {$issuedCoupon->status}. Nothing to do.");
                }
            }

            if ($issuedCouponCount > 0) {
                $payment->status = PaymentTransaction::STATUS_SUCCESS;
                $payment->save();

                $coupon = $this->getCoupon($payment);
                $discount = $payment->discount_code;
                $coupon->updateAvailability();

                if (! empty($discount)) {
                    $discountCode = $discount->discount_code;
                    $promoCodeReservation = App::make(ReservationInterface::class);
                    $promoData = (object) [
                        'promo_code' => $discountCode,
                        'object_id' => $coupon->promotion_id,
                        'object_type' => 'coupon'
                    ];
                    $promoCodeReservation->markAsIssued($payment->user, $promoData);
                    Log::info("PaidCoupon: Promo code {$discountCode} issued for payment {$paymentId}...");
                }

                // Commit the changes ASAP.
                DB::connection()->commit();

                Log::info("PaidCoupon: {$issuedCouponCount} coupon issued for payment {$paymentId}..");

                // Notify Customer.
                $payment->user->notify(new ReceiptNotification($payment));

                GMP::create(Config::get('orbit.partners_api.google_measurement'))
                    ->setQueryString([
                        'cid' => time(),
                        't' => 'event',
                        'ea' => 'Purchase Coupon Successful',
                        'ec' => 'Coupon',
                        'el' => $coupon->promotion_name,
                        'cs' => $payment->utm_source,
                        'cm' => $payment->utm_medium,
                        'cn' => $payment->utm_campaign,
                        'ck' => $payment->utm_term,
                        'cc' => $payment->utm_content
                    ])
                    ->request();

                // @todo: need to have google analytics transaction and item hit

                // Log Activity
                $activity->setActivityNameLong('Transaction is Successful')
                        ->setModuleName('Midtrans Transaction')
                        ->setObject($payment)
                        ->setCoupon($coupon)
                        ->setNotes(Coupon::TYPE_HOT_DEALS)
                        ->setLocation($mall)
                        ->responseOK()
                        ->save();

                Activity::mobileci()
                        ->setUser($payment->user)
                        ->setActivityType('click')
                        ->setActivityName('coupon_added_to_wallet')
                        ->setActivityNameLong('Coupon Added To Wallet')
                        ->setModuleName('Coupon')
                        ->setObject($coupon)
                        ->setNotes(Coupon::TYPE_HOT_DEALS)
                        ->setLocation($mall)
                        ->responseOK()
                        ->save();

                $rewardObject = (object) [
                    'object_id' => $coupon->promotion_id,
                    'object_type' => 'coupon',
                    'object_name' => $coupon->promotion_name,
                    'country_id' => $payment->country_id,
                ];

                Event::fire('orbit.purchase.coupon.success', [$payment->user, $rewardObject]);

            }
            else {
                // Commit the changes ASAP.
                DB::connection()->rollBack();

                Log::info("PaidCoupon: NO COUPON ISSUED for payment {$paymentId} !");
            }

        } catch (Exception $e) {

            // Mark as failed if we get any exception.
            if (! empty($payment)) {
                $payment->status = PaymentTransaction::STATUS_SUCCESS_NO_COUPON_FAILED;
                $payment->save();

                if (! empty($discount)) {
                    $discountCode = $discount->discount_code;
                    // Mark promo code as available if purchase was failed.
                    $promoCodeReservation = App::make(ReservationInterface::class);
                    $promoData = (object) [
                        'promo_code' => $discountCode,
                        'object_id' => $coupon->promotion_id,
                        'object_type' => 'coupon'
                    ];
                    $promoCodeReservation->markAsAvailable($payment->user, $promoData);
                    Log::info("PaidCoupon: Promo code {$discountCode} reverted back/marked as available...");
                }

                DB::connection()->commit();

                // Notify admin for this failure.
                foreach($adminEmails as $email) {
                    $admin              = new User;
                    $admin->email       = $email;
                    $admin->notify(new CouponNotAvailableNotification($payment, $e->getMessage()));
                }

                // Notify customer that coupon is not available.
                $payment->user->notify(new HotDealsCouponNotAvailableNotification($payment));

                $objectName = $payment->details->first()->object_name;
                GMP::create(Config::get('orbit.partners_api.google_measurement'))
                    ->setQueryString([
                        'cid' => time(),
                        't' => 'event',
                        'ea' => 'Purchase Coupon Failed',
                        'ec' => 'Coupon',
                        'el' => $objectName,
                        'cs' => $payment->utm_source,
                        'cm' => $payment->utm_medium,
                        'cn' => $payment->utm_campaign,
                        'ck' => $payment->utm_term,
                        'cc' => $payment->utm_content
                    ])
                    ->request();

                $activity->setActivityNameLong('Transaction is Success - Failed Getting Coupon')
                         ->setModuleName('Midtrans Transaction')
                         ->setObject($payment)
                         ->setNotes($e->getMessage())
                         ->setLocation($mall)
                         ->responseFailed()
                         ->save();

                 Activity::mobileci()
                         ->setUser($payment->user)
                         ->setActivityType('click')
                         ->setActivityName('coupon_added_to_wallet')
                         ->setActivityNameLong('Coupon Added to Wallet Failed')
                         ->setModuleName('Coupon')
                         ->setObject($payment->details->first()->coupon)
                         ->setNotes($e->getMessage())
                         ->setLocation($mall)
                         ->responseFailed()
                         ->save();
            }
            else {
                DB::connection()->rollBack();
            }

            Log::info(sprintf('PaidCoupon: Get HotDeals Coupon exception: %s:%s, %s', $e->getFile(), $e->getLine(), $e->getMessage()));
            Log::info('PaidCoupon: Data: ' . serialize($data));
        }

        $job->delete();
    }

    private function getCoupon($payment)
    {
        $coupon = null;
        foreach($payment->details as $detail) {
            if (! empty($detail->coupon)) {
                $coupon = $detail->coupon;
                break;
            }
        }

        return $coupon;
    }
}
