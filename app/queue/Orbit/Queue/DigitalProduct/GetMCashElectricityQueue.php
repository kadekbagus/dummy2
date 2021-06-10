<?php namespace Orbit\Queue\DigitalProduct;

use App;
use Log;
use Mall;
use User;
use Event;
use Config;
use Exception;
use PaymentTransaction;
use Illuminate\Support\Facades\DB;
use Orbit\Helper\MCash\API\Purchase;
use Illuminate\Support\Facades\Queue;
use Orbit\Notifications\Pulsa\PulsaRetryNotification;
use Orbit\Helper\GoogleMeasurementProtocol\Client as GMP;
use Orbit\Notifications\DigitalProduct\Electricity\ReceiptNotification;
use Orbit\Controller\API\v1\Pub\Purchase\Activities\PurchaseSuccessActivity;
use Orbit\Notifications\DigitalProduct\DigitalProductNotAvailableNotification;
use Orbit\Controller\API\v1\Pub\Purchase\Activities\PurchaseFailedProductActivity;
use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts\ReservationInterface;
use Orbit\Helper\AutoIssueCoupon\AutoIssueCoupon;
use Orbit\Notifications\DigitalProduct\CustomerDigitalProductNotAvailableNotification;

/**
 * A job to get/issue PLN Token from MCash after payment completed.
 *
 * @author Budi <budi@dominopos.com>
 */
class GetMCashElectricityQueue
{
    /**
     * Delay before we trigger another MCash Purchase (in minutes).
     * @var integer
     */
    protected $retryDelay = 3;

    protected $maxRetry = 10;

    private $objectType = 'digital_product';

