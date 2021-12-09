<?php

namespace Orbit\Queue\Bill;

use App;
use Config;
use DB;
use Event;
use Exception;
use Log;
use Mall;
use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts\ReservationInterface;
use Orbit\Controller\API\v1\Pub\Purchase\Activities\PurchaseFailedProductActivity;
use Orbit\Controller\API\v1\Pub\Purchase\Activities\PurchaseSuccessActivity;
use Orbit\Helper\AutoIssueCoupon\AutoIssueCoupon;
use Orbit\Helper\AutoIssueCoupon\AutoIssueGamePromotion;
use Orbit\Helper\GoogleMeasurementProtocol\Client as GMP;
use Orbit\Helper\MCash\API\BillInterface;
use Orbit\Notifications\DigitalProduct\CustomerDigitalProductNotAvailableNotification;
use Orbit\Notifications\DigitalProduct\DigitalProductNotAvailableNotification;
use PaymentTransaction;
use User;

/**
 * A base job class to pay bill after payment completed.
 *
 * @author Budi <budi@dominopos.com>
 */
abstract class PayBillQueue
{
    /**
     * Delay before we trigger another MCash Purchase (in minutes).
     * @var integer
     */
    protected $retryDelay = 3;

    protected $objectType = 'digital_product';

    protected $billType = null;

    protected $GMPId = null;

