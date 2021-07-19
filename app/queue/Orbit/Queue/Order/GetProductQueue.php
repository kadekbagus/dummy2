<?php namespace Orbit\Queue\Order;

use App;
use Config;
use DB;
use Event;
use Exception;
use Log;
use Mall;
use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts\ReservationInterface;
use Orbit\Controller\API\v1\Pub\Purchase\Activities\PurchaseFailedProductActivity;
use Orbit\Controller\API\v1\Pub\Purchase\Activities\PurchaseSuccessActivity;
use Orbit\Helper\AutoIssueCoupon\AutoIssueCoupon;
use Orbit\Helper\Cart\CartInterface;
use Orbit\Helper\GoogleMeasurementProtocol\Client as GMP;
use Orbit\Notifications\Order\CustomerDigitalProductNotAvailableNotification;
use Orbit\Notifications\Order\DigitalProductNotAvailableNotification;
use Orbit\Notifications\Order\ReceiptNotification;
use Order;
use PaymentTransaction;
use User;

/**
 * A job to get/issue Digital Product after payment completed.
 *
 * @author Budi <budi@dominopos.com>
 */
class GetProductQueue
{
    /**
     * Delay before we trigger another MCash Purchase (in minutes).
     * @var integer
     */
    protected $retryDelay = 3;

    private $objectType = 'order';

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

        if (! isset($data['retry'])) {
            $data['retry'] = 0;
        }

        try {
            DB::connection()->beginTransaction();

            $paymentId = $data['paymentId'];

            $this->log("Getting item for PaymentID: {$paymentId}");

            $payment = PaymentTransaction::onWriteConnection()->with([
                'details.order',
                'user',
                'midtrans',
                'discount_code'
            ])->lockForUpdate()->findOrFail($paymentId);

            // Dont issue coupon if after some delay the payment was canceled.
            if ($payment->denied() || $payment->failed() || $payment->expired() || $payment->canceled()
                || $payment->status === PaymentTransaction::STATUS_SUCCESS_NO_PRODUCT_FAILED
                || $payment->status === PaymentTransaction::STATUS_SUCCESS_REFUND
                || $payment->status === PaymentTransaction::STATUS_SUCCESS
            ) {
                $this->log("Payment {$paymentId} was already success/denied/canceled/failed/refunded. We should not issue any item.");

                DB::connection()->commit();

                $job->delete();

                return;
            }


            $payment->status = PaymentTransaction::STATUS_SUCCESS;
            $payment->save();

            $orderPaymentDetails = $payment->details->filter(function($detail) {
                return $detail->object_type === 'order';
            });

            $orderIds = $orderPaymentDetails->lists('object_id');

            // should store cart item ids in order details instead?
            $cartItemIds = $orderPaymentDetails->implode('payload', ',');

            Order::markAsPaid($orderIds);

            App::make(CartInterface::class)->removeItem($cartItemIds);

            // Commit the changes ASAP.
            DB::connection()->commit();

            $this->log("Issued for payment {$paymentId}..");

            // Auto issue free coupon if trx meet certain criteria.
            // AutoIssueCoupon::issue($payment, $digitalProduct->product_type);

            // Notify Customer.
            // $payment->user->notify(new ReceiptNotification(
            //     $payment,
            //     $purchase->getVoucherData()
            // ));

            // $this->recordSuccessGMP();

            $payment->user->activity(new PurchaseSuccessActivity($payment, $this->objectType));

            // if (! empty($discount)) {
            //     // Mark promo code as issued.
            //     $promoCodeReservation = App::make(ReservationInterface::class);
            //     $promoData = (object) [
            //         'promo_code' => $discount->discount_code,
            //         'object_id' => $product,
            //         'object_type' => 'order'
            //     ];
            //     $promoCodeReservation->markAsIssued($payment->user, $promoData);
            //     $this->log("Promo code {$discount->discount_code} issued for purchase {$paymentId}");
            // }

            // Increase point when the transaction is success.
            // if (in_array($payment->status, [PaymentTransaction::STATUS_SUCCESS])) {
            //     $rewardObject = (object) [
            //         'object_id' => $digitalProductId,
            //         'object_type' => 'brand_product',
            //         'object_name' => $digitalProduct->product_name,
            //         'country_id' => $payment->country_id,
            //     ];

            //     Event::fire('orbit.purchase.pulsa.success', [$payment->user, $rewardObject]);
            // }

        } catch (Exception $e) {

            // Mark as failed if we get any exception.
            if (! empty($payment)) {
                $payment->status = PaymentTransaction::STATUS_SUCCESS_NO_PRODUCT_FAILED;
                $payment->save();

                // if (! empty($discount) && ! empty($digitalProduct)) {
                //     // Mark promo code as available.
                //     $discountCode = $discount->discount_code;
                //     $promoCodeReservation = App::make(ReservationInterface::class);
                //     $promoData = (object) [
                //         'promo_code' => $discountCode,
                //         'object_id' => $digitalProductId,
                //         'object_type' => 'digital_product'
                //     ];
                //     $promoCodeReservation->markAsAvailable($payment->user, $promoData);
                //     $this->log("Promo code {$discountCode} reverted back/marked as available...");
                // }

                DB::connection()->commit();

                // Notify admin for this failure.
                foreach($adminEmails as $email) {
                    // $admin              = new User;
                    // $admin->email       = $email;
                    // $admin->notify(new DigitalProductNotAvailableNotification($payment, $e->getMessage()));
                }

                // Notify customer that coupon is not available.
                // $payment->user->notify(new CustomerDigitalProductNotAvailableNotification($payment));

                $notes = $e->getMessage();

                // $digitalProductName = ! empty($digitalProduct) ? $digitalProduct->product_name : '-';

                // $this->recordFailedGMP();

                $payment->user->activity(new PurchaseFailedProductActivity($payment, $this->objectType, $notes));
            }
            else {
                DB::connection()->rollBack();
            }

            $this->log(sprintf("Get {$this->objectType} exception: %s:%s, %s", $e->getFile(), $e->getLine(), $e->getMessage()));
            $this->log(serialize($data));
        }