    /**
     * Issue PLN token.
     *
     * @param  Illuminate\Queue\Jobs\Job | Orbit\FakeJob $job  the job
     * @param  array $data the data needed to run this job
     * @return void
     */
    public function fire($job, $data)
    {
        $mallId = isset($data['mall_id']) ? $data['mall_id'] : null;
        $mall = Mall::where('merchant_id', $mallId)->first();
        $payment = null;
        $discount = null;
        $digitalProduct = null;
        $purchase = null;

        if (! isset($data['retry'])) {
            $data['retry'] = 0;
        }

        try {
            DB::connection()->beginTransaction();

            $paymentId = $data['paymentId'];

            $this->log("Getting item for PaymentID: {$paymentId}");

            $payment = PaymentTransaction::onWriteConnection()->with([
                'details.digital_product',
                'details.provider_product',
                'user',
                'midtrans',
                'discount_code'
            ])->lockForUpdate()->findOrFail($paymentId);

            // Dont issue coupon if after some delay the payment was canceled.
            if ($payment->denied() || $payment->failed() || $payment->expired() || $payment->canceled()
                || $payment->status === PaymentTransaction::STATUS_SUCCESS_NO_PRODUCT_FAILED
                || $payment->status === PaymentTransaction::STATUS_SUCCESS_REFUND) {

                $this->log("Payment {$paymentId} was denied/canceled/failed/refunded. We should not issue any item.");

                DB::connection()->commit();

                $job->delete();

                return;
            }

            $detail = $payment->details->first();
            $digitalProduct = $payment->getDigitalProduct();
            $providerProduct = $payment->getProviderProduct();
            $customerId = $payment->extra_data;

            if (! empty($digitalProduct)) {
                $this->objectType = ucwords(str_replace(['_', '-'], ' ', $digitalProduct->product_type));
            }

            $discount = $payment->discount_code;

            $purchase = Purchase::create()
                // ->mockResponse(['status' => 618])
                ->doPurchase(
                    $providerProduct->code,
                    $customerId,
                    $paymentId
                );

            // Append noted
            $notes = $payment->notes;
            if (empty($notes)) {
                $notes = '[' . json_encode($purchase->getData()) .']';
            } else {
                $notes = substr_replace($notes, "," . json_encode($purchase->getData()), -1, 0);
            }

            $payment->notes = $notes;

            if ($purchase->isSuccess()) {
                $payment->status = PaymentTransaction::STATUS_SUCCESS;
                $payment->save();

                $detail->payload = $this->parseElectricityInfo($purchase);
                $detail->save();

                DB::connection()->commit();

                $this->log("Electricity Purchase is SUCCESS for payment {$paymentId}.");

                // Auto issue free coupon if trx meet certain criteria.
                AutoIssueCoupon::issue($payment, 'pln');

                $this->recordSuccessGMP($payment, $digitalProduct, $providerProduct, $detail);
            }
            else if ($purchase->isPending()) {
                // assume pending purchase as success, and wait mcash to
                // actually make it success on their side.
                $payment->status = PaymentTransaction::STATUS_SUCCESS;
                $payment->save();

                $detail->payload = $this->parseElectricityInfo($purchase);
                $detail->save();

                DB::connection()->commit();

                $this->log("Electricity Purchase is PENDING for payment {$paymentId}.");
                $this->log("Purchase Data: " . serialize([$providerProduct->code, $customerId, $paymentId]));
                $this->log("Purchase response: " . serialize($purchase));

                $this->recordPendingGMP($payment, $digitalProduct, $providerProduct, $detail);
            }
            else if ($purchase->shouldRetry($data['retry'])) {
                $payment->save();
                DB::connection()->commit();

                $data['retry']++;

                $this->log("Retry #{$data['retry']} for Electricity Purchase will be run in {$this->retryDelay} minutes...");
                $this->log("Purchase Data: " . serialize([$providerProduct->code, $customerId, $paymentId]));
                $this->log("Purchase response: " . serialize($purchase));

                $this->retryDelay = $this->retryDelay * 60; // seconds
                Queue::later(
                    $this->retryDelay,
                    "Orbit\Queue\DigitalProduct\GetMCashElectricityQueue",
                    $data,
                    'gtm_pulsa'
                );

                $this->recordRetryGMP($payment, $data, $digitalProduct);

                $this->notifyRetryPurchase($payment, $purchase);
            }
            else if ($purchase->isOutOfStock()) {
                $this->log("Electricity {$providerProduct->code} -- {$digitalProduct->product_name} is OUT OF STOCK.");
                $this->log("Purchase Data" . serialize([$providerProduct->code, $customerId, $paymentId]));
                $this->log("Purchase Response: " . serialize($purchase));
                throw new Exception("Electricity {$providerProduct->code} -- {$digitalProduct->product_name} is OUT OF STOCK (STATUS: {$purchase->getData()->status}).");
            }
            else {
                $this->log("Electricity Purchase is FAILED for payment {$paymentId}. Unknown status from MCash.");
                $this->log("Purchase Data: " . serialize([$providerProduct->code, $customerId, $paymentId]));
                $this->log("Purchase Response: " . serialize($purchase));
                throw new Exception($purchase->getFailureMessage());
            }

            // If purchase success, then...
            if ($payment->status === PaymentTransaction::STATUS_SUCCESS) {

                // Update promo code
                $this->updatePromoCode('issued', $payment, $discount, $digitalProduct);

                // Record activity.
                $payment->user->activity(new PurchaseSuccessActivity($payment, $this->objectType));

                // Notify Customer
                $payment->user->notify(new ReceiptNotification(
                    $payment,
                    $this->parseElectricityInfo($purchase)
                ));

                // Reward point to User.
                $this->rewardPointToUser($payment, $digitalProduct);
            }

        } catch (Exception $e) {

            $this->handleFailure($e, $discount, $digitalProduct, $payment, $data);

        }

        $job->delete();
    }

