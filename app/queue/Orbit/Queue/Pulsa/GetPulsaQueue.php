<?php namespace Orbit\Queue\Pulsa;

use DB;
use Log;
use Event;
use Queue;
use Config;
use Exception;
use Carbon\Carbon;

use User;
use PaymentTransaction;
use IssuedCoupon;
use Coupon;
use Mall;
use Activity;

use Orbit\Helper\MCash\API\Purchase;

// Notifications
use Orbit\Notifications\Pulsa\ReceiptNotification;
use Orbit\Notifications\Pulsa\PulsaNotAvailableNotification;
use Orbit\Notifications\Pulsa\CustomerPulsaNotAvailableNotification;
use Orbit\Notifications\Pulsa\PulsaPendingNotification;


/**
 * A job to get/issue Hot Deals Coupon after payment completed.
 * At this point, we assume the payment was completed (paid) so anything wrong
 * while trying to issue the coupon will make the status success_no_coupon_failed.
 *
 * @author Budi <budi@dominopos.com>
 */
class GetPulsaQueue
{
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
        $mallId = isset($data['mall_id']) ? $data['mall_id'] : null;
        $mall = Mall::where('merchant_id', $mallId)->first();

        $activity = Activity::mobileci()
                            ->setActivityType('transaction')
                            ->setActivityName('transaction_status');

        try {
            DB::connection()->beginTransaction();

            $paymentId = $data['paymentId'];

            Log::info("Pulsa: Getting pulsa PaymentID: {$paymentId}");

            $payment = PaymentTransaction::with(['details.pulsa', 'user', 'midtrans'])->findOrFail($paymentId);

            $activity->setUser($payment->user);

            // Dont issue coupon if after some delay the payment was canceled.
            if ($payment->denied() || $payment->failed() || $payment->expired() || $payment->canceled()) {

                Log::info("Pulsa: Payment {$paymentId} was denied/canceled. We should not issue any pulsa.");

                $job->delete();

                return;
            }

            $pulsa = $payment->details->first()->pulsa;
            $phoneNumber = $payment->extra_data;
            $pulsaName = $pulsa->pulsa_display_name;

            // Send request to buy pulsa from MCash
            $pulsaPurchase = Purchase::create()->doPurchase($pulsa->pulsa_code, $phoneNumber, $paymentId);

            // Test only, set status response manually.
            // $pulsaPurchase->setStatus(0); // success

            if ($pulsaPurchase->isSuccess()) {
                $payment->status = PaymentTransaction::STATUS_SUCCESS;

                Log::info("Pulsa: issued for payment {$paymentId}..");

                // Notify Customer.
                $payment->user->notify(new ReceiptNotification($payment));

                $activity->setActivityNameLong('Transaction is Successful')
                        ->setModuleName('Midtrans Transaction')
                        ->setObject($payment)
                        ->setObjectDisplayName($pulsaName)
                        ->setNotes($phoneNumber)
                        ->setLocation($mall)
                        ->responseOK()
                        ->save();
            }
            else if ($pulsaPurchase->isPending()) {
                Log::info("Pulsa: Pulsa purchase is PENDING for payment {$paymentId}.");
                Log::info("pulsaData: " . serialize([$pulsa->pulsa_code, $phoneNumber, $paymentId]));
                Log::info("Purchase response: " . serialize($pulsaPurchase));

                $payment->status = PaymentTransaction::STATUS_SUCCESS_NO_PULSA;
            }
            else if ($pulsaPurchase->isNotAvailable()) {
                throw new Exception("Pulsa NOT AVAILABLE FROM MCASH.");
            }
            else {
                Log::info("Pulsa: Pulsa purchase is FAILED for payment {$paymentId}. Unknown status from MCash.");
                Log::info("pulsaData: " . serialize([$pulsa->pulsa_code, $phoneNumber, $paymentId]));
                Log::info("Purchase response: " . serialize($pulsaPurchase));
                throw new Exception("Pulsa purchase is FAILED, unknown status from MCASH.");
            }

            $payment->save();

            // Commit the changes ASAP.
            DB::connection()->commit();

            // Send notification to admin if pulsa purchase is pending.
            if ($pulsaPurchase->isPending()) {
                foreach($adminEmails as $email) {
                    $admin              = new User;
                    $admin->email       = $email;
                    $admin->notify(new PulsaPendingNotification($payment, 'Pending Payment'));
                }
            }

        } catch (Exception $e) {

            // Mark as failed if we get any exception.
            if (! empty($payment)) {
                $payment->status = PaymentTransaction::STATUS_SUCCESS_NO_COUPON_FAILED;
                $payment->save();

                DB::connection()->commit();

                // Notify admin for this failure.
                foreach($adminEmails as $email) {
                    $admin              = new User;
                    $admin->email       = $email;
                    $admin->notify(new PulsaNotAvailableNotification($payment, $e->getMessage()));
                }

                // Notify customer that coupon is not available.
                $payment->user->notify(new CustomerPulsaNotAvailableNotification($payment));

                $notes = $phoneNumber . ' ---- ' . $e->getMessage();

                $activity->setActivityNameLong('Transaction is Success - Failed Getting Pulsa')
                         ->setModuleName('Midtrans Transaction')
                         ->setObject($payment)
                         ->setObjectDisplayName($pulsaName)
                         ->setNotes($notes)
                         ->setLocation($mall)
                         ->responseFailed()
                         ->save();

                 // Activity::mobileci()
                 //         ->setUser($payment->user)
                 //         ->setActivityType('click')
                 //         ->setActivityName('coupon_added_to_wallet')
                 //         ->setActivityNameLong('Coupon Added to Wallet Failed')
                 //         ->setModuleName('Coupon')
                 //         ->setObject($payment->details->first()->coupon)
                 //         ->setNotes($e->getMessage())
                 //         ->setLocation($mall)
                 //         ->responseFailed()
                 //         ->save();
            }
            else {
                DB::connection()->rollBack();
            }

            Log::info(sprintf('Pulsa: Get Pulsa exception: %s:%s, %s', $e->getFile(), $e->getLine(), $e->getMessage()));
            Log::info('Pulsa: ' . serialize($data));
        }

        $job->delete();
    }
}
