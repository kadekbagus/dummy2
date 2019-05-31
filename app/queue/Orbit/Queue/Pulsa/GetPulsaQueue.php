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
use Orbit\Notifications\Pulsa\CustomerPulsaPendingNotification;
use Orbit\Notifications\Pulsa\PulsaRetryNotification;

use Orbit\Helper\GoogleMeasurementProtocol\Client as GMP;

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
     * Delay before we trigger another MCash Purchase (in minutes).
     * @var integer
     */
    protected $retryDelay = 3;

    /**
     * Maximum number of retry we would do if the first time was failed.
     * @var integer
     */
    protected $maxRetry = 10;

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
        $shouldRetry = false;
        if (! isset($data['retry'])) {
            $data['retry'] = 0;
        }

        $activity = Activity::mobileci()
                            ->setActivityType('transaction')
                            ->setActivityName('transaction_status');

        try {
            DB::connection()->beginTransaction();

            $paymentId = $data['paymentId'];

            Log::info("Pulsa: Getting pulsa for PaymentID: {$paymentId}");

            $payment = PaymentTransaction::with(['details.pulsa', 'user', 'midtrans'])->findOrFail($paymentId);

            $activity->setUser($payment->user);

            // Dont issue coupon if after some delay the payment was canceled.
            if ($payment->denied() || $payment->failed() || $payment->expired() || $payment->canceled()
                || $payment->status === PaymentTransaction::STATUS_SUCCESS_NO_PULSA_FAILED) {

                Log::info("Pulsa: Payment {$paymentId} was denied/canceled/failed. We should not issue any pulsa.");

                DB::connection()->commit();

                $job->delete();

                return;
            }

            $paymentDetail = $payment->details->first();
            $pulsa = $paymentDetail->pulsa;
            $phoneNumber = $payment->extra_data;
            $pulsaName = $pulsa->pulsa_display_name;

            // If we are retrying purchase, then add something at the end
            // of paymentId to make it unique.
            $uniqueId = $data['retry'] > 0 ? "-{$data['retry']}" : '';

            // Send request to buy pulsa from MCash
            $pulsaPurchase = Purchase::create()->doPurchase($pulsa->pulsa_code, $phoneNumber, sprintf('%s%s', $paymentId, $uniqueId));

            // Test only, set status response manually.
            // $pulsaPurchase->setStatus(609);

            if ($pulsaPurchase->isSuccess()) {
                $payment->status = PaymentTransaction::STATUS_SUCCESS;

                Log::info("Pulsa: issued for payment {$paymentId}..");

                // Notify Customer.
                $payment->user->notify(new ReceiptNotification($payment));

                GMP::create(Config::get('orbit.partners_api.google_measurement'))->setQueryString(['ea' => 'Purchase Pulsa Successful', 'ec' => 'Pulsa', 'el' => $pulsaName])->request();

                Log::info("Pulsa: PulsaPurchase Data" . serialize([$pulsa->pulsa_code, $phoneNumber, $paymentId]));
                Log::info("Pulsa: PulsaPurchase Response: " . serialize($pulsaPurchase));
            }
            else if ($pulsaPurchase->isPending()) {
                Log::info("Pulsa: Pulsa purchase is PENDING for payment {$paymentId}.");
                Log::info("Pulsa: PulsaPurchase Data " . serialize([$pulsa->pulsa_code, $phoneNumber, $paymentId]));
                Log::info("Pulsa: PulsaPurchase Response " . serialize($pulsaPurchase));

                $payment->status = PaymentTransaction::STATUS_SUCCESS;

                GMP::create(Config::get('orbit.partners_api.google_measurement'))->setQueryString(['ea' => 'Purchase Pulsa Successful', 'ec' => 'Pulsa', 'el' => $pulsaName])->request();
            }
            else if ($pulsaPurchase->shouldRetry($data['retry'], $this->maxRetry)) {
                $shouldRetry = true;

                Log::info("Pulsa: Purchase failed and will be retried in a moment.");
                Log::info("Pulsa: PulsaPurchase Data " . serialize([$pulsa->pulsa_code, $phoneNumber, $paymentId]));
                Log::info("Pulsa: PulsaPurchase Response: " . serialize($pulsaPurchase));

                GMP::create(Config::get('orbit.partners_api.google_measurement'))->setQueryString(['ea' => 'Purchase Pulsa Retry ' . $data['retry'], 'ec' => 'Pulsa', 'el' => $pulsaName])->request();

                throw new Exception("Pulsa will be retried.", 1);
            }
            else if ($data['retry'] === $this->maxRetry) {
                Log::info("Pulsa: PulsaPurchase Data" . serialize([$pulsa->pulsa_code, $phoneNumber, $paymentId]));
                Log::info("Pulsa: PulsaPurchase Response: " . serialize($pulsaPurchase));
                throw new Exception("Pulsa purchase is FAILED, MAX RETRY REACHED ({$data['retry']}).");
            }
            else {
                Log::info("Pulsa: Pulsa purchase is FAILED for payment {$paymentId}. Unknown status from MCash.");
                Log::info("Pulsa: PulsaPurchase Data: " . serialize([$pulsa->pulsa_code, $phoneNumber, $paymentId]));
                Log::info("Pulsa: PulsaPurchase Response: " . serialize($pulsaPurchase));
                $shouldRetry = true;
                throw new Exception("Pulsa purchase is FAILED, unknown status from MCASH (STATUS CODE: {$pulsaPurchase->getCode()}).");
            }

            $payment->save();

            // Commit the changes ASAP.
            DB::connection()->commit();

            if ($pulsaPurchase->isSuccess()) {
                $activity->setActivityNameLong('Transaction is Successful')
                        ->setModuleName('Midtrans Transaction')
                        ->setObject($payment)
                        ->setObjectDisplayName($pulsaName)
                        ->setNotes($phoneNumber)
                        ->setLocation($mall)
                        ->responseOK()
                        ->save();
            }

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

                if ($shouldRetry && $data['retry'] < $this->maxRetry) {
                    $data['retry']++;

                    // Retry purchase in a few minutes...
                    $retryDelay = $this->retryDelay * 60; // seconds
                    Queue::later(
                        $retryDelay,
                        'Orbit\\Queue\\Pulsa\\GetPulsaQueue',
                        $data
                    );

                    Log::info("Pulsa: Retry #{$data['retry']} for Pulsa Purchase will be run in {$this->retryDelay} minutes...");

                    // Send notification each time we do retry...
                    foreach($adminEmails as $email) {
                        $admin              = new User;
                        $admin->email       = $email;
                        $admin->notify(new PulsaRetryNotification($payment, $pulsaPurchase->getMessage()));
                    }

                    DB::connection()->rollBack();
                }
                else {
                    $payment->status = PaymentTransaction::STATUS_SUCCESS_NO_PULSA_FAILED;
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

                    $paymentDetail = $payment->details->first();
                    $pulsa = isset($paymentDetail->pulsa) ? $paymentDetail->pulsa : null;
                    $pulsaName = ! empty($pulsa) ? $pulsa->pulsa_display_name : '-';

                    GMP::create(Config::get('orbit.partners_api.google_measurement'))->setQueryString(['ea' => 'Purchase Pulsa Failed', 'ec' => 'Pulsa', 'el' => $pulsaName])->request();

                    $activity->setActivityNameLong('Transaction is Success - Failed Getting Pulsa')
                             ->setModuleName('Midtrans Transaction')
                             ->setObject($payment)
                             ->setObjectDisplayName($pulsaName)
                             ->setNotes($notes)
                             ->setLocation($mall)
                             ->responseFailed()
                             ->save();
                }
            }
            else {
                DB::connection()->rollBack();
            }

            Log::info(sprintf('Pulsa: Queue exception: %s:%s, %s', $e->getFile(), $e->getLine(), $e->getMessage()));
            Log::info('Pulsa: Queue Data ' . serialize($data));
        }

        $job->delete();
    }
}
