<?php namespace Orbit\Queue\Payment\Midtrans;

use DB;
use Log;
use Queue;
use Config;
use Exception;
use Orbit\Helper\Util\JobBurier;

use Veritrans\Veritrans_Config;
use Veritrans\Veritrans_Transaction;

use PaymentTransaction;

use Orbit\Helper\Midtrans\API\Response\TransactionStatusResponse;

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
            if ($payment->completed() && $payment->couponIssued()) {
                $this->log('Midtrans::CheckTransactionStatusQueue: Transaction ID ' . $data['transactionId'] . ' completed and the coupon issued. Nothing to do.');
                return;
            }
            else if ($payment->completed() && ! $payment->couponIssued()) {
                $this->log('Midtrans::CheckTransactionStatusQueue: Transaction ID ' . $data['transactionId'] . ' completed BUT the coupon NOT ISSUED YET.');
            }
        
            Veritrans_Config::$serverKey = Config::get('orbit.partners_api.midtrans.server_key', '');
            Veritrans_Config::$isProduction = Config::get('orbit.partners_api.midtrans.is_production', false);

            $this->log('Midtrans::CheckTransactionStatusQueue: Checking transaction status... ' . $data['transactionId']);

            $transaction = new TransactionStatusResponse(Veritrans_Transaction::status($data['transactionId']));

            $data['check']++;

            // Re-run this queue if the status is still pending (and not reached maximum try yet)
            if ($transaction->isPending() && $data['check'] < Config::get('orbit.partners_api.midtrans.transaction_status_max_retry', 60)) {

                // NOTE delay can be passed as queue's data, so no need to get it from config everytime?
                $delay = Config::get('orbit.partners_api.midtrans.transaction_status_timeout', 60);

                Queue::later(
                    $delay,
                    'Orbit\\Queue\\Payment\\Midtrans\\CheckTransactionStatusQueue',
                    ['transactionId' => $data['transactionId'], 'check' => $data['check']]
                );

                $this->log('Midtrans::CheckTransactionStatusQueue: Check #' . ($data['check'] + 1) . ' is scheduled to run in ' . $delay . ' seconds.');
            }
            else {
                // If we get here, it's either transaction is success, expired, error, or reached maximum check allowed,
                // thus no need to do/schedule checking anymore.

                // Set the internal payment status based on transaction status from Midtrans.
                // We should always assume the payment is failed, unless the transaction status from Midtrans is success.
                $payment->status = PaymentTransaction::STATUS_FAILED;
                if ($transaction->isSuccess()) {
                    $payment->status = PaymentTransaction::STATUS_SUCCESS;
                }

                // Record Midtrans' response Code & Message.
                $payment->provider_response_code = $transaction->getCode();
                $payment->provider_response_message = $transaction->getMessage();

                $payment->save();

                // Fire event to issue the coupon.
                Event::fire('orbit.payment.postupdatepayment.after.save', [$payment]);

                DB::connection()->commit();

                // Fire event to send receipt/notification if necessary.
                Event::fire('orbit.payment.postupdatepayment.after.commit', [$payment]);
            }

            $job->delete();

            JobBurier::create($job, function($theJob) {
                $theJob->delete();
            })->bury();

        } catch(Exception $e) {
            DB::connection()->rollback();
            $this->log('Midtrans::CheckTransactionStatusQueue: (E) ' . $e->getFile()  . ':' . $e->getLine() . ' >> ' . $e->getMessage(), true);
            $this->log('Midtrans::CheckTransactionStatusQueue: (E) Data: ' . serialize($data), true);
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
}
