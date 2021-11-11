<?php

namespace Orbit\Queue\Order;

use Config;
use DB;
use Exception;
use Illuminate\Support\Facades\Queue;
use Log;
use Orbit\Helper\Midtrans\API\Refund;
use Orbit\Notifications\Order\CustomerRefundNotification;
use PaymentTransaction;

/**
 * A job to auto-cancel order if admin didn't process within the time limit.
 *
 * @author Budi <budi@dominopos.com>
 */
class AutoCancelOrderQueue
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

            $this->log("Starting auto-cancel for order: {$data['orderId']}");

            $order = Order::onWriteConnection()
                ->lockForUpdate()
                ->findOrFail($data['orderId']);

            if ($order->status === Order::STATUS_CANCELLING) {
                Order::cancel($data['orderId']);
                $this->log("Order: {$order->order_id} cancelled by system.");
            }
            else {
                $this->log("Order: {$order->order_id} status is {$order->status}. Nothing to do.");
            }

            DB::connection()->commit();

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
                'Orbit\Queue\Order\AutoCancelOrderQueue',
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
