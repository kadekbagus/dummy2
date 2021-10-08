<?php

namespace Orbit\Controller\API\v1\Pub\Purchase\Order;

use App;
use DB;
use Log;
use Mall;
use User;
use Event;
use Queue;
use Config;
use Exception;
use Carbon\Carbon;
use PaymentTransaction;
use Orbit\Helper\Cart\CartInterface;
use Orbit\Helper\Midtrans\API\TransactionStatus;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Orbit\Notifications\Order\AbortedPaymentNotification;
use Orbit\Notifications\Order\CustomerRefundNotification;
use Orbit\Notifications\Order\ExpiredPaymentNotification;
use Orbit\Notifications\Order\PendingPaymentNotification;
use Orbit\Notifications\Order\CanceledPaymentNotification;
use Orbit\Controller\API\v1\Pub\Purchase\Activities\PurchaseFailedActivity;
use Orbit\Controller\API\v1\Pub\Purchase\Activities\PurchaseAbortedActivity;
use Orbit\Controller\API\v1\Pub\Purchase\Activities\PurchaseExpiredActivity;
use Orbit\Controller\API\v1\Pub\Purchase\Activities\PurchasePendingActivity;
use Orbit\Controller\API\v1\Pub\Purchase\Activities\PurchaseCanceledActivity;
use Orbit\Controller\API\v1\Pub\Purchase\Activities\PurchaseProcessingProductActivity;
use Order;

/**
 * Order Purchase Update handler.
 *
 * @todo Create a proper base purchase creator/updater.
 *
 * @author Budi <budi@gotomalls.com>
 */
class UpdatePurchase
{
    protected $objectType = 'Order';

    protected $purchase = null;

    private $shouldNotifyRefund = false;

    private $refundReason = '';

