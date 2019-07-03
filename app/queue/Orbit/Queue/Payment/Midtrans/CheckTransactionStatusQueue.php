<?php namespace Orbit\Queue\Payment\Midtrans;

use DB;
use Log;
use Queue;
use Config;
use Exception;
use Event;
use Mall;
use Activity;
use Orbit\Helper\Util\JobBurier;

use PaymentTransaction;

use Orbit\Helper\Midtrans\API\TransactionStatus;

use Orbit\Notifications\Payment\ExpiredPaymentNotification;
use Orbit\Notifications\Pulsa\ExpiredPaymentNotification as PulsaExpiredPaymentNotification;

/**
 * Get transaction status from Midtrans and update our internal payment status if necessary.
 *
 * @author Budi <budi@dominopos.com>
 * @todo Remove logging.
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

            $payment = PaymentTransaction::onWriteConnection()->with(['details.coupon', 'details.pulsa', 'midtrans', 'issued_coupons', 'user'])->find($data['transactionId']);
            $mallId = isset($data['mall_id']) ? $data['mall_id'] : null;
            $mall = Mall::where('merchant_id', $mallId)->first();

            $activity = Activity::mobileci()
                            ->setActivityType('transaction')
                            ->setUser($payment->user)
                            ->setActivityName('transaction_status');

            // If payment completed or expired then do nothing.
            // (It maybe completed by notification callback/ping from Midtrans)
            if ($payment->completed()) {
                Log::info('Midtrans::CheckTransactionStatusQueue: Transaction ID ' . $data['transactionId'] . ' completed. Nothing to do.');

                DB::connection()->commit();

                $job->delete();

                return;
            }
            else if ($payment->expired() || $payment->failed() || $payment->denied() || $payment->canceled()) {
                Log::info("Midtrans::CheckTransactionStatusQueue: Transaction ID {$data['transactionId']} status is {$payment->status}.");

                if (! $payment->forPulsa()) {
                    $payment->cleanUp();
                }

                DB::connection()->commit();

                if ($payment->failed() || $payment->denied()) {
                    Log::info('Transaction is Failed');
                    $activity->setActivityNameLong('Transaction is Failed')
                            ->setModuleName('Midtrans Transaction')
                            ->setObject($payment)
                            ->setNotes('Transaction is failed from Midtrans/Customer.')
                            ->setLocation($mall)
                            ->responseFailed()
                            ->save();
                }
                else if ($payment->expired()) {
                    Log::info('Transaction is Expired');
                    $activity->setActivityNameLong('Transaction is Expired')
                            ->setModuleName('Midtrans Transaction')
                            ->setObject($payment)
                            ->setNotes('Transaction is expired from Midtrans.')
                            ->setLocation($mall)
                            ->responseFailed()
                            ->save();
                }

                $job->delete();

                return;
            }

            Log::info('Midtrans::CheckTransactionStatusQueue: Checking transaction status... ' . $data['transactionId']);

            $data['check']++;

            $transaction = TransactionStatus::create()->getStatus($data['transactionId']);

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
                $transactionStatus = $transaction->mapToInternalStatus();
                if ($transaction->isSuccess()) {
                    if ($payment->forSepulsa() || $payment->paidWith(['bank_transfer', 'echannel', 'gopay'])) {
                        if ($payment->forPulsa()) {
                            $transactionStatus = PaymentTransaction::STATUS_SUCCESS_NO_PULSA;
                        }
                        else {
                            $transactionStatus = PaymentTransaction::STATUS_SUCCESS_NO_COUPON;
                        }
                    }
                }

                $payment->status = $transactionStatus;

                $payment->save();

                DB::connection()->commit();

                if ($payment->failed() || $payment->denied()) {
                    $activity->setActivityNameLong('Transaction is Failed')
                            ->setModuleName('Midtrans Transaction')
                            ->setObject($payment)
                            ->setNotes('Transaction is failed from Midtrans/Customer.')
                            ->setLocation($mall)
                            ->responseFailed()
                            ->save();
                }
                else if ($payment->expired()) {
                    $activity->setActivityNameLong('Transaction is Expired')
                            ->setModuleName('Midtrans Transaction')
                            ->setObject($payment)
                            ->setNotes('Transaction is expired from Midtrans.')
                            ->setLocation($mall)
                            ->responseFailed()
                            ->save();

                    $paymentDetail = $payment->details->first();
                    if ($paymentDetail->object_type === 'pulsa') {
                        $payment->user->notify(new PulsaExpiredPaymentNotification($payment));
                    } else if ($paymentDetail->object_type === 'coupon') {
                        $payment->user->notify(new ExpiredPaymentNotification($payment));
                    }
                }

                // Fire event to get the coupon if necessary.
                Event::fire('orbit.payment.postupdatepayment.after.commit', [$payment, $mall]);

                Log::info('Midtrans::CheckTransactionStatusQueue: Checking stopped.');
            }

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

        $job->delete();
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
            $data
        );

        Log::info('Midtrans::CheckTransactionStatusQueue: Check #' . ($data['check'] + 1) . ' is scheduled to run after ' . $delay . ' seconds.');
    }
}
