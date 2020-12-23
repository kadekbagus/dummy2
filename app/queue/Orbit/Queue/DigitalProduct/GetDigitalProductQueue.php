<?php namespace Orbit\Queue\DigitalProduct;

use Activity;
use App;
use Carbon\Carbon;
use Config;
use DB;
use Event;
use Exception;
use Game;
use Log;
use Mall;
use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts\ReservationInterface;
use Orbit\Controller\API\v1\Pub\Purchase\Activities\PurchaseFailedProductActivity;
use Orbit\Controller\API\v1\Pub\Purchase\Activities\PurchaseSuccessActivity;
use Orbit\Helper\DigitalProduct\Providers\PurchaseProviderInterface;
use Orbit\Helper\GoogleMeasurementProtocol\Client as GMP;
use Orbit\Notifications\DigitalProduct\CustomerDigitalProductNotAvailableNotification;
use Orbit\Notifications\DigitalProduct\DigitalProductNotAvailableNotification;
use Orbit\Notifications\DigitalProduct\PulsaRetryNotification;
use Orbit\Notifications\DigitalProduct\ReceiptNotification;
use PaymentTransaction;
use Queue;
use User;

/**
 * A job to get/issue Digital Product after payment completed.
 * At this point, we assume the payment was completed (paid) so anything wrong
 * while trying to issue the will make the status success_no_product_failed.
 *
 * @author Budi <budi@dominopos.com>
 */
class GetDigitalProductQueue
{
    /**
     * Delay before we trigger another MCash Purchase (in minutes).
     * @var integer
     */
    protected $retryDelay = 3;

    private $objectType = 'digital_product';

