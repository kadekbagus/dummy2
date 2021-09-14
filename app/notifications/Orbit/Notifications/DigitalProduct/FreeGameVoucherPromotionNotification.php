<?php namespace Orbit\Notifications\DigitalProduct;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Orbit\Notifications\DigitalProduct\ReceiptNotification;
use PaymentTransaction;

/**
 * Free Game Voucher Promotion Notification for Customer after purchasing
 * Game Voucher.
 *
 * @author Budi <budi@gotomalls.com>
 */
class FreeGameVoucherPromotionNotification extends ReceiptNotification
{
    protected $logID = 'FreeGameVoucherPromotion';

    protected $signature = 'digital-product-free-game-voucher-promotion-notification';

    public function getEmailTemplates()
    {
        return [
            // 'html' => 'emails.digital-product.free-game-voucher.free-game-voucher',
            'text' => 'emails.digital-product.free-game-voucher.free-game-voucher-text',
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

    public function getEmailSubject()
    {
        return trans('email-purchase-rewards.free_game_voucher.subject', [], '', 'id');
    }

    public function getEmailData()
    {
        return [
            'transaction_id' => $this->payment->payment_transaction_id,
        ];
    }

    protected function getSupportedLanguages()
    {
        return ['id', 'en'];
    }

    private function getProviderProductName()
    {
        $name = '';

        foreach($this->payment->details as $detail) {
            if ($detail->provider_product) {
                $name = $detail->provider_product->provider_product_name;
                break;
            }
        }

        return $name;
    }

    private function getVouchers()
    {
        $vouchers = [];

        foreach($this->payment->free_game_vouchers as $gameVoucherDetail) {
            $vouchers[] = [
                'startDate' => Carbon::parse($gameVoucherDetail->game_voucher->start_date)
                    ->format('d M y'),
                'endDate' => Carbon::parse($gameVoucherDetail->game_voucher->end_date)
                    ->format('d M y'),
                'pin' => $gameVoucherDetail->pin_number,
                'serialNumber' => $gameVoucherDetail->serial_number,
            ];
        }

        return $vouchers;
    }

    private function prepareMailData($data)
    {
        $this->payment = PaymentTransaction::onWriteConnection()
            ->with([
                'details.provider_product',
                'user',
                'free_game_vouchers.game_voucher',
            ])->findOrFail($data['transaction_id']);

        return [
            'recipientEmail'    => $this->getRecipientEmail(),
            'customerEmail'     => $this->getCustomerEmail(),
            'customerName'      => $this->getCustomerName(),
            'cs'                => $this->getContactData(),
            'transactionId'     => $this->payment->payment_transaction_id,
            'transactionDateTime' => $this->payment->getTransactionDate('d M Y, H:i ')
                . " {$this->getLocalTimezoneName($this->payment->timezone_name)}",
            'emailSubject'      => $this->getEmailSubject(),
            'productName'       => $this->getProviderProductName(),
            'vouchers'          => $this->getVouchers(),
            'supportedLangs'    => $this->getSupportedLanguages(),
        ];
    }

    public function toEmail($job, $data)
    {
        try {
            $data = $this->prepareMailData($data);
            $data['emailConfig'] = Config::get('orbit.registration.mobile.sender');

            $emailTemplates = $this->getEmailTemplates();

            foreach($data['vouchers'] as $voucher) {
                $data['voucher'] = $voucher;

                Mail::send($emailTemplates, $data, function($mail) use ($data) {
                    $mail->subject($data['emailSubject']);
                    $mail->from($data['emailConfig']['email'], $data['emailConfig']['name']);
                    $mail->to($data['recipientEmail']);
                });
            }

        } catch (Exception $e) {
            $this->log(sprintf(
                'Email exception. Line: %s, Message: %s',
                $e->getLine(), $e->getMessage()
            ));
        }

        $job->delete();
    }
}
