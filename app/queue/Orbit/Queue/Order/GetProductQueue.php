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
// use Orbit\Notifications\Order\CustomerDigitalProductNotAvailableNotification;
// use Orbit\Notifications\Order\DigitalProductNotAvailableNotification;
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
            Order::markAsPaid($orderIds);

            // should store cart item ids in order details instead?
            $cartItemIds = $orderPaymentDetails->implode('payload', ',');
            App::make(CartInterface::class)->removeItem($cartItemIds);

            // Commit the changes ASAP.
            DB::connection()->commit();

            $this->log("Order for payment {$paymentId} ..");

            // Send receipt to customer.
            $payment->user->notify(new ReceiptNotification($payment), 5);

            // Notify admin/store user for new order.
            (new NewOrderNotification($payment))->send();

            $payment->user->activity(new PurchaseSuccessActivity($payment, $this->objectType));

        } catch (Exception $e) {

            // Mark as failed if we get any exception.
            if (! empty($payment)) {
                $payment->status = PaymentTransaction::STATUS_SUCCESS_NO_PRODUCT_FAILED;
                $payment->save();

                DB::connection()->commit();

                $notes = $e->getMessage();

                $payment->user->activity(new PurchaseFailedProductActivity($payment, $this->objectType, $notes));
            }
            else {
                DB::connection()->rollBack();
            }

            $this->log(sprintf(
                "Get {$this->objectType} exception: %s:%s, %s",
                $e->getFile(),
                $e->getLine(),
                $e->getMessage()
            ));

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
