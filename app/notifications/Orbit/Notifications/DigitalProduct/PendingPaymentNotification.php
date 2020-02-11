<?php namespace Orbit\Notifications\DigitalProduct;

use Carbon\Carbon;
use Config;
use DB;
use Exception;
use Log;
use Mail;
use Orbit\Notifications\Payment\PendingPaymentNotification as Base;
use PaymentTransaction;
use Queue;


/**
 * Pending Payment Notification for Digital Product.
 *
 * @author Budi <budi@dominopos.com>
 */
class PendingPaymentNotification extends Base
{
    /**
     * Context of this notification.
     * @var string
     */
    protected $context = 'transaction';

    /**
     * Signature/ID for this notification.
     * @var string
     */
    protected $signature = 'digital-product-pending-transaction';

    protected $productType = '';

    function __construct($payment = null)
    {
        parent::__construct($payment);
    }

    /**
     * Get the email templates.
     * At the moment we can use same template for both Sepulsa and Hot Deals.
     * Can be overriden in each receipt class if needed.
     *
     * @return [type] [description]
     */
    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.digital-product.pending-payment',
            // 'text' => 'emails.digital-product.pending-payment'
        ];
    }

    /**
     * Get the email data.
     *
     * @return [type] [description]
     */
    public function getEmailData()
    {
        return array_merge(parent::getEmailData(), [
            'productType' => $this->getObjectType(),
            'paymentMethod' => $this->getPaymentMethod(),
            'cancelUrl' => $this->getCancelUrl() . "&type=game-voucher",
            'myWalletUrl' => $this->getMyPurchasesUrl('/game-voucher'),
        ]);
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
            $payment = PaymentTransaction::onWriteConnection()->select('payment_transaction_id', 'status')->findOrFail($data['transaction']['id']);

            // Only send email if pending.
            if ($payment->pending()) {
                Mail::send($data['template'], $data, function($mail) use ($data) {
                    $emailConfig = Config::get('orbit.registration.mobile.sender');

                    $subject = $data['emailSubject'];

                    $mail->subject($subject);
                    $mail->from($emailConfig['email'], $emailConfig['name']);
                    $mail->to($data['recipientEmail']);
                });
            }

        } catch (Exception $e) {
            Log::debug('Notification: PendingPayment email exception. Line:' . $e->getLine() . ', Message: ' . $e->getMessage());
        }

        $job->delete();
    }
}
