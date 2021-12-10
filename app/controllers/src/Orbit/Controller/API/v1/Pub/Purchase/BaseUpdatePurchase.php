<?php

namespace Orbit\Controller\API\v1\Pub\Purchase;

use App;
use Carbon\Carbon;
use Config;
use DB;
use Event;
use Exception;
use Log;
use Mall;
use OrbitShop\API\v1\CommonAPIControllerTrait;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Orbit\Controller\API\v1\Pub\Purchase\Activities\PurchaseAbortedActivity;
use Orbit\Controller\API\v1\Pub\Purchase\Activities\PurchaseCanceledActivity;
use Orbit\Controller\API\v1\Pub\Purchase\Activities\PurchaseExpiredActivity;
use Orbit\Controller\API\v1\Pub\Purchase\Activities\PurchaseFailedActivity;
use Orbit\Controller\API\v1\Pub\Purchase\Activities\PurchasePendingActivity;
use Orbit\Controller\API\v1\Pub\Purchase\Activities\PurchaseProcessingProductActivity;
use Orbit\Helper\Midtrans\API\TransactionStatus;
use Orbit\Notifications\DigitalProduct\AbortedPaymentNotification;
use Orbit\Notifications\DigitalProduct\CanceledPaymentNotification;
use Orbit\Notifications\DigitalProduct\CustomerRefundNotification;
use Orbit\Notifications\DigitalProduct\Electricity\AbortedPaymentNotification as ElectricityAbortedPaymentNotification;
use Orbit\Notifications\DigitalProduct\Electricity\CanceledPaymentNotification as ElectricityCanceledPaymentNotification;
use Orbit\Notifications\DigitalProduct\Electricity\ExpiredPaymentNotification as ElectricityExpiredPaymentNotification;
use Orbit\Notifications\DigitalProduct\Electricity\PendingPaymentNotification as ElectricityPendingPaymentNotification;
use Orbit\Notifications\DigitalProduct\ExpiredPaymentNotification;
use Orbit\Notifications\DigitalProduct\PendingPaymentNotification;
use Orbit\Notifications\DigitalProduct\Woodoos\AbortedPaymentNotification as WoodoosAbortedPaymentNotification;
use Orbit\Notifications\DigitalProduct\Woodoos\CanceledPaymentNotification as WoodoosCanceledPaymentNotification;
use Orbit\Notifications\DigitalProduct\Woodoos\ExpiredPaymentNotification as WoodoosExpiredPaymentNotification;
use Orbit\Notifications\DigitalProduct\Woodoos\PendingPaymentNotification as WoodoosPendingPaymentNotification;
use PaymentTransaction;
use Queue;
use User;


/**
 * Base Digital Product Purchase Update handler.
 *
 * @author Budi <budi@gotomalls.com>
 */
class BaseUpdatePurchase
{
    use CommonAPIControllerTrait;

    protected $objectType = 'digital_product';

    protected $objectName = '';

    protected $request = null;

    protected $purchase = null;

    protected $refundData = null;

    protected $fromSnap = false;

    protected $shouldNotifyRefund = false;

    protected $refundReason = '';

    protected $mall = null;

    protected $currentUtmUrl = '';

    protected $useTransaction = true;

    protected $onBeforeCommit = [];

    protected $onAfterCommit = [];

    protected function init($request)
    {
        $this->initRequest($request);

        $this->initUser($request);

        $this->initPurchase($request);

        $this->generateUtmUrl();

        $this->resolveObjectType();

        $this->mall = Mall::where('merchant_id', $request->mall)->first();
    }

    protected function initRequest($request)
    {
        $this->request = $request;
        $this->refundData = $request->refund_data;
        $this->fromSnap = $request->from_snap ?: false;
    }

    protected function initUser($request)
    {
        $this->user = App::make('currentUser');
    }

    protected function initPurchase($request)
    {
        $this->purchase = PaymentTransaction::onWriteConnection()->with([
                'details.digital_product',
                'details.provider_product',
                'refunds',
                'midtrans',
                'user',
                'discount_code'
            ])->findOrFail($request->payment_transaction_id);
    }