    private function handleFailure($e, $discount = null, $digitalProduct = null, $payment = null, $data = [])
    {
        // Mark as failed if we get any exception.
        if (! empty($payment)) {
            $payment->status = PaymentTransaction::STATUS_SUCCESS_NO_PRODUCT_FAILED;
            $payment->save();

            $this->updatePromoCode('reset', $payment, $discount, $digitalProduct);

            DB::connection()->commit();

            // Notify admin for this failure.
            $adminEmails = Config::get('orbit.transaction.notify_emails', ['developer@dominopos.com']);
            foreach($adminEmails as $email) {
                $admin              = new User;
                $admin->email       = $email;
                $admin->notify(new DigitalProductNotAvailableNotification($payment, $e->getMessage()));
            }

            // Notify customer that coupon is not available.
            $payment->user->notify(new CustomerDigitalProductNotAvailableNotification($payment));

            $notes = $e->getMessage();

            GMP::create(Config::get('orbit.partners_api.google_measurement'))
                ->setQueryString([
                    'cid' => time(),
                    't' => 'event',
                    'ea' => 'Purchase Digital Product Failed',
                    'ec' => 'Electricity',
                    'el' => ! empty($digitalProduct) ? $digitalProduct->product_name : '',
                    'cs' => $payment->utm_source,
                    'cm' => $payment->utm_medium,
                    'cn' => $payment->utm_campaign,
                    'ck' => $payment->utm_term,
                    'cc' => $payment->utm_content
                ])
                ->request();

            $payment->user->activity(new PurchaseFailedProductActivity($payment, $this->objectType, $notes));
        }
        else {
            DB::connection()->rollBack();
        }

        $this->log(sprintf(
            "Get {$this->objectType} exception: %s:%s, %s",
            $e->getFile(), $e->getLine(), $e->getMessage()
        ));

        $this->log(serialize($data));
    }

    private function updatePromoCode($status, $payment, $discount = null, $digitalProduct = null)
    {
        if ($status === 'issued') {
            if (! empty($discount) && ! empty($digitalProduct)) {
                // Mark promo code as issued.
                $promoCodeReservation = App::make(ReservationInterface::class);
                $promoData = (object) [
                    'promo_code' => $discount->discount_code,
                    'object_id' => $digitalProduct->digital_product_id,
                    'object_type' => 'digital_product'
                ];
                $promoCodeReservation->markAsIssued($payment->user, $promoData);

                $this->log(sprintf(
                    "Promo code %s issued for purchase %s",
                    $discount->discount_code,
                    $payment->payment_transaction_id
                ));
            }
        }
        else if ($status === 'reset') {
            if (! empty($discount) && ! empty($digitalProduct)) {
                // Mark promo code as available.
                $discountCode = $discount->discount_code;
                $promoCodeReservation = App::make(ReservationInterface::class);
                $promoData = (object) [
                    'promo_code' => $discountCode,
                    'object_id' => $digitalProduct->digital_product_id,
                    'object_type' => 'digital_product'
                ];
                $promoCodeReservation->markAsAvailable($payment->user, $promoData);
                $this->log("Promo code {$discountCode} reverted back/marked as available...");
            }
        }
    }

    private function recordSuccessGMP($payment, $digitalProduct, $providerProduct, $paymentDetail)
    {
        $cid = time();
        // send google analitics event hit
        GMP::create(Config::get('orbit.partners_api.google_measurement'))
            ->setQueryString([
                'cid' => $cid,
                't' => 'event',
                'ea' => 'Purchase Electricity Successful',
                'ec' => 'Electricity',
                'el' => $digitalProduct->product_name,
                'cs' => $payment->utm_source,
                'cm' => $payment->utm_medium,
                'cn' => $payment->utm_campaign,
                'ck' => $payment->utm_term,
                'cc' => $payment->utm_content
            ])
            ->request();

        // send google analitics transaction hit
        GMP::create(Config::get('orbit.partners_api.google_measurement'))
            ->setQueryString([
                'cid' => $cid,
                't' => 'transaction',
                'ti' => $payment->payment_transaction_id,
                'tr' => $payment->amount,
                'cu' => $payment->currency,
            ])
            ->request();

        // send google analitics item hit
        GMP::create(Config::get('orbit.partners_api.google_measurement'))
            ->setQueryString([
                'cid' => $cid,
                't' => 'item',
                'ti' => $payment->payment_transaction_id,
                'in' => $digitalProduct->product_name,
                'ip' => $paymentDetail->price,
                'iq' => $paymentDetail->quantity,
                'ic' => $providerProduct->code,
                'iv' => 'electricity',
                'cu' => $payment->currency,
            ])
            ->request();
    }

