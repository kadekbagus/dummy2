<?php namespace Orbit\Queue\Order;

use App;
use Config;
use DB;
use Event;
use Exception;
use Illuminate\Support\Facades\Queue;
use Log;
use Mall;
use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts\ReservationInterface;
use Orbit\Controller\API\v1\Pub\Purchase\Activities\PurchaseFailedProductActivity;
use Orbit\Controller\API\v1\Pub\Purchase\Activities\PurchaseSuccessActivity;
use Orbit\Helper\AutoIssueCoupon\AutoIssueCoupon;
use Orbit\Helper\Cart\CartInterface;
use Orbit\Helper\GoogleMeasurementProtocol\Client as GMP;
use Orbit\Helper\Midtrans\API\Refund;
use Orbit\Notifications\Order\Admin\NewOrderNotification;
use Orbit\Notifications\Order\ReceiptNotification;
use Order;
use PaymentTransaction;
use User;

/**
 * A job to process refund of given transaction id.
 *
 * @author Budi <budi@dominopos.com>
 */
class RefundOrderQueue
{
    /**
     * Delay before we trigger another MCash Purchase (in minutes).
     * @var integer
     */
    protected $retryDelay = 0.1;

    private $objectType = 'order';

    protected $maxRetry = 3;

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
        // $mallId = isset($data['mall_id']) ? $data['mall_id'] : null;
        // $mall = Mall::where('merchant_id', $mallId)->first();
        $payment = null;

        if (! isset($data['retry'])) {
            $data['retry'] = 0;
        }

        if (! isset($data['refundKey'])) {
            $data['refundKey'] = $data['paymentId'];
        }

        try {
            DB::connection()->beginTransaction();

            $this->log("Preparing refund for PaymentID: {$data['paymentId']}");

            $payment = PaymentTransaction::onWriteConnection()->with([
                'details.order',
                'user',
                'midtrans',
                'discount_code'
            ])->lockForUpdate()->findOrFail($data['paymentId']);

            if ($payment->pending() || $payment->denied() || $payment->failed()
                || $payment->expired() || $payment->canceled()
                || $payment->refunded()
            ) {
                $this->log("PaymentID: {$data['paymentId']} is pending or failed/cancelled/refunded! Nothing to do.");

                DB::connection()->commit();

                $job->delete();

                return;
            }

            $refundParams = [
                'refund_key' => $data['refundKey'],
                'reason' => 'Order cancelled by Customer',
            ];

            $refund = Refund::create()->direct($data['paymentId'], $refundParams);

            if ($refund->isSuccess()) {
                $this->log("PaymentID: {$data['paymentId']} refunded!");
            }
            else {
                $this->retry($data);
            }

            DB::connection()->commit();

        } catch (Exception $e) {

            $this->retry($data);

            DB::connection()->rollBack();

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

    private function retry($data)
    {
        if ($data['retry'] < $this->maxRetry) {
            $data['retry']++;
            Queue::later($this->retryDelay * 60, 'Orbit\Queue\Order\RefundOrderQueue', $data);
        }
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
