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
class RetryTakeVoucher
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
            $data['retries']++;

            Log::info('Request TakeVoucher retry #' . $data['retries'] . '....');

            $payment = PaymentTransaction::where('payment_transaction_id', $data['paymentId'])->first();

            DB::connection()->beginTransaction();
            
            // Take voucher...
            Event::fire('orbit.payment.postupdatepayment.after.save', [$payment, $data['retries']]);

            DB::connection()->commit();

            $payment->load('issued_coupon');

            // Send receipt if necessary...
            Event::fire('orbit.payment.postupdatepayment.after.commit', [$payment]);

            // Bury the job for later inspection
            JobBurier::create($job, function($theJob) {
                // The queue driver does not support bury.
                $theJob->delete();
            })->bury();

        } catch (Exception $e) {
            Log::info('Request TakeVoucher retry exception: %s, %s', $e->getLine(), $e->getMessage());
        }
    }
}
