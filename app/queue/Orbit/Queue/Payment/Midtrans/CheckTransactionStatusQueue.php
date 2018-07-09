<?php namespace Orbit\Queue\Payment\Midtrans;

use DB;
use Log;
use Queue;
use Config;
use Exception;
use Event;
use Orbit\Helper\Util\JobBurier;

use PaymentTransaction;

use Orbit\Helper\Midtrans\API\TransactionStatus;

/**
 * Get transaction status from Midtrans and update our internal payment status if necessary.
 * 
 * @author Budi <budi@dominopos.com>
 */
class CheckTransactionStatusQueue
{
    /**
     * $data should have 2 items:
     * 1. transactionId -- the transaction ID we want to check. Should be equivalent with external_payment_transaction_id.
     * 2. check -- counter to track the number of check status API we've been calling for this transactionId.
     * 
     * @param  [type] $job  [description]
     * @param  [type] $data [description]
     * @return [type]       [description]
     */
    public function fire($job, $data)
    {
        $transaction = null;

        try {

            DB::connection()->beginTransaction();

            $payment = PaymentTransaction::with(['coupon', 'issued_coupon'])
                                                ->where('payment_transaction_id', $data['transactionId'])->first();

            if (empty($payment)) {
                // If no transaction found, so we should not do/schedule any check.
                throw new Exception('Transaction ' . $data['transactionId'] . ' not found!');
            }

            // If payment completed or expired then do nothing.
            // (It maybe completed by notification callback/ping from Midtrans)
            if ($payment->completed()) {
                Log::info('Midtrans::CheckTransactionStatusQueue: Transaction ID ' . $data['transactionId'] . ' completed. Nothing to do.');

                return;
            }
            else if ($payment->expired() || $payment->failed() || $payment->denied()) {
                
                $payment->cleanUp();

                DB::connection()->commit();

                return;
            }

            Log::info('Midtrans::CheckTransactionStatusQueue: Checking transaction status... ' . $data['transactionId']);

            $data['check']++;

            $midtransTransactionId = $payment->external_payment_transaction_id;
            $transaction = TransactionStatus::create()->getStatus($midtransTransactionId);

            // Record Midtrans' response Code & Message.
            $payment->provider_response_code = $transaction->getCode();
            $payment->provider_response_message = $transaction->getMessage();

            $payment->save();

            // Re-run this job if the status is still pending (and not reached maximum try yet)
            if ($transaction->shouldRetryChecking($data['check'])) {

                // Commit the provider response changes in DB.
                DB::connection()->commit();

                $this->retryChecking($data);
            }
            else {
                // If we get here, it's either transaction is success, expired, error, or reached maximum check allowed,
                // thus no need to do/schedule checking anymore.

                // Set the internal payment status based on transaction status from Midtrans.
                // @todo Should we assume the payment is failed or just let it as what it is (pending or whatever its status is)?
                $payment->status = $transaction->mapToInternalStatus();

                $payment->save();

                DB::connection()->commit();

                // Fire event to get the coupon if necessary.
                Event::fire('orbit.payment.postupdatepayment.after.commit', [$payment]);

                Log::info('Midtrans::CheckTransactionStatusQueue: Checking stopped.');
            }

            $job->delete();

            // JobBurier::create($job, function($theJob) {
            //     $theJob->delete();
            // })->bury();

        } catch(Exception $e) {

            // If the message contains veritrans text, then assume it is a veritrans error.
            // We should update response data accordingly.
            if (stripos($e->getMessage(), 'veritrans') !== FALSE) {
                // Record Midtrans' response Code & Message.
                $payment->provider_response_code = $e->getCode();
                $payment->provider_response_message = $e->getMessage();

                $payment->save();

                DB::connection()->commit();

                // Should we retry?
                // @todo create a unified method here or somewhere, maybe.
                if (in_array($e->getCode(), [500, 502, 503, 505]) && 
                    $data['check'] < Config::get('orbit.partners_api.midtrans.transaction_status_max_retry', 60)) {

                    $this->retryChecking($data);
                }
                else {
                    Log::info('Midtrans::CheckTransactionStatusQueue: Checking stopped.');
                }
            }
            else {
                DB::connection()->rollback();

                Log::info('Midtrans::CheckTransactionStatusQueue: (E) ' . $e->getFile()  . ':' . $e->getLine() . ' >> ' . $e->getMessage());
                Log::info('Midtrans::CheckTransactionStatusQueue: Checking stopped.');
            }
        }
    }

    /**
     * Retry checking by pushing the this job again into the Queue.
     * 
     * @param  [type] $data [description]
     * @return [type]       [description]
     */
    private function retryChecking($data)
    {
        // NOTE delay can be passed as queue's data, so no need to get it from config everytime?
        $delay = Config::get('orbit.partners_api.midtrans.transaction_status_timeout', 60);

        Queue::later(
            $delay,
            'Orbit\\Queue\\Payment\\Midtrans\\CheckTransactionStatusQueue',
            ['transactionId' => $data['transactionId'], 'check' => $data['check']]
        );

        Log::info('Midtrans::CheckTransactionStatusQueue: Check #' . ($data['check'] + 1) . ' is scheduled to run after ' . $delay . ' seconds.');
    }
}
