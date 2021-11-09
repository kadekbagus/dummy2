<?php

namespace Orbit\Queue\Order;

use DB;
use Exception;
use Illuminate\Support\Facades\Queue;
use Log;
use Orbit\Helper\Midtrans\API\TransactionStatus;
use Orbit\Notifications\Order\CustomerRefundNotification;
use PaymentTransaction;

/**
 * A job to record refund of given transaction id.
 *
 * @author Budi <budi@dominopos.com>
 */
class RecordRefundQueue
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
        if (! isset($data['retry'])) {
            $data['retry'] = 0;
        }

        try {
            DB::connection()->beginTransaction();

            $this->log("Starting refund record for PaymentID: {$data['paymentId']}");

            $payment = PaymentTransaction::onWriteConnection()->with([
                'user',
                'refunds',
            ])->lockForUpdate()->findOrFail($data['paymentId']);

            if (! $payment->refunded()) {
                $this->log("PaymentID: {$data['paymentId']} is not refunded! Nothing to do.");

                DB::connection()->commit();

                $job->delete();

                return;
            }

            $transactionStatus = TransactionStatus::create()
                ->getStatus($data['paymentId']);

            if ($transactionStatus->wasRefunded()) {
                $refundList = $payment->recordRefund(
                    $transactionStatus->getData()
                );

                if (count($refundList) > 0) {
                    $payment->status = PaymentTransaction::STATUS_SUCCESS_REFUND;
                    $payment->save();
                    DB::connection()->commit();

                    $this->log("PaymentID: {$data['paymentId']} status updated to success_refund!");

                    $refundReason = isset($refundList[0]->reason)
                        ? $refundList[0]->reason
                        : 'Order Cancelled by Customer';

                    $payment->user->notify(
                        new CustomerRefundNotification(
                            $payment,
                            $refundReason
                        )
                    );
                }
            }
            else {
                DB::connection()->commit();
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
                'Orbit\Queue\Order\RecordRefundQueue',
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
}