    /**
     * Purchased object's name (e.g the game name: Ragnarok M, Candy Crush, etc)
     * @var string
     */
    private $purchasedItem = '';

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
        $digitalProduct = null;

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
            ])->leftJoin('games', 'games.game_id', '=', 'payment_transactions.extra_data')->findOrFail($paymentId);

            // Register payment into container, so can be accessed by other classes.
            App::instance('purchase', $payment);

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
            $digitalProduct = $this->getDigitalProduct($payment);
            $providerProduct = $this->getProviderProduct($payment);
            $productCode = $providerProduct->code;
            $digitalProductId = $digitalProduct->digital_product_id;

            if (! empty($digitalProduct)) {
                $this->objectType = ucwords(str_replace(['_', '-'], ' ', $digitalProduct->product_type));
            }

            $discount = $payment->discount_code;
            $digitalProductName = $digitalProduct->product_name;

            $purchaseData = [
                'payment_transaction_id' => $paymentId,
                'product_code' => $providerProduct->code,
            ];

            $purchase = App::make(PurchaseProviderInterface::class)->purchase($purchaseData);

            // Append noted
            $notes = $payment->notes;
            if (empty($notes)) {
                $notes = '[' . json_encode($purchase->getData()) .']';
            } else {
                $notes = substr_replace($notes, "," . json_encode($purchase->getData()), -1, 0);
            }

            $payment->notes = $notes;
            $detail->payload = $purchase->getData();
            $detail->save();

            if ($purchase->isSuccess()) {
                $payment->status = PaymentTransaction::STATUS_SUCCESS;

                $this->log("Issued for payment {$paymentId}..");

                // Notify Customer.
                $payment->user->notify(new ReceiptNotification(
                    $payment,
                    $purchase->getVoucherData()
                ));

                $cid = time();
                // send google analitics event hit
                GMP::create(Config::get('orbit.partners_api.google_measurement'))
                    ->setQueryString([
                        'cid' => $cid,
                        't' => 'event',
                        'ea' => 'Purchase Digital Product Successful',
                        'ec' => 'game_voucher',
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
                            'iv' => 'game_voucher',
                            'cu' => $payment->currency,
                        ])
                        ->request();
                }

                $payment->user->activity(
                    new PurchaseSuccessActivity($payment, $this->purchasedItem)
                );

                if (! empty($discount)) {
                    // Mark promo code as issued.
                    $promoCodeReservation = App::make(ReservationInterface::class);
                    $promoData = (object) [
                        'promo_code' => $discount->discount_code,
                        'object_id' => $digitalProductId,
                        'object_type' => 'digital_product'
                    ];
                    $promoCodeReservation->markAsIssued($payment->user, $promoData);
                    $this->log("Promo code {$discount->discount_code} issued for purchase {$paymentId}");
                }

                $this->log("Purchase Data: " . serialize($purchaseData));
                $this->log("Purchase Response: " . serialize($purchase->getData()));
            }

            // Pending?

            // Retry?

            // Not used at the moment, moved below.

            else {
                $this->log("Purchase failed for payment {$paymentId}.");
                $this->log("Purchase Data: " . serialize($purchaseData));
                $this->log("Purchase Response: " . serialize($purchase->getData()));
                throw new Exception($purchase->getFailureMessage());
            }

            $payment->save();

            // Commit the changes ASAP.
            DB::connection()->commit();

            // Increase point when the transaction is success.
            if (in_array($payment->status, [PaymentTransaction::STATUS_SUCCESS])) {
                $rewardObject = (object) [
                    'object_id' => $digitalProductId,
                    'object_type' => 'digital_product',
                    'object_name' => $digitalProduct->product_name,
                    'country_id' => $payment->country_id,
                ];

                Event::fire('orbit.purchase.pulsa.success', [$payment->user, $rewardObject]);
            }

        } catch (Exception $e) {

            // Mark as failed if we get any exception.
            if (! empty($payment)) {
                $payment->status = PaymentTransaction::STATUS_SUCCESS_NO_PRODUCT_FAILED;
                $payment->save();

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

                DB::connection()->commit();

                // Notify admin for this failure.
                foreach($adminEmails as $email) {
                    $admin              = new User;
                    $admin->email       = $email;
                    $admin->notify(new DigitalProductNotAvailableNotification($payment, $e->getMessage()));
                }

                // Notify customer that coupon is not available.
                $payment->user->notify(new CustomerDigitalProductNotAvailableNotification($payment));

                $notes = $e->getMessage();

                $digitalProductName = ! empty($digitalProduct) ? $digitalProduct->product_name : '-';

                GMP::create(Config::get('orbit.partners_api.google_measurement'))
                    ->setQueryString([
                        'cid' => time(),
                        't' => 'event',
                        'ea' => 'Purchase Digital Product Failed',
                        'ec' => 'game_voucher',
                        'el' => $digitalProductName,
                        'cs' => $payment->utm_source,
                        'cm' => $payment->utm_medium,
                        'cn' => $payment->utm_campaign,
                        'ck' => $payment->utm_term,
                        'cc' => $payment->utm_content
                    ])
                    ->request();

                $payment->user->activity(
                    new PurchaseFailedProductActivity(
                        $payment,
                        $this->objectType,
                        $notes
                    )
                );
            }
            else {
                DB::connection()->rollBack();
            }

            $this->log(sprintf("Get {$this->objectType} exception: %s:%s, %s", $e->getFile(), $e->getLine(), $e->getMessage()));
            $this->log(serialize($data));
        }

        $job->delete();
    }

    private function getDigitalProduct($payment)
    {
        $digitalProduct = null;
        foreach($payment->details as $detail) {
            if (! empty($detail->digital_product)) {
                $digitalProduct = $detail->digital_product;
                $this->resolvePurchasedItem($payment, $digitalProduct);

                break;
            }
        }

        if (empty($digitalProduct)) {
            throw new Exception("{$this->objectType} for payment {$payment->payment_transaction_id} is not found.", 1);
        }

        return $digitalProduct;
    }

    /**
     * Resolve purchased item name/game name (e.g Ragnarok M, Candy Crush)
     * based on purchased digital product type.
     *
     * @param  [type] $payment        [description]
     * @param  [type] $digitalProduct [description]
     * @return [type]                 [description]
     */
    private function resolvePurchasedItem($payment, $digitalProduct)
    {
        switch ($digitalProduct->product_type) {
            case 'game_voucher':
                $this->purchasedItem = Game::findOrFail($payment->extra_data)
                    ->game_name;
                break;

            case 'electricity':
                break;

            default:
                break;
        }
    }

    private function getProviderProduct($payment)
    {
        $providerProduct = null;

        foreach($payment->details as $detail) {
            if (! empty($detail->provider_product)) {
                $providerProduct = $detail->provider_product;
                break;
            }
        }

        if (empty($providerProduct)) {
            throw new Exception('provider product not found!');
        }

        return $providerProduct;
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
