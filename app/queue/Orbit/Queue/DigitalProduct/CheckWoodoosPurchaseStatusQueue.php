<?php namespace Orbit\Queue\DigitalProduct;

use DB;
use App;
use Log;
use Mall;
use User;
use Event;
use Config;
use Exception;
use PaymentTransaction;
use Orbit\Helper\GoogleMeasurementProtocol\Client as GMP;
use Orbit\Controller\API\v1\Pub\Purchase\DigitalProduct\APIHelper;
use Orbit\Notifications\DigitalProduct\Woodoos\ReceiptNotification;
use Orbit\Helper\DigitalProduct\Providers\PurchaseProviderInterface;
use Orbit\Controller\API\v1\Pub\Purchase\Activities\PurchaseSuccessActivity;
use Orbit\Notifications\DigitalProduct\DigitalProductNotAvailableNotification;
use Orbit\Controller\API\v1\Pub\Purchase\Activities\PurchaseFailedProductActivity;
use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts\ReservationInterface;
use Orbit\Notifications\DigitalProduct\CustomerDigitalProductNotAvailableNotification;

/**
 * A job to get/issue Hot Deals Coupon after payment completed.
 * At this point, we assume the payment was completed (paid) so anything wrong
 * while trying to issue the coupon will make the status success_no_coupon_failed.
 *
 * @author Budi <budi@dominopos.com>
 */
class CheckWoodoosPurchaseStatusQueue
{
    use APIHelper;

    /**
     * Delay before we trigger another MCash Purchase (in minutes).
     * @var integer
     */
    protected $retryDelay = 1;

    private $objectType = 'digital_product';

    /**
     * Issue hot deals coupon.
     *
     * @param  Illuminate\Queue\Jobs\Job | Orbit\FakeJob $job  the job
     * @param  array $data the data needed to run this job
     * @return void
     */
    public function fire($job, $data)
    {
        $data['checkCounter']++;

        try {

            $purchaseStatus = App::make(PurchaseProviderInterface::class, [
                    'providerId' => 'woodoos'
                ])->status($data['paymentId']);

            if ($purchaseStatus->isSuccess()) {

            }

            if ($data['checkCounter'] <= $this->maxCheck) {
                Queue::later(
                    $data['retryDelay'],
                    "Orbit\Queue\DigitalProduct\CheckWoodoosPurchaseStatusQueue",
                    $data
                );
            }

        } catch (Exception $e) {
            $this->log(sprintf(
                "Check Woodoos Purchase Status Exception: %s:%s >> %s",
                $e->getFile(),
                $e->getLine(),
                $e->getMessage()
            ));
        }

        $job->delete();
    }

    private function getDigitalProduct($payment)
    {
        $digitalProduct = null;
        foreach($payment->details as $detail) {
            if (! empty($detail->digital_product)) {
                $digitalProduct = $detail->digital_product;
                break;
            }
        }

        if (empty($digitalProduct)) {
            throw new Exception("{$this->objectType} for payment {$payment->payment_transaction_id} is not found.", 1);
        }

        return $digitalProduct;
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
