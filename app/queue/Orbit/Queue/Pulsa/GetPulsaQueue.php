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
use Orbit\Notifications\Pulsa\PulsaSuccessWithoutSerialNumberNotification;

use Orbit\Helper\GoogleMeasurementProtocol\Client as GMP;

use App;
use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts\ReservationInterface;

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

    private $objectType = 'pulsa';

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
        $payment = null;
        $discount = null;
        $pulsa = null;

        if (! isset($data['retry'])) {
            $data['retry'] = 0;
        }

        $activity = Activity::mobileci()
                            ->setActivityType('transaction')
                            ->setActivityName('transaction_status')
                            ->setCurrentUrl($data['current_url']);

        try {
            DB::connection()->beginTransaction();

            $paymentId = $data['paymentId'];

            $this->log("Getting item for PaymentID: {$paymentId}");

            $payment = PaymentTransaction::onWriteConnection()->with(['details.pulsa', 'user', 'midtrans', 'discount_code'])->findOrFail($paymentId);

            $activity->setUser($payment->user);

            // Dont issue coupon if after some delay the payment was canceled.
            if ($payment->denied() || $payment->failed() || $payment->expired() || $payment->canceled()
                || $payment->status === PaymentTransaction::STATUS_SUCCESS_NO_PULSA_FAILED
                || $payment->status === PaymentTransaction::STATUS_SUCCESS_REFUND) {

                $this->log("Payment {$paymentId} was denied/canceled/failed/refunded. We should not issue any item.");

                DB::connection()->commit();

                $job->delete();

                return;
            }

            $pulsa = $this->getPulsa($payment);

            if (! empty($pulsa)) {
                $this->objectType = ucwords(str_replace(['_', '-'], ' ', $pulsa->object_type));
            }

            $discount = $payment->discount_code;
            $phoneNumber = $payment->extra_data;
            $pulsaName = $pulsa->pulsa_display_name;

            // Send request to buy pulsa from MCash
            $pulsaPurchase = Purchase::create()->doPurchase($pulsa->pulsa_code, $phoneNumber, $paymentId);
            // $pulsaPurchase = Purchase::create()->mockSuccess()->doPurchase($pulsa->pulsa_code, $phoneNumber, $paymentId);

            if ($pulsaPurchase->isSuccess()) {
                $payment->status = PaymentTransaction::STATUS_SUCCESS;

                $this->log("Issued for payment {$paymentId}..");

                // Notify Customer.
                $payment->user->notify(new ReceiptNotification($payment, $pulsaPurchase->getSerialNumber()));

                GMP::create(Config::get('orbit.partners_api.google_measurement'))->setQueryString(['ea' => 'Purchase Pulsa Successful', 'ec' => 'Pulsa', 'el' => $pulsaName])->request();

                $activity->setActivityNameLong('Transaction is Successful')
                        ->setModuleName('Midtrans Transaction')
                        ->setObject($payment)
                        ->setObjectDisplayName($pulsaName)
                        ->setNotes($phoneNumber)
                        ->setLocation($mall)
                        ->responseOK()
                        ->save();

                if (! empty($discount)) {
                    $discountCode = $discount->discount_code;
                    // Mark promo code as issued.
                    $promoCodeReservation = App::make(ReservationInterface::class);
                    $promoData = (object) [
                        'promo_code' => $discount->discount_code,
                        'object_id' => $pulsa->pulsa_item_id,
                        'object_type' => 'pulsa'
                    ];
                    $promoCodeReservation->markAsIssued($payment->user, $promoData);
                    $this->log("Promo code {$discountCode} issued for purchase {$paymentId}");
                }

                $this->log("pulsaData: " . serialize([$pulsa->pulsa_code, $phoneNumber, $paymentId]));
                $this->log("Purchase response: " . serialize($pulsaPurchase));
            }
            else if ($pulsaPurchase->isPending()) {
                $this->log("Pulsa purchase is PENDING for payment {$paymentId}.");
                $this->log("pulsaData: " . serialize([$pulsa->pulsa_code, $phoneNumber, $paymentId]));
                $this->log("Purchase response: " . serialize($pulsaPurchase));

                $payment->status = PaymentTransaction::STATUS_SUCCESS;

                if (! empty($discount)) {
                    $discountCode = $discount->discount_code;
                    // Mark promo code as issued.
                    $discountCode = $discount->discount_code;
                    $promoCodeReservation = App::make(ReservationInterface::class);
                    $promoData = (object) [
                        'promo_code' => $discountCode,
                        'object_id' => $pulsa->pulsa_item_id,
                        'object_type' => 'pulsa'
                    ];
                    $promoCodeReservation->markAsIssued($payment->user, $promoData);
                    $this->log("Promo code {$discountCode} issued for purchase {$paymentId}");
                }

                GMP::create(Config::get('orbit.partners_api.google_measurement'))->setQueryString(['ea' => 'Purchase Pulsa Successful', 'ec' => 'Pulsa', 'el' => $pulsaName])->request();
            }
            else if ($pulsaPurchase->shouldRetry($data['retry'])) {
                $data['retry']++;

                GMP::create(Config::get('orbit.partners_api.google_measurement'))->setQueryString(['ea' => 'Purchase Pulsa Retry ' . $data['retry'], 'ec' => 'Pulsa', 'el' => $pulsaName])->request();

                $this->log("Retry #{$data['retry']} for Pulsa Purchase will be run in {$this->retryDelay} minutes...");
                $this->log("pulsaData: " . serialize([$pulsa->pulsa_code, $phoneNumber, $paymentId]));
                $this->log("Purchase response: " . serialize($pulsaPurchase));

                // Retry purchase in a few minutes...
                $this->retryDelay = $this->retryDelay * 60; // seconds
                Queue::later(
                    $this->retryDelay,
                    'Orbit\\Queue\\Pulsa\\GetPulsaQueue',
                    $data
                );

                // Send notification each time we do retry...
                foreach($adminEmails as $email) {
                    $admin              = new User;
                    $admin->email       = $email;
                    $admin->notify(new PulsaRetryNotification($payment, $pulsaPurchase->getMessage()));
                }
            }
            else if ($pulsaPurchase->maxRetryReached($data['retry'])) {
                $this->log("pulsaData: " . serialize([$pulsa->pulsa_code, $phoneNumber, $paymentId]));
                $this->log("Purchase response: " . serialize($pulsaPurchase));
                throw new Exception("Pulsa purchase is FAILED, MAX RETRY REACHED ({$data['retry']}).");
            }
            else if ($pulsaPurchase->isOutOfStock()) {
                $this->log("Pulsa {$pulsa->pulsa_code} -- {$pulsa->pulsa_display_name} is OUT OF STOCK.");
                $this->log("PulsaPurchase Data" . serialize([$pulsa->pulsa_code, $phoneNumber, $paymentId]));
                $this->log("PulsaPurchase Response: " . serialize($pulsaPurchase));
                throw new Exception("Pulsa {$pulsa->pulsa_code} -- {$pulsa->pulsa_display_name} is OUT OF STOCK (STATUS: {$pulsaPurchase->getData()->status}).");
            }
            else {
                $this->log("Pulsa purchase is FAILED for payment {$paymentId}. Unknown status from MCash.");
                $this->log("pulsaData: " . serialize([$pulsa->pulsa_code, $phoneNumber, $paymentId]));
                $this->log("Purchase response: " . serialize($pulsaPurchase));
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

            // Send notification to admin if pulsa purchase is success
            // but WITHOUT SERIAL NUMBER.
            if ($pulsaPurchase->isSuccessWithoutSN()) {
                foreach($adminEmails as $email) {
                    $admin              = new User;
                    $admin->email       = $email;
                    $admin->notify(new PulsaSuccessWithoutSerialNumberNotification($payment, 'Pulsa Success Without Serial Number'));
                }
            }

            // Increase point when the transaction is success.
            if (in_array($payment->status, [PaymentTransaction::STATUS_SUCCESS])) {
                $rewardObject = (object) [
                    'object_id' => $pulsa->pulsa_item_id,
                    'object_type' => 'pulsa',
                    'object_name' => $pulsa->pulsa_display_name,
                    'country_id' => $payment->country_id,
                ];

                Event::fire('orbit.purchase.pulsa.success', [$payment->user, $rewardObject]);
            }

        } catch (Exception $e) {

            // Mark as failed if we get any exception.
            if (! empty($payment)) {
                $payment->status = PaymentTransaction::STATUS_SUCCESS_NO_PULSA_FAILED;
                $payment->save();

                if (! empty($discount) && ! empty($pulsa)) {
                    // Mark promo code as available.
                    $discountCode = $discount->discount_code;
                    $promoCodeReservation = App::make(ReservationInterface::class);
                    $promoData = (object) [
                        'promo_code' => $discountCode,
                        'object_id' => $pulsa->pulsa_item_id,
                        'object_type' => 'pulsa'
                    ];
                    $promoCodeReservation->markAsAvailable($payment->user, $promoData);
                    $this->log("Promo code {$discountCode} reverted back/marked as available...");
                }

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

                $pulsaName = ! empty($pulsa) ? $pulsa->pulsa_display_name : '-';

                GMP::create(Config::get('orbit.partners_api.google_measurement'))->setQueryString(['ea' => 'Purchase Pulsa Failed', 'ec' => 'Pulsa', 'el' => $pulsaName])->request();

                $activity->setActivityNameLong('Transaction is Success - Failed Getting ' . $this->objectType)
                         ->setModuleName('Midtrans Transaction')
                         ->setObject($payment)
                         ->setObjectDisplayName($pulsaName)
                         ->setNotes($notes)
                         ->setLocation($mall)
                         ->responseFailed()
                         ->save();
            }
            else {
                DB::connection()->rollBack();
            }

            $this->log(sprintf("Get {$this->objectType} exception: %s:%s, %s", $e->getFile(), $e->getLine(), $e->getMessage()));
            $this->log(serialize($data));
        }

        $job->delete();
    }

    private function getPulsa($payment)
    {
        $pulsa = null;
        foreach($payment->details as $detail) {
            if (! empty($detail->pulsa)) {
                $pulsa = $detail->pulsa;
                break;
            }
        }

        if (empty($pulsa)) {
            throw new Exception("{$this->objectType} for payment {$payment->payment_transaction_id} is not found.", 1);
        }

        return $pulsa;
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
