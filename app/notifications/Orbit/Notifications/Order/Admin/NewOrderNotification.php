<?php

namespace Orbit\Notifications\Order\Admin;

use Config;
use Exception;
use Log;
use Mail;
use Orbit\Helper\Notifications\AdminNotification;
use Orbit\Notifications\Traits\CommonHelper;
use Orbit\Notifications\Traits\HasContactTrait;
use Orbit\Notifications\Traits\HasOrderTrait;
use Orbit\Notifications\Traits\HasPaymentTrait as HasPayment;

/**
 * Notify store admin that the new order has been made.
 *
 * @author Budi <budi@dominopos.com>
 */
class NewOrderNotification extends AdminNotification
{
    use CommonHelper,
        HasContactTrait,
        HasPayment,
        HasOrderTrait {
            HasOrderTrait::getTransactionData insteadof HasPayment;
        }

    /**
     * Indicate if we should push this job to queue.
     * @var boolean
     */
    protected $shouldQueue = true;

    /**
     * @var integer
     */
    protected $notificationDelay = 3;

    protected $logID = 'ProductOrderNotification';

    protected $signature = 'admin-new-order-notification';

    function __construct($payment = null)
    {
        $this->payment              = $payment;
        $this->queueName            = Config::get('orbit.registration.mobile.queue_name');
    }

    /**
     * Get the email templates.
     *
     * @return [type] [description]
     */
    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.order.admin.new-order',
        ];
    }

    /**
     * Get the email subject.
     * @return [type] [description]
     */
    public function getEmailSubject()
    {
        return trans('email-order.new-order.subject', [], '', 'id');
    }

    public function getEmailData()
    {
        return [
            'transactionId' => $this->payment->payment_transaction_id,
        ];
    }

    /**
     * @override
     */
    protected function getSupportedLanguages()
    {
        return ['id','en'];
    }

    /**
     * Get the email data. Separate by store/location because there's
     * possibility that we have to send to multiple admin/store users
     * for a single purchase/transaction.
     *
     * @return array $emailData - data needed to render the email
     */
    private function prepareEmailData($data)
    {
        $this->payment = $this->getPayment($data['transactionId']);
        $admins = $this->getAdminRecipients();

        $emailData = [];

        $this->payment->details->filter(function($detail) {
                return ! empty($detail->order);
            })->each(function($detail) use (&$emailData, $admins) {

                $storeId = $detail->order->merchant_id;

                if (! isset($emailData[$storeId])) {
                    $emailData[$storeId] = [
                        'admins' => $admins[$storeId]['admins'],
                        'store' => $admins[$storeId]['details'],
                        'transaction' => [
                            'id'        => $this->payment->payment_transaction_id,
                            'orderId'   => $detail->object_id,
                            'itemName'  => null,
                            'otherProduct' => -1,
                            'items'     => [],
                            'discounts' => [],
                            'total'     => 0,
                            'followUpUrl' => $this->getFollowUpOrderUrl($detail->order),
                        ],
                    ];
                }

                $emailData[$storeId]['transaction']['total'] =
                    $this->formatCurrency($detail->order->total_amount, $detail->currency);

                foreach($detail->order->details as $orderDetail) {
                    $product = $orderDetail->brand_product_variant;
                    $productName = $product->brand_product->product_name;

                    $emailData[$storeId]['transaction']['otherProduct']++;

                    if (empty($emailData[$storeId]['transaction']['itemName'])) {
                        $emailData[$storeId]['transaction']['itemName'] = $productName;
                    }

                    $emailData[$storeId]['transaction']['items'][] = [
                        'name'      => $productName,
                        'shortName' => $productName,
                        'variant'   => $this->getVariant($orderDetail),
                        'quantity'  => $orderDetail->quantity,
                        'price'     => $this->formatCurrency($orderDetail->selling_price, $detail->currency),
                        'total'     => $this->formatCurrency($orderDetail->selling_price * $orderDetail->quantity, $detail->currency),
                    ];
                }
            });

        return $emailData;
    }

    private function getBasicEmailData()
    {
        return [
            'recipientEmail'    => '',
            'recipientName'    => 'Store Admin',
            'transaction'       => [],
            'customer'          => $this->getCustomerData(),
            'transactionDateTime' => $this->getTransactionDateTime(),
            'emailSubject'      => $this->getEmailSubject(),
            'supportedLangs'    => $this->getSupportedLanguages(),
            'cs'                => $this->getContactData(),
        ];
    }

    /**
     * Notify via email.
     * This method act as custom Queue handler.
     *
     * @param  [type] $notifiable [description]
     * @return [type]             [description]
     */
    public function toEmail($job, $data)
    {
        try {
            $emailData = $this->prepareEmailData($data);
            $basicEmailData = $this->getBasicEmailData();
            $emailTemplate = $this->getEmailTemplates();
            $emailConfig = Config::get('orbit.registration.mobile.sender');

            foreach($emailData as $storeData) {
                $emailDataByStore = array_merge($basicEmailData, [
                    'store' => $storeData['store'],
                    'transaction' => $storeData['transaction'],
                ]);

                // reset each recipient email address and name,
                // then send the email.
                foreach($storeData['admins'] as $recipient) {
                    $emailDataByStore['recipientEmail'] = $recipient['email'];
                    $emailDataByStore['recipientName'] = $recipient['name'];

                    Mail::send(
                        $emailTemplate,
                        $emailDataByStore,
                        function($mail) use ($emailConfig, $emailDataByStore) {
                            $mail->subject($emailDataByStore['emailSubject']);
                            $mail->from($emailConfig['email'], $emailConfig['name']);
                            $mail->to($emailDataByStore['recipientEmail']);
                        }
                    );
                }
            }

        } catch (Exception $e) {
            $this->log('Exception on Line:' . $e->getLine() . ', Message: ' . $e->getMessage());
            $this->log('Exception data: ' . serialize($data));
        }

        $job->delete();
    }

}