        $job->delete();
    }

    private function recordSuccessGMP()
    {
        $cid = time();
        // send google analitics event hit
        GMP::create(Config::get('orbit.partners_api.google_measurement'))
            ->setQueryString([
                'cid' => $cid,
                't' => 'event',
                'ea' => 'Purchase Digital Product Successful',
                'ec' => 'game_voucher',
                'el' => $digitalProductName,
                'cs' => $payment->utm_source,
                'cm' => $payment->utm_medium,
                'cn' => $payment->utm_campaign,
                'ck' => $payment->utm_term,
                'cc' => $payment->utm_content
            ])
            ->request();

        if (! is_null($detail) && ! is_null($digitalProduct)) {
            // send google analitics transaction hit
            GMP::create(Config::get('orbit.partners_api.google_measurement'))
                ->setQueryString([
                    'cid' => $cid,
                    't' => 'transaction',
                    'ti' => $payment->payment_transaction_id,
                    'tr' => $payment->amount,
                    'cu' => $payment->currency,
                ])
                ->request();

            // send google analitics item hit
            GMP::create(Config::get('orbit.partners_api.google_measurement'))
                ->setQueryString([
                    'cid' => $cid,
                    't' => 'item',
                    'ti' => $payment->payment_transaction_id,
                    'in' => $digitalProductName,
                    'ip' => $detail->price,
                    'iq' => $detail->quantity,
                    'ic' => $productCode,
                    'iv' => 'game_voucher',
                    'cu' => $payment->currency,
                ])
                ->request();
        }
    }

    private function recordFailedGMP()
    {
        GMP::create(Config::get('orbit.partners_api.google_measurement'))
            ->setQueryString([
                'cid' => time(),
                't' => 'event',
                'ea' => 'Purchase Digital Product Failed',
                'ec' => 'game_voucher',
                'el' => $digitalProductName,
                'cs' => $payment->utm_source,
                'cm' => $payment->utm_medium,
                'cn' => $payment->utm_campaign,
                'ck' => $payment->utm_term,
                'cc' => $payment->utm_content
            ])
            ->request();
    }

    /**
     * Log to file with specific objectType prefix.
     *
     * @param  [type] $message [description]
     * @return [type]          [description]
     */
    private function log($message)
    {
        Log::info("{$this->objectType}: {$message}");
    }
}
