<?php namespace Orbit\Queue\Order;

use Config;
use DB;
use Exception;
use Illuminate\Support\Facades\Queue;
use Log;
use Mall;
use Orbit\Helper\GoogleMeasurementProtocol\Client as GMP;
use Orbit\Helper\Midtrans\API\Refund;
use Orbit\Notifications\Order\CustomerRefundNotification;
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
     * Delay before we trigger another refund request (in minutes).
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
        $refundReason = 'Order cancelled by Customer';

        if (! isset($data['retry'])) {
            $data['retry'] = 0;
        }

        if (! isset($data['refundKey'])) {
            $data['refundKey'] = $data['paymentId'];
        }

        if (isset($data['reason'])) {
            $refundReason = $data['reason'];
        }

        try {
            DB::connection()->beginTransaction();

            $this->log("Preparing refund for PaymentID: {$data['paymentId']}");

            $payment = PaymentTransaction::onWriteConnection()->with([
                'user',
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
                'reason' => $refundReason,
            ];

            $refund = Refund::create()->direct($data['paymentId'], $refundParams);

            if ($refund->isSuccess()) {
                // Set payment status and send refund notification email right away.
                // This is useful when Midtrans Notifications is late/not sending at all.
                $payment->status = PaymentTransaction::STATUS_SUCCESS_REFUND;
                $payment->save();
                DB::connection()->commit();

                $payment->user->notify(
                    new CustomerRefundNotification(
                        $payment,
                        $refundReason
                    )
                );

                $this->log("PaymentID: {$data['paymentId']} refunded!");

                $this->recordRefundData($payment);
            }
            else {
                DB::connection()->commit();
                $this->retry($data);
            }

        } catch (Exception $e) {

            $this->retry($data);

            DB::connection()->rollBack();

            $this->log(sprintf(
                "Get %s exception: %s:%s, %s",
                $this->objectType,
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
            Queue::later(
                $this->retryDelay * 60,
                'Orbit\Queue\Order\RefundOrderQueue',
                $data
            );
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

    /**
     * Record refund data in a separate queue/http request
     * since it is not trivial for customer.
     *
     * @param  [type] $payment [description]
     * @return [type]          [description]
     */
    private function recordRefundData($payment)
    {
        Queue::push('Orbit\Queue\Order\RecordRefundQueue', [
            'paymentId' => $payment->payment_transaction_id,
        ]);
    }
}