    protected function generateUtmUrl()
    {
        $utm_source = (isset($this->purchase->utm_source)) ? $this->purchase->utm_source : '';
        $utm_medium = (isset($this->purchase->utm_medium)) ? $this->purchase->utm_medium : '';
        $utm_term = (isset($this->purchase->utm_term)) ? $this->purchase->utm_term : '';
        $utm_content = (isset($this->purchase->utm_content)) ? $this->purchase->utm_content : '';
        $utm_campaign = (isset($this->purchase->utm_campaign)) ? $this->purchase->utm_campaign : '';

        $this->utmUrl = '?utm_source='.$utm_source.'&utm_medium='.$utm_medium.'&utm_term='.$utm_term.'&utm_content='.$utm_content.'&utm_campaign='.$utm_campaign;
    }

    protected function getSuccessStatus()
    {
        return [
            PaymentTransaction::STATUS_SUCCESS_NO_PRODUCT,
            PaymentTransaction::STATUS_SUCCESS_NO_PRODUCT_FAILED,
            PaymentTransaction::STATUS_SUCCESS_REFUND
        ];
    }

    protected function getFinalStatus()
    {
        // List of status which considered as final (should not be changed again except some conditions met).
        return [
            PaymentTransaction::STATUS_SUCCESS,
            PaymentTransaction::STATUS_SUCCESS_NO_PRODUCT,
            PaymentTransaction::STATUS_SUCCESS_NO_PRODUCT_FAILED,
            PaymentTransaction::STATUS_EXPIRED,
            PaymentTransaction::STATUS_FAILED,
            PaymentTransaction::STATUS_DENIED,
            PaymentTransaction::STATUS_CANCELED,
            PaymentTransaction::STATUS_ABORTED,
            PaymentTransaction::STATUS_SUCCESS_REFUND,
        ];
    }

    protected function log($message = '')
    {
        Log::info("{$this->objectType}: {$message}");
    }

    /**
     * Resolve object type of item that being purchased.
     *
     * @return [type] [description]
     */
    protected function resolveObjectType()
    {
        foreach($this->purchase->details as $detail) {
            if (! empty($detail->digital_product)) {
                $this->objectType = ucwords(
                    str_replace(
                        '_',
                        ' ',
                        $detail->digital_product->product_type
                    )
                );
                break;
            }
        }
    }

    /**
     * Resolve object name.
     *
     * @return [type] [description]
     */
    protected function resolveObjectName()
    {
        $this->objectName = 'unknown object name';
        foreach($this->purchase->details as $detail) {
            if (! empty($detail->digital_product)) {
                $this->objectName = $detail->object_name;
                break;
            }
        }
    }

    protected function determineShouldUpdateOrNot()
    {
        $shouldUpdate = false;
        $currentStatus = $this->purchase->status;
        $newStatus = $this->request->status;

        $finalStatus = $this->getFinalStatus();
        $successStatus = $this->getSuccessStatus();

        // If current payment was success (but no product/processing/refunded)
        // then assume it as 'success', so we can compare it with new requested
        // status (bcs frontend/midtrans only know one 'success')
        $tempCurrentStatus = $currentStatus;
        if (in_array($currentStatus, $successStatus)) {
            $tempCurrentStatus = PaymentTransaction::STATUS_SUCCESS;
        }

        if (
            in_array($currentStatus, $finalStatus)
            && $tempCurrentStatus !== $newStatus
        ) {
            $this->log("Payment {$this->purchase->payment_transaction_id} was marked as FINAL, but there is new request to change status to {$newStatus}");

            // If it is a refund request, then try to record it..
            if (in_array($newStatus, ['refund', 'partial_refund']) && ! empty($this->refundData)) {
                $this->handleRefundedPurchase($this->refundData);
            }
            else {
                $this->log("Getting correct status from Midtrans for payment {$this->purchase->payment_transaction_id}...");
                $transactionStatus = TransactionStatus::create()->getStatus($this->purchase->payment_transaction_id);
                $transactionData = $transactionStatus->getData();
                $internalStatus = $transactionStatus->mapToInternalStatus();
                // If the new status doesnt match with what midtrans gave us, then
                // we can ignored this request (dont update).
                if ($newStatus !== $internalStatus) {
                    $this->log(sprintf(
                        "New status %s for payment %s will be IGNORED since the midtrans status is %s (mapped to: %s) !",
                        $newStatus,
                        $this->purchase->payment_transaction_id,
                        $transactionData->transaction_status,
                        $internalStatus
                    ));
                }
                else {
                    $this->log("New status {$newStatus} for payment {$this->purchase->payment_transaction_id} will be set!");
                    $shouldUpdate = true;
                }
            }
        }
        else if (! in_array($currentStatus, $finalStatus)) {
            if ($newStatus === PaymentTransaction::STATUS_ABORTED) {
                // If status is aborted, then check if the transaction is in pending (exists) or not in Midtrans.
                // If doesn't exist, assume the payment is starting and we can abort it.
                // Otherwise, assume it is pending and we should not update the statusad.
                $this->log("Request to abort payment {$this->purchase->payment_transaction_id}...");
                $this->log("Checking transaction {$this->purchase->payment_transaction_id} status in Midtrans...");
                $transactionStatus = TransactionStatus::create()->getStatus($this->purchase->payment_transaction_id);
                if ($transactionStatus->notFound()) {
                    $this->log("Transaction {$this->purchase->payment_transaction_id} not found! Aborting payment...");
                    $shouldUpdate = true;
                }
                else {
                    $this->log("Transaction {$this->purchase->payment_transaction_id} found! Payment can not be aborted/canceled.");
                }
            }
            else {
                $shouldUpdate = true;
            }
        }
        else {
            $this->log("Payment {$this->purchase->payment_transaction_id} is good. Nothing to do.");
            $this->commit();
        }

        return [$shouldUpdate, $currentStatus, $newStatus];
    }