    private function recordPendingGMP($payment, $digitalProduct, $providerProduct, $paymentDetail)
    {
        $cid = time();
        GMP::create(Config::get('orbit.partners_api.google_measurement'))
            ->setQueryString([
                'cid' => $cid,
                't' => 'event',
                'ea' => 'Purchase Electricity Successful',
                'ec' => 'Electricity',
                'el' => $digitalProduct->product_name,
                'cs' => $payment->utm_source,
                'cm' => $payment->utm_medium,
                'cn' => $payment->utm_campaign,
                'ck' => $payment->utm_term,
                'cc' => $payment->utm_content
            ])
            ->request();

        if (! is_null($paymentDetail) && ! is_null($digitalProduct)) {
            // send google analitics transaction hit
            GMP::create(Config::get('orbit.partners_api.google_measurement'))
                ->setQueryString([
                    'cid' => $cid,
                    't' => 'transaction',
                    'ti' => $payment->payment_transaction_id,
                    'tr' => $payment->amount,
                    'cu' => $payment->currency,
                ])
                ->request();

            // send google analitics item hit
            GMP::create(Config::get('orbit.partners_api.google_measurement'))
                ->setQueryString([
                    'cid' => $cid,
                    't' => 'item',
                    'ti' => $payment->payment_transaction_id,
                    'in' => $digitalProduct->product_name,
                    'ip' => $paymentDetail->price,
                    'iq' => $paymentDetail->quantity,
                    'ic' => $providerProduct->code,
                    'iv' => 'electricity',
                    'cu' => $payment->currency,
                ])
                ->request();
        }
    }

    private function recordRetryGMP($payment, $data, $digitalProduct)
    {
        GMP::create(Config::get('orbit.partners_api.google_measurement'))
            ->setQueryString([
                'cid' => time(),
                't' => 'event',
                'ea' => 'Purchase Electricity Retry ' . $data['retry'],
                'ec' => 'Electricity',
                'el' => $digitalProduct->product_name,
                'cs' => $payment->utm_source,
                'cm' => $payment->utm_medium,
                'cn' => $payment->utm_campaign,
                'ck' => $payment->utm_term,
                'cc' => $payment->utm_content
            ])
            ->request();
    }

    private function rewardPointToUser($payment, $digitalProduct)
    {
        $rewardObject = (object) [
            'object_id' => $digitalProduct->digital_product_id,
            'object_type' => 'digital_product',
            'object_name' => $digitalProduct->product_name,
            'country_id' => $payment->country_id,
        ];

        Event::fire('orbit.purchase.pulsa.success', [$payment->user, $rewardObject]);
    }

    private function notifyRetryPurchase($payment, $purchase)
    {
        $adminEmails = Config::get('orbit.transaction.notify_emails', [
            'developer@dominopos.com'
        ]);

        // Send notification each time we do retry...
        foreach($adminEmails as $email) {
            $admin              = new User;
            $admin->email       = $email;
            $admin->notify(new PulsaRetryNotification($payment, $purchase->getMessage()));
        }
    }

    private function parseElectricityInfo($purchase)
    {
        $info = [];
        $serialNumber = $purchase->getSerialNumber();

        if (! empty($serialNumber)) {
            $serialNumber = explode('*', $serialNumber);
            $info = [
                'token' => $serialNumber[0],
                'customer' => $serialNumber[1],
                'tarif' => $serialNumber[2],
                'daya' => $serialNumber[3],
                'kwh' => $serialNumber[4],
            ];
        }

        return serialize($info);
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