    public function update($request)
    {
        try {
            DB::beginTransaction();

            $payment_transaction_id = $request->payment_transaction_id;
            $status = $request->status;
            $mallId = $request->mall_id;
            $fromSnap = $request->from_snap ?: false;
            $refundData = $request->refund_data;

            $shouldUpdate = false;
            $currentUtmUrl = $this->generateUtmUrl();

            $this->purchase = PaymentTransaction::onWriteConnection()->with([
                'details.order',
                'refunds',
                'midtrans',
                'user',
                'discount_code'
            ])->findOrFail($payment_transaction_id);

            $this->resolveObjectType();

            $oldStatus = $this->purchase->status;

            // List of status which considered as final (should not be changed again except some conditions met).
            $finalStatus = [
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

            // Assume status as success if it is success_no_coupon/success_no_coupon_failed,
            // because Midtrans and landing_page don't send those status. (They only know 'success')
            $tmpOldStatus = $oldStatus;
            $successStatus = [
                PaymentTransaction::STATUS_SUCCESS_NO_PRODUCT,
                PaymentTransaction::STATUS_SUCCESS_NO_PRODUCT_FAILED,
                PaymentTransaction::STATUS_SUCCESS_REFUND
            ];

            if (in_array($oldStatus, $successStatus)) {
                $tmpOldStatus = PaymentTransaction::STATUS_SUCCESS;
            }

            $tmpNewStatus = $status;

            // If we have cancel request after success (paid), then update
            if ($oldStatus === PaymentTransaction::STATUS_SUCCESS
                && $status === PaymentTransaction::STATUS_CANCEL
            ) {
                $shouldUpdate = true;
            }

            // If old status was marked as final and doesnt match with the new one, then
            // ask Midtrans for the correct one.
            else if (in_array($oldStatus, $finalStatus) && $tmpOldStatus !== $status) {
                $this->log("Payment {$payment_transaction_id} was marked as FINAL, but there is new request to change status to {$tmpNewStatus}");

                // If it is a refund request, then try to record it..
                if (in_array($tmpNewStatus, ['refund', 'partial_refund']) && ! empty($refundData)) {
                    $this->handleRefund($refundData);
                }
                else {
                    $this->log("Getting correct status from Midtrans for payment {$payment_transaction_id}...");
                    $transactionStatus = TransactionStatus::create()->getStatus($payment_transaction_id);
                    $status = $transactionStatus->mapToInternalStatus();
                    // If the new status doesnt match with what midtrans gave us, then
                    // we can ignored this request (dont update).
                    if ($tmpNewStatus !== $status) {
                        $this->log(sprintf(
                            "New status %s for payment %s will be IGNORED since the correct status is %s!",
                            $tmpNewStatus, $payment_transaction_id, $status
                        ));
                    }
                    else {
                        $this->log("New status {$status} for payment {$payment_transaction_id} will be set!");
                        $shouldUpdate = true;
                    }
                }
            }
            else if (! in_array($oldStatus, $finalStatus)) {
                if ($status === PaymentTransaction::STATUS_ABORTED) {
                    // If status is aborted, then check if the transaction is in pending (exists) or not in Midtrans.
                    // If doesn't exist, assume the payment is starting and we can abort it.
                    // Otherwise, assume it is pending and we should not update the statusad.
                    $this->log("Request to abort payment {$payment_transaction_id}...");
                    $this->log("Checking transaction {$payment_transaction_id} status in Midtrans...");
                    $transactionStatus = TransactionStatus::create()->getStatus($payment_transaction_id);
                    if ($transactionStatus->notFound()) {
                        $this->log("Transaction {$payment_transaction_id} not found! Aborting payment...");
                        $shouldUpdate = true;
                    }
                    else {
                        $this->log("Transaction {$payment_transaction_id} found! Payment can not be aborted/canceled.");
                    }
                }
                else {
                    $shouldUpdate = true;
                }
            }
            else {
                $this->log("Payment {$payment_transaction_id} is good. Nothing to do.");
                // Commit the changes ASAP so if there are any other requests that trigger this controller
                // they will use the updated payment data/status.
                // Try not doing any expensive operation above.
                DB::commit();
            }

            // If old status is not final, then we should update...
            if ($shouldUpdate) {
                $mall = Mall::where('merchant_id', $mallId)->first();

                $this->purchase->status = $status;

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

                $this->purchase->responded_at = Carbon::now('UTC');

                // If payment was success, set purchase to processing/purchasing product to provider.
                if ($status === PaymentTransaction::STATUS_SUCCESS) {
                    $this->purchase->status = PaymentTransaction::STATUS_SUCCESS_NO_PRODUCT;
                }

                $orderIds = $this->purchase->details->filter(function($detail) {
                        return $detail->object_type === 'order';
                    })->lists('object_id');

                // If payment pending, then set Orders status to waiting payment.
                if ($status === PaymentTransaction::STATUS_PENDING) {
                    $cart = App::make(CartInterface::class);
                    foreach($this->purchase->details as $detail) {
                        if ($detail->order) {
                            $detail->order->status = Order::STATUS_WAITING_PAYMENT;
                            $detail->order->save();
                            $cart->removeItem($detail->payload);
                        }
                    }
                }

                // Cancel Orders if payment failed, denied, etc.
                if (in_array($status, [
                        PaymentTransaction::STATUS_FAILED,
                        PaymentTransaction::STATUS_ABORTED,
                        PaymentTransaction::STATUS_FAILED,
                        PaymentTransaction::STATUS_DENIED,
                    ])
                ) {
                    Order::cancel($orderIds);
                }

                if ($oldStatus === PaymentTransaction::STATUS_PENDING
                    && in_array($status, [
                        PaymentTransaction::STATUS_CANCELED,
                        PaymentTransaction::STATUS_EXPIRED,
                    ])
                ) {
                    // Cancel order, but don't restore the product qty.
                    Order::cancel($orderIds, false);
                }

                // Request Orders cancellation.
                if ($oldStatus === PaymentTransaction::STATUS_SUCCESS
                    && $status === PaymentTransaction::STATUS_CANCEL
                ) {
                    $this->purchase->status = $oldStatus;
                    Order::requestCancel($orderIds);
                }

                // If new status is 'aborted', then keep it as 'starting' after cleaning up
                // any related (issued) coupons.
                if ($oldStatus === PaymentTransaction::STATUS_STARTING
                    && $status === PaymentTransaction::STATUS_ABORTED
                ) {
                    // If not from closing snap window, then keep status to starting.
                    if (! $fromSnap) {
                        $this->purchase->status = PaymentTransaction::STATUS_STARTING;
                    }
                }

                $this->purchase->save();

                // Commit the changes ASAP so if there are any other requests that trigger this controller
                // they will use the updated payment data/status.
                // Try not doing any expensive operation above.
                DB::commit();

                $this->purchase->current_utm_url = $currentUtmUrl;

                $objectName = $this->resolveObjectName($this->purchase);

                // Log activity...
                // Should be done before issuing coupon for the sake of activity ordering,
                // or at the end before returning the response??
                if ($this->purchase->failed() || $this->purchase->denied()) {
                    $this->purchase->user->activity(new PurchaseFailedActivity($this->purchase));
                }
                else if ($this->purchase->status === PaymentTransaction::STATUS_SUCCESS_NO_PRODUCT) {
                    $this->purchase->user->activity(
                        new PurchaseProcessingProductActivity(
                            $this->purchase, $objectName, $this->objectType
                        )
                    );
                }

                Event::fire('orbit.payment.postupdatepayment.after.commit', [
                    $this->purchase,
                    $mall,
                    []
                ]);

                // If previous status was starting and now is pending, we should trigger job transaction status check.
                // The job will be run forever until the transaction status is success, failed, expired or reached the maximum number of allowed check.
                if ($oldStatus === PaymentTransaction::STATUS_STARTING
                    && $status === PaymentTransaction::STATUS_PENDING
                ) {
                    $delay = Config::get('orbit.partners_api.midtrans.transaction_status_timeout', 60);
                    $queueData = [
                        'transactionId' => $payment_transaction_id,
                        'check' => 0,
                        'current_url' => $currentUtmUrl
                    ];

                    if (! empty($mall)) {
                        $queueData['mall_id'] = $mall->merchant_id;
                    }

                    Queue::later(
                        $delay,
                        "Orbit\Queue\Payment\Midtrans\CheckTransactionStatusQueue",
                        $queueData
                    );

                    $this->log(sprintf(
                        'First time TransactionStatus check for Payment: %s  is scheduled to run after %s seconds.',
                        $payment_transaction_id, $delay
                    ));

                    // Notify customer to complete the payment.
                    // Send email to address that being used on checkout (can be different with user's email)
                    $paymentUser = new User;
                    $paymentUser->email = $this->purchase->user_email;

                    // $paymentUser->notify(new PendingPaymentNotification($this->purchase), 30);

                    // Record activity of pending purchase...
                    $this->purchase->user->activity(new PurchasePendingActivity($this->purchase, $objectName));
                }

                // Send notification if the purchase was canceled.
                // Only send if previous status was pending.
                if ($oldStatus === PaymentTransaction::STATUS_PENDING
                    && $status === PaymentTransaction::STATUS_CANCELED
                ) {
                    // $this->purchase->user->notify(new CanceledPaymentNotification($this->purchase));

                    $this->purchase->user->activity(new PurchaseCanceledActivity($this->purchase, $objectName));
                }

                // Send notification if the purchase was expired
                // Only send if previous status was pending.
                if ($oldStatus === PaymentTransaction::STATUS_PENDING
                    && $status === PaymentTransaction::STATUS_EXPIRED
                ) {
                    // $this->purchase->user->notify(new ExpiredPaymentNotification($this->purchase));

                    $this->purchase->user->activity(new PurchaseExpiredActivity($this->purchase, $objectName));
                }

                // Send notification if the purchase was aborted
                // Only send if previous status was starting.
                if ($oldStatus === PaymentTransaction::STATUS_STARTING
                    && $status === PaymentTransaction::STATUS_ABORTED
                ) {
                    if ($fromSnap) {
                        // $this->purchase->user->notify(new AbortedPaymentNotification($this->purchase));

                        $this->purchase->user->activity(new PurchaseAbortedActivity($this->purchase, $objectName));
                    }
                }
            }
            else {
                OrbitInput::post('payment_method', function($paymentMethod) {
                    $this->purchase->payment_method = $paymentMethod;
                });

                $this->purchase->save();

                DB::commit();
            }

            // Send refund notification to customer.
            if ($this->shouldNotifyRefund) {
                $this->purchase->user->notify(
                    new CustomerRefundNotification(
                        $this->purchase, $this->refundReason
                    )
                );
            }

            return $this->purchase;

        } catch (Exception $e) {
            // rethrow exception so main controller can handle it.
            throw $e;
        }
    }

    private function log($message = '')
    {
        Log::info("{$this->objectType}: {$message}");
    }

    /**
     * Resolve object type of item that being purchased.
     *
     * @return [type] [description]
     */
    private function resolveObjectType()
    {
        //
    }

    /**
     * Resolve object name.
     *
     * @return [type] [description]
     */
    private function resolveObjectName()
    {
        foreach($this->purchase->details as $detail) {
            if (! empty($detail->order)) {
                return $detail->object_name;
            }
        }

        return 'Unknown object name';
    }

    private function generateUtmUrl()
    {
        $utmUrl = '';
        $utm_source = (isset($this->purchase->utm_source)) ? $this->purchase->utm_source : '';
        $utm_medium = (isset($this->purchase->utm_medium)) ? $this->purchase->utm_medium : '';
        $utm_term = (isset($this->purchase->utm_term)) ? $this->purchase->utm_term : '';
        $utm_content = (isset($this->purchase->utm_content)) ? $this->purchase->utm_content : '';
        $utm_campaign = (isset($this->purchase->utm_campaign)) ? $this->purchase->utm_campaign : '';

        $utmUrl = '?utm_source='.$utm_source.'&utm_medium='.$utm_medium.'&utm_term='.$utm_term.'&utm_content='.$utm_content.'&utm_campaign='.$utm_campaign;

        return $utmUrl;
    }

    private function handleRefund($refundData)
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
}