    protected function handleSuccessPurchase($newStatus)
    {
        // If payment was success, set purchase state to 'processing'
        if ($newStatus === PaymentTransaction::STATUS_SUCCESS) {
            if ($this->purchase->forDigitalProduct()) {
                $this->purchase->status = PaymentTransaction::STATUS_SUCCESS_NO_PRODUCT;
            }
        }
    }

    protected function handleAbortedPurchase($currentStatus, $newStatus)
    {
        // If new status is 'aborted' and customer forcibly closed the window,
        // then keep it as 'starting'. Otherwise, assume customer closed
        // snapjs/payment window so we have to set purchase status as is,
        // notify customer and then record the activity.
        if ($currentStatus === PaymentTransaction::STATUS_STARTING
            && $newStatus === PaymentTransaction::STATUS_ABORTED
        ) {
            if (! $this->fromSnap) {
                $this->purchase->status = PaymentTransaction::STATUS_STARTING;
            }

            if ($this->fromSnap) {
                // Add an after commit callback that should send notification
                // and record abort activity.
                $this->onAfterCommit[] = [
                    'args' => [],
                    'fn' => function() {
                        $this->notifyAbortedPurchase();
                        $this->recordAbortedPurchaseActivity();
                    },
                ];
            }
        }
    }

    protected function notifyAbortedPurchase()
    {
        if ($this->purchase->forWoodoos()) {
            $this->purchase->user->notify(
                new WoodoosAbortedPaymentNotification($this->purchase)
            );
        }
        else if ($this->purchase->forMCashElectricity()) {
            $this->purchase->user->notify(
                new ElectricityAbortedPaymentNotification($this->purchase)
            );
        }
        else {
            $this->purchase->user->notify(
                new AbortedPaymentNotification($this->purchase)
            );
        }
    }

    protected function recordAbortedPurchaseActivity()
    {
        $this->purchase->user->activity(new PurchaseAbortedActivity(
                $this->purchase, $this->objectName
            ));
    }

    protected function handlePendingPurchase($currentStatus, $newStatus)
    {
        if ($currentStatus === PaymentTransaction::STATUS_STARTING
            && $newStatus === PaymentTransaction::STATUS_PENDING
        ) {
            // Start transaction status checking which will run for a certain
            // period of time, in case we haven't received the HTTP Notification
            // callback from Midtrans.
            $this->startTransactionStatusCheckingQueue();

            $this->notifyPendingPurchase();

            $this->recordPendingPurchaseActivity();
        }
    }

    protected function notifyPendingPurchase()
    {
        // Notify customer to complete the payment.
        // Send email to address that being used on checkout (can be different with user's email)
        $paymentUser = new User;
        $paymentUser->email = $this->purchase->user_email;

        if ($this->purchase->forMCashElectricity()) {
            $paymentUser->notify(
                new ElectricityPendingPaymentNotification($this->purchase),
                30
            );
        }
        else if ($this->purchase->forWoodoos()) {
            $paymentUser->notify(
                new WoodoosPendingPaymentNotification($this->purchase),
                30
            );
        }
        else {
            $paymentUser->notify(
                new PendingPaymentNotification($this->purchase),
                30
            );
        }
    }

    protected function recordPendingPurchaseActivity()
    {
        // Record activity of pending purchase...
        $this->purchase->user->activity(
            new PurchasePendingActivity($this->purchase, $this->objectName)
        );
    }

