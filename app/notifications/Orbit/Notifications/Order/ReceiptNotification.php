<?php

namespace Orbit\Notifications\Order;

use Config;
use Discount;
use Exception;
use Mail;
use Orbit\Helper\Resource\ImageTransformer;
use Orbit\Helper\Resource\MediaQuery;
use Orbit\Notifications\Payment\ReceiptNotification as BaseReceiptNotification;
use PaymentTransaction;

/**
 * Receipt Notification for Customer after purchasing Pulsa.
 *
 * @author Budi <budi@gotomalls.com>
 */
class ReceiptNotification extends BaseReceiptNotification
{
    protected $signature = 'brand-product-order-receipt-notification';

    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.order.receipt',
        ];
    }

    /**
     * Only send email notification at the moment.
     *
     * @override
     * @return [type] [description]
     */
    protected function notificationMethods()
    {
        // Set to notify via email
        return ['email'];
    }

    public function getEmailData()
    {
        return [
            'transactionId' => $this->payment->payment_transaction_id
        ];
    }

    /**
     * @override
     * @return [type] [description]
     */
    protected function getTransactionData()
    {
        $transaction = [
            'id'        => $this->payment->payment_transaction_id,
            'date'      => $this->payment->getTransactionDate(),
            'customer'  => $this->getCustomerData(),
            'itemName'  => null,
            'otherProduct' => -1,
            'items'     => [],
            'discounts' => [],
            'total'     => $this->payment->getGrandTotal(),
        ];

        foreach($this->payment->details as $item) {
            $detailItem = [
                'name'      => $item->object_name,
                'shortName' => $item->object_name,
                'quantity'  => $item->quantity,
                'price'     => $item->getPrice(),
                'total'     => $item->getTotal(),
            ];

            if ($item->order) {
                foreach($item->order->details as $orderDetail) {
                    $product = $orderDetail->brand_product_variant;
                    $detailItem = [
                        'name'      => $product->brand_product->product_name,
                        'shortName' => $product->brand_product->product_name,
                        'quantity'  => $orderDetail->quantity,
                        'price'     => $this->formatCurrency($orderDetail->selling_price, $item->currency),
                        'total'     => $this->formatCurrency($item->order->total_amount, $item->currency),
                    ];

                    if (empty($transaction['itemName'])) {
                        $transaction['itemName'] = $detailItem['name'];
                    }

                    $transaction['items'][] = $detailItem;
                    $transaction['otherProduct']++;
                }
            }
            else if ($item->price < 0 || $item->object_type === 'discount') {
                $discount = Discount::select('value_in_percent')->find($item->object_id);
                $discount = ! empty($discount) ? $discount->value_in_percent . '%' : '';
                $detailItem['name'] = $discount;
                $detailItem['quantity'] = '';
                $transaction['discounts'][] = $detailItem;
            }
        }

        return $transaction;
    }

    private function prepareMailData($data)
    {
        $this->payment = PaymentTransaction::onWriteConnection()->with([
                'details.order.details.order_variant_details',
                'details.order.details.brand_product_variant.brand_product',
                'midtrans',
                'user',
                'discount',
            ])->findOrFail($data['transactionId']);


        return [
            'recipientEmail'    => $this->getRecipientEmail(),
            'customerEmail'     => $this->getCustomerEmail(),
            'customerName'      => $this->getCustomerName(),
            'customerPhone'     => $this->getCustomerPhone(),
            'transaction'       => $this->getTransactionData(),
            'cs'                => $this->getContactData(),
            'myWalletUrl'       => $this->getMyPurchasesUrl('/products'),
            'transactionDateTime' => $this->payment->getTransactionDate('d F Y, H:i ') . " {$this->getLocalTimezoneName($this->payment->timezone_name)}",
            'emailSubject'      => $this->getEmailSubject(),
            // 'gameName'          => $this->getGameName(),
            // 'purchaseRewards'   => $this->getPurchaseRewards(),
        ];
    }

    public function toEmail($job, $data)
    {
        try {
            $mailData = $this->prepareMailData($data);

            Mail::send($this->getEmailTemplates(), $mailData, function($mail) use ($mailData) {
                $emailConfig = Config::get('orbit.registration.mobile.sender');

                $subject = $mailData['emailSubject'];

                $mail->subject($subject);
                $mail->from($emailConfig['email'], $emailConfig['name']);
                $mail->to($mailData['recipientEmail']);
            });

        } catch (Exception $e) {
            \Log::info(serialize($e));
        }

        $job->delete();
    }
}
