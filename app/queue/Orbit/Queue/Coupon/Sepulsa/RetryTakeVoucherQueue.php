<?php namespace Orbit\Queue\Coupon\Sepulsa;

use DB;
use Log;
use Event;
use Queue;
use Config;
use Exception;
use Carbon\Carbon;
use Orbit\FakeJob;
use Orbit\Helper\Util\JobBurier;

use PaymentTransaction;

/**
 * Queue to retry TakeVoucher request to Sepulsa.
 *
 * @author Budi <budi@dominopos.com>
 */
class RetryTakeVoucherQueue
{
    /**
     * It is basically re-fire the paymentupdate events after some delay...
     * No need to do any magic here.
     * 
     * @param  [type] $job  [description]
     * @param  [type] $data [description]
     * @return [type]       [description]
     */
    public function fire($job, $data)
    {
        try {
            $retries = $data['retries'];
            $retries++;

            Log::info('Request TakeVoucher retry #' . $retries . ' ...');

            DB::connection()->beginTransaction();

            $payment = PaymentTransaction::with(['coupon', 'coupon_sepulsa', 'issued_coupon', 'user'])
                                            ->where('payment_transaction_id', $data['paymentId'])->first();
            
            // Take voucher...
            Event::fire('orbit.payment.postupdatepayment.after.save', [$payment, $retries]);

            DB::connection()->commit();

            $payment->load('issued_coupon');

            // Send receipt if necessary...
            Event::fire('orbit.payment.postupdatepayment.after.commit', [$payment]);

            $job->delete();

            // Bury the job for later inspection
            // JobBurier::create($job, function($theJob) {
            //     // The queue driver does not support bury.
            //     $theJob->delete();
            // })->bury();

        } catch (Exception $e) {
            DB::connection()->rollback();
            Log::info(sprintf('Request TakeVoucher retry exception: %s:%s, %s', $e->getFile(), $e->getLine(), $e->getMessage()));
        }
    }
}