    protected function handleCancelledPurchase($currentStatus, $newStatus)
    {
        // Send notification if the purchase was canceled.
        // Only send if previous status was pending.
        if ($currentStatus === PaymentTransaction::STATUS_PENDING
            && $newStatus === PaymentTransaction::STATUS_CANCELED
        ) {
            $this->notifyCancelledPurchase();

            $this->recordCancelledPurchaseActivity();
        }
    }

    protected function notifyCancelledPurchase()
    {
        if ($this->purchase->forWoodoos()) {
            $this->purchase->user->notify(
                new WoodoosCanceledPaymentNotification($this->purchase)
            );
        }
        else if ($this->purchase->forMCashElectricity()) {
            $this->purchase->user->notify(
                new ElectricityCanceledPaymentNotification($this->purchase)
            );
        }
        else {
            $this->purchase->user->notify(
                new CanceledPaymentNotification($this->purchase)
            );
        }
    }

    protected function recordCancelledPurchaseActivity()
    {
        $this->purchase->user->activity(
            new PurchaseCanceledActivity($this->purchase, $this->objectName)
        );
    }

    protected function handleExpiredPurchase($currentStatus, $newStatus)
    {
        // Send notification if the purchase was expired
        // Only send if previous status was pending.
        if ($currentStatus === PaymentTransaction::STATUS_PENDING
            && $newStatus === PaymentTransaction::STATUS_EXPIRED
        ) {
            $this->notifyExpiredPurchase();

            $this->recordExpiredPurchaseActivity();
        }
    }

    protected function notifyExpiredPurchase()
    {
        if ($this->purchase->forWoodoos()) {
            $this->purchase->user->notify(
                new WoodoosExpiredPaymentNotification($this->purchase)
            );
        }
        else if ($this->purchase->forMCashElectricity()) {
            $this->purchase->user->notify(
                new ElectricityExpiredPaymentNotification($this->purchase)
            );
        }
        else {
            $this->purchase->user->notify(
                new ExpiredPaymentNotification($this->purchase)
            );
        }
    }

    protected function recordExpiredPurchaseActivity()
    {
        $this->purchase->user->activity(
            new PurchaseExpiredActivity($this->purchase, $this->objectName)
        );
    }

    protected function handleFailedPurchase($currentStatus, $newStatus)
    {
        if ($this->purchase->failed() || $this->purchase->denied()) {
            $this->recordFailedPurchaseActivity();
        }
    }

    protected function recordFailedPurchaseActivity()
    {
        $this->purchase->user->activity(
            new PurchaseFailedActivity($this->purchase)
        );
    }

    protected function handleProcessingPurchase()
    {
        if ($this->purchase->status === PaymentTransaction::STATUS_SUCCESS_NO_PRODUCT) {
            $this->recordProcessingPurchaseActivity();
        }
    }

    protected function recordProcessingPurchaseActivity()
    {
        $this->purchase->user->activity(
            new PurchaseProcessingProductActivity(
                $this->purchase, $this->objectName, $this->objectType
            )
        );
    }

    protected function handleRefundedPurchase($refundData)
    {
        $this->recordRefund($refundData);

        // Add an after commit callback that will send notification about
        // refunded purchase to the customer.
        $this->onAfterCommit[] = [
            'args' => [],
            'fn' => function() {
                $this->notifyRefundedPurchase();
            }
        ];
    }

    protected function notifyRefundedPurchase()
    {
        // Send refund notification to customer.
        if ($this->shouldNotifyRefund) {
            $this->purchase->user->notify(
                new CustomerRefundNotification(
                    $this->purchase, $this->refundReason
                )
            );
        }
    }

    protected function recordRefund($refundData)
    {
        $this->log("It is a refund notification...");

        $refundData = json_decode($refundData, true);
        $refundDataObject = new \stdClass;
        $refundDataObject->refunds = [];
        foreach($refundData['refunds'] as $refund) {
            $refundDataObject->refunds[] = (object) $refund;
        }
        $refundDataObject->refund_amount = $refundData['refund_amount'];

        $refundList = $this->purchase->recordRefund($refundDataObject);

        if (count($refundList) > 0) {
            $this->purchase->status = PaymentTransaction::STATUS_SUCCESS_REFUND;
            $this->purchase->save();
            $this->shouldNotifyRefund = true;
            $this->refundReason = isset($refundList[0]) && isset($refundList[0]->reason)
                ? $refundList[0]->reason
                : '';
        }
    }