    /**
     * The job handler.
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

        if (! isset($data['retry'])) {
            $data['retry'] = 0;
        }

        try {
            DB::connection()->beginTransaction();

            $paymentId = $data['paymentId'];

            $this->log("Paying bill for PaymentID: {$paymentId}");

            $payment = PaymentTransaction::onWriteConnection()->with([
                'details.digital_product',
                'details.provider_product',
                'user',
                'midtrans',
                'discount_code'
            ])
            ->select('payment_transactions.*', 'games.game_name')
            ->leftJoin('games', 'games.game_id', '=', 'payment_transactions.extra_data')
            ->lockForUpdate()
            ->findOrFail($paymentId);

            // Register payment into container, so can be accessed by other classes.
            App::instance('purchase', $payment);

            // Dont issue coupon if after some delay the payment was canceled.
            if ($payment->denied() || $payment->failed() || $payment->expired() || $payment->canceled()
                || $payment->status === PaymentTransaction::STATUS_SUCCESS_NO_PRODUCT_FAILED
                || $payment->status === PaymentTransaction::STATUS_SUCCESS_REFUND
                || $payment->status === PaymentTransaction::STATUS_SUCCESS
            ) {

                $this->log("Payment {$paymentId} was already success/denied/canceled/failed/refunded. We should not issue any item.");

                DB::connection()->commit();

                $job->delete();

                return;
            }

            $detail = $payment->details->filter(function($detail) {
                return $detail->object_type !== 'discount';
            })->first();

            $digitalProduct = $payment->getDigitalProduct();
            $providerProduct = $payment->getProviderProduct();
            $productCode = $providerProduct->code;
            $digitalProductId = $digitalProduct->digital_product_id;
            $this->billType = $providerProduct->product_type;

            if (! empty($digitalProduct)) {
                $this->objectType = ucwords(str_replace(['_', '-'], ' ', $digitalProduct->product_type));
            }

            $discount = $payment->discount_code;
            $digitalProductName = $digitalProduct->product_name;

            $paymentParams = $this->buildPaymentParams(
                $payment,
                $productCode,
                $detail
            );

            $billPayment = App::make(BillInterface::class, [
                'billType' => $this->billType
            ])->pay($paymentParams);

            if ($billPayment->isSuccess()) {

                // At this point, 'notes' should contain inquiry response.
                // Now we add bill payment response to the notes.
                $notes = unserialize($payment->notes);
                $notes['payment'] = $billPayment->getData();

                $payment->notes = serialize($notes);
                $payment->status = PaymentTransaction::STATUS_SUCCESS;

                $payment->save();

                // Commit the changes ASAP.
                DB::connection()->commit();

                $this->log("Bill paid for payment {$paymentId}..");

                // Auto issue free coupon if trx meet certain criteria.
                // AutoIssueCoupon::issue($payment, $digitalProduct->product_type);

                // Auto issue free game voucher promotion if eligible.
                // AutoIssueGamePromotion::issue($payment, $providerProduct);

                // Notify Customer.
                $this->notifyReceipt($payment);

                $this->recordSuccessGMP(
                    $digitalProductName,
                    $payment,
                    $detail,
                    $digitalProduct,
                    $productCode
                );

                $this->recordSuccessActivity($payment);

                // $this->markPromoCodeAsIssued($payment, $discount, $digitalProduct);

                $this->log("Bill payment Data: " . serialize($paymentParams));
                $this->log("Bill payment Response: " . serialize($billPayment->getData()));
            }
            else {
                $this->log("Bill payment failed for payment {$paymentId}.");
                $this->log("Bill payment Data: " . serialize($paymentParams));
                $this->log("Bill payment Response: " . serialize($billPayment->getData()));
                throw new Exception($billPayment->getFailureMessage());
            }

            // $this->rewardUserPoint($payment, $digitalProduct);

        } catch (Exception $e) {

            // Mark as failed if we get any exception.
            if (! empty($payment)) {

                if (isset($billPayment) && ! empty($billPayment)) {
                    // At this point, 'notes' should contain inquiry response.
                    // Now we add bill payment response to the notes.
                    $notes = unserialize($payment->notes);
                    $notes['payment'] = $billPayment->getData();

                    $payment->notes = serialize($notes);
                }

                $payment->status = PaymentTransaction::STATUS_SUCCESS_NO_PRODUCT_FAILED;
                $payment->save();

                // $this->markPromoCodeAsAvailable(
                //     $payment,
                //     $discount,
                //     $digitalProduct
                // );

                DB::connection()->commit();

                // $this->notifyFailed($payment, $e);

                $notes = $e->getMessage();

                $digitalProductName = ! empty($digitalProduct) ? $digitalProduct->product_name : '-';

                // $this->recordFailedGMP(
                //     $digitalProductName,
                //     $payment
                // );

                // $this->recordFailedActivity($payment, $notes);
            }
            else {
                DB::connection()->rollBack();
            }

            $this->log(sprintf("Get {$this->objectType} exception: %s:%s, %s", $e->getFile(), $e->getLine(), $e->getMessage()));
            $this->log(serialize($data));
        }

        $job->delete();
    }

    abstract protected function notifyReceipt($payment);

    protected function buildPaymentParams($payment, $productCode, $detail)
    {
        return [
            'product' => $productCode,
            'customer' => $payment->extra_data,
            'partnerTrxId' => $payment->payment_transaction_id,
            'amount' => (int) $detail->vendor_price + (int) $detail->provider_fee,
        ];
    }

    protected function notifyFailed($payment, $e)
    {
        $adminEmails = Config::get('orbit.transaction.notify_emails', ['developer@dominopos.com']);

        // Notify admin for this failure.
        foreach($adminEmails as $email) {
            $admin              = new User;
            $admin->email       = $email;
            $admin->notify(new DigitalProductNotAvailableNotification($payment, $e->getMessage()));
        }

        // Notify customer that coupon is not available.
        $payment->user->notify(new CustomerDigitalProductNotAvailableNotification($payment));
    }

    protected function recordSuccessActivity($payment)
    {
        $payment->user->activity(new PurchaseSuccessActivity($payment, $this->objectType));
    }

    protected function recordFailedActivity($payment, $notes)
    {
        $payment->user->activity(new PurchaseFailedProductActivity(
            $payment,
            $this->objectType,
            $notes
        ));
    }

    protected function recordSuccessGMP(
        $digitalProductName,
        $payment,
        $detail,
        $digitalProduct,
        $productCode
    ) {
        $cid = time();
        // send google analitics event hit
        GMP::create(Config::get('orbit.partners_api.google_measurement'))
            ->setQueryString([
                'cid' => $cid,
                't' => 'event',
                'ea' => $this->GMPId . ' Payment Successful',
                'ec' => $this->billType,
                'el' => $digitalProductName,
                'cs' => $payment->utm_source,
                'cm' => $payment->utm_medium,
                'cn' => $payment->utm_campaign,
                'ck' => $payment->utm_term,
                'cc' => $payment->utm_content
            ])
            ->request();

        if (! is_null($detail) && ! is_null($digitalProduct)) {
            // send google analitics transaction hit
            GMP::create(Config::get('orbit.partners_api.google_measurement'))
                ->setQueryString([
                    'cid' => $cid,
                    't' => 'transaction',
                    'ti' => $payment->payment_transaction_id,
                    'tr' => $payment->amount,
                    'cu' => $payment->currency,
                    'cd4' => $payment->payment_method,
                ])
                ->request();

            // send google analitics item hit
            GMP::create(Config::get('orbit.partners_api.google_measurement'))
                ->setQueryString([
                    'cid' => $cid,
                    't' => 'item',
                    'ti' => $payment->payment_transaction_id,
                    'in' => $digitalProductName,
                    'ip' => $detail->price,
                    'iq' => $detail->quantity,
                    'ic' => $productCode,
                    'iv' => $this->billType,
                    'cu' => $payment->currency,
                ])
                ->request();
        }
    }

    protected function recordFailedGMP(
        $digitalProductName,
        $payment
    ) {
        GMP::create(Config::get('orbit.partners_api.google_measurement'))
            ->setQueryString([
                'cid' => time(),
                't' => 'event',
                'ea' => $this->GMPId . ' Payment Failed',
                'ec' => $this->billType,
                'el' => $digitalProductName,
                'cs' => $payment->utm_source,
                'cm' => $payment->utm_medium,
                'cn' => $payment->utm_campaign,
                'ck' => $payment->utm_term,
                'cc' => $payment->utm_content
            ])
            ->request();
    }

    protected function rewardUserPoint($payment, $digitalProduct)
    {
        // Increase point when the transaction is success.
        if (in_array($payment->status, [PaymentTransaction::STATUS_SUCCESS])) {
            $rewardObject = (object) [
                'object_id' => $digitalProduct->digital_product_id,
                'object_type' => 'digital_product',
                'object_name' => $digitalProduct->product_name,
                'country_id' => $payment->country_id,
            ];

            Event::fire('orbit.purchase.pulsa.success', [$payment->user, $rewardObject]);
        }
    }

    protected function markPromoCodeAsIssued($payment, $discount, $digitalProduct)
    {
        if (! empty($discount)) {
            // Mark promo code as issued.
            $promoCodeReservation = App::make(ReservationInterface::class);
            $promoData = (object) [
                'promo_code' => $discount->discount_code,
                'object_id' => $digitalProduct->digital_product_id,
                'object_type' => 'digital_product'
            ];
            $promoCodeReservation->markAsIssued($payment->user, $promoData);
            $this->log("Promo code {$discount->discount_code} issued for bill payment {$paymentId}");
        }
    }

    protected function markPromoCodeAsAvailable(
        $payment,
        $discount,
        $digitalProduct
    ) {
        if (! empty($discount) && ! empty($digitalProduct)) {
            // Mark promo code as available.
            $discountCode = $discount->discount_code;
            $promoCodeReservation = App::make(ReservationInterface::class);
            $promoData = (object) [
                'promo_code' => $discountCode,
                'object_id' => $digitalProductId,
                'object_type' => 'digital_product'
            ];
            $promoCodeReservation->markAsAvailable($payment->user, $promoData);
            $this->log("Promo code {$discountCode} reverted back/marked as available...");
        }
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
