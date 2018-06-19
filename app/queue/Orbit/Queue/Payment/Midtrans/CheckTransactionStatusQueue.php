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
    protected $debug = false;

    /**
     * $data should have 2 items:
     * 1. transactionId -- the transaction ID we want to check. Should be equivalent with external_payment_transaction_id.
     * 2. check -- counter to track the number of check status API we've been calling for this transactionId. We increase the value right after TransactionStatus request made.
     * 
     * @param  [type] $job  [description]
     * @param  [type] $data [description]
     * @return [type]       [description]
     */
    public function fire($job, $data)
    {
        $transaction = null;

        try {

            $this->debug = Config::get('app.debug');

            DB::connection()->beginTransaction();

            $payment = PaymentTransaction::with(['coupon', 'issued_coupon', 'user', 'coupon_sepulsa'])
                                                ->where('external_payment_transaction_id', $data['transactionId'])->first();

            if (empty($payment)) {
                // If no transaction found, so we should not do/schedule any check.
                throw new Exception('Transaction with ExternalID: ' . $data['transactionId'] . ' not found!');
            }

            // If payment completed then do nothing.
            // (It maybe completed by notification callback/ping from Midtrans)
            if ($payment->completed()) {
                $this->log('Midtrans::CheckTransactionStatusQueue: Transaction ID ' . $data['transactionId'] . ' completed. Nothing to do.');

                if (! $payment->couponIssued()) {
                    $this->log('Midtrans::CheckTransactionStatusQueue: Transaction ID ' . $data['transactionId'] . ' Coupon NOT ISSUED YET.');
                }

                return;
            }

            $this->log('Midtrans::CheckTransactionStatusQueue: Checking transaction status... ' . $data['transactionId']);

            $data['check']++;

            $transaction = TransactionStatus::create()->getStatus($data['transactionId']);

            // Record Midtrans' response Code & Message.
            $payment->provider_response_code = $transaction->getCode();
            $payment->provider_response_message = $transaction->getMessage();

            $payment->save();

            // Re-run this queue if the status is still pending (and not reached maximum try yet)
            if ($transaction->shouldRetryChecking($data['check'])) {

                // Commit the provider response changes in DB.
                DB::connection()->commit();

                // Then schedule check status...
                // NOTE delay can be passed as queue's data, so no need to get it from config everytime?
                $this->retryChecking($data);
            }
            else {
                // If we get here, it's either transaction is success, expired, error, or reached maximum check allowed,
                // thus no need to do/schedule checking anymore.

                // Set the internal payment status based on transaction status from Midtrans.
                // @todo Should we assume the payment is failed or just let it as what it is (pending or whatever its status is)?
                $payment->status = PaymentTransaction::STATUS_FAILED;

                if ($transaction->isSuccess()) {
                    $payment->status = PaymentTransaction::STATUS_SUCCESS;
                }
                else if ($transaction->isExpired()) {
                    $payment->status = PaymentTransaction::STATUS_EXPIRED;
                }

                $payment->save();

                // Fire event to issue the coupon.
                Event::fire('orbit.payment.postupdatepayment.after.save', [$payment]);

                DB::connection()->commit();

                $payment->load('issued_coupon');

                // Fire event to send receipt/notification if necessary.
                Event::fire('orbit.payment.postupdatepayment.after.commit', [$payment]);

                $this->log('Midtrans::CheckTransactionStatusQueue: Checking stopped.');
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
            }
            else {
                DB::connection()->rollback();

                $this->log('Midtrans::CheckTransactionStatusQueue: (E) ' . $e->getFile()  . ':' . $e->getLine() . ' >> ' . $e->getMessage(), true);
                // $this->log('Midtrans::CheckTransactionStatusQueue: (E) Data: ' . serialize($data), true);
            }

            $this->log('Midtrans::CheckTransactionStatusQueue: Checking stopped.');
        }
    }

    /**
     * Log message. Only log if in debug mode or forced.
     * 
     * @param  string $message [description]
     * @return [type]          [description]
     */
    private function log($message = '', $forceLogging = false)
    {
        if ($this->debug || $forceLogging) {
            Log::info($message);
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

        $this->log('Midtrans::CheckTransactionStatusQueue: Check #' . ($data['check'] + 1) . ' is scheduled to run in ' . $delay . ' seconds.');
    }
}