    protected function startTransactionStatusCheckingQueue()
    {
        $delay = Config::get('orbit.partners_api.midtrans.transaction_status_timeout', 60);
        $queueData = [
            'transactionId' => $this->purchase->payment_transaction_id,
            'check' => 0,
            'current_url' => $this->currentUtmUrl
        ];

        if (! empty($this->mall)) {
            $queueData['mall_id'] = $this->mall->merchant_id;
        }

        Queue::later(
            $delay,
            "Orbit\Queue\Payment\Midtrans\CheckTransactionStatusQueue",
            $queueData
        );

        $this->log(
            sprintf(
                'First time TransactionStatus check for Payment: %s  is scheduled to run after %s seconds.',
                $this->purchase->payment_transaction_id,
                $delay
            )
        );
    }

    protected function updatePaymentTransaction(
        $shouldUpdate,
        $currentStatus,
        $newStatus,
        $request
    ) {
        if ($shouldUpdate) {

            $this->purchase->status = $newStatus;

            $this->purchase->responded_at = Carbon::now('UTC');

            OrbitInput::post('external_payment_transaction_id', function($external_payment_transaction_id) {
                $this->purchase->external_payment_transaction_id = $external_payment_transaction_id;
            });

            OrbitInput::post('provider_response_code', function($provider_response_code) {
                $this->purchase->provider_response_code = $provider_response_code;
            });

            OrbitInput::post('provider_response_message', function($provider_response_message) {
                $this->purchase->provider_response_message = $provider_response_message;
            });

            OrbitInput::post('payment_midtrans_info', function($payment_midtrans_info) {
                $this->purchase->midtrans->payment_midtrans_info = serialize($payment_midtrans_info);
                $this->purchase->midtrans->save();
            });

            OrbitInput::post('payment_method', function($paymentMethod) {
                $this->purchase->payment_method = $paymentMethod;
            });

            // Try handling success purchase
            $this->handleSuccessPurchase($newStatus);

            // Try handling aborted purchase
            $this->handleAbortedPurchase($currentStatus, $newStatus);

            $this->purchase->save();

            // Commit the changes ASAP so if there are any other requests that trigger this controller
            // they will use the updated payment data/status.
            // Try not doing any expensive operation above.
            $this->commit();

            $this->purchase->current_utm_url = $this->currentUtmUrl;

            $this->resolveObjectName();
        }
        else {
            OrbitInput::post('payment_method', function($paymentMethod) {
                $this->purchase->payment_method = $paymentMethod;
            });

            $this->purchase->save();

            $this->commit();
        }
    }

    protected function beforeCommit()
    {
        if (! empty($this->onBeforeCommit)) {
            foreach($this->onBeforeCommit as $beforeCommit) {
                call_user_func_arary($beforeCommit['fn'], $beforeCommit['args']);
            }
        }
    }

    protected function afterCommit()
    {
        Event::fire('orbit.payment.postupdatepayment.after.commit', [
            $this->purchase,
            $this->mall,
            []
        ]);

        if (! empty($this->onAfterCommit)) {
            foreach($this->onAfterCommit as $afterCommit) {
                call_user_func_arary($afterCommit['fn'], $afterCommit['args']);
            }
        }
    }

    protected function formatPurchase()
    {
        unset(
            $this->purchase->notes,
            $this->purchase->user,
            $this->purchase->details,
            $this->purchase->midtrans
        );
    }

    public function update($request)
    {
        // Here we separate the update block with after-commit block, so that
        // if there is an exception after committing, it wont trigger db rollback
        // on main controller exception handler.
        try {
            $this->beginTransaction();

            $this->init($request);

            list($shouldUpdate, $currentStatus, $newStatus) =
                $this->determineShouldUpdateOrNot();

            $this->updatePaymentTransaction(
                $shouldUpdate,
                $currentStatus,
                $newStatus,
                $request
            );

        } catch (Exception $e) {
            $this->rollBack();

            throw $e;
        }

        // Run any callback/functions after commit...
        $this->afterCommit();

        // Try handling some purchase states
        $this->handleFailedPurchase($currentStatus, $newStatus);

        $this->handleProcessingPurchase();

        $this->handlePendingPurchase($currentStatus, $newStatus);

        $this->handleCancelledPurchase($currentStatus, $newStatus);

        $this->handleExpiredPurchase($currentStatus, $newStatus);

        $this->formatPurchase();

        return $this->purchase;
    }
}
