<?php namespace Orbit\Notifications\Traits;

/**
 * A trait that indicate that the using object/model
 * *should* have PaymentTransaction instance as property in it.
 *
 * @author Budi <budi@dominopos.com>
 */
trait HasPaymentTrait
{
    protected $payment = null;

    protected $bankMapper = [
        'mandiri' => [
            'label' => 'Mandiri',
            'code' => '008',
        ],
        'bni' => [
            'label' => 'BNI',
            'code' => '009',
        ],
        'bca' => [
            'label' => 'BCA',
            'code' => '014',
        ],
        'permata' => [
            'label' => 'Permata Bank',
            'code' => '013',
        ],
        'cimb' => [
            'label' => 'CIMB',
            'code' => '022',
        ],
        'bri' => [
            'label' => 'BRI',
            'code' => '002',
        ],
        'danamon' => [
            'label' => 'Danamon',
            'code' => '011',
        ],
        'maybank' => [
            'label' => 'Maybank',
            'code' => '016',
        ],
        'mega' => [
            'label' => 'Bank Mega',
            'code' => '426',
        ],
        'other' => [
            'label' => 'Other Bank',
            'code' => '013',
        ],
    ];

    /**
     * Get the transaction data.
     *
     * @todo  return transaction as object instead of array. (need to adjust the view/email templates)
     * @todo  use presenter helper.
     *
     * @return [type] [description]
     */
    protected function getTransactionData()
    {
        $transaction = [
            'id'        => $this->payment->payment_transaction_id,
            'date'      => $this->payment->getTransactionDate(),
            'customer'  => $this->getCustomerData(),
            'items'     => [],
            'total'     => $this->payment->getGrandTotal(),
        ];

        foreach($this->payment->details as $item) {
            $transaction['items'][] = [
                'name'      => $item->object_name,
                'quantity'  => $item->quantity,
                'price'     => $item->getPrice(),
                'total'     => $item->getTotal(),
            ];
        }

        return $transaction;
    }

    protected function getCustomerEmail()
    {
        return $this->payment->user_email;
    }

    protected function getCustomerName()
    {
        return $this->payment->user_name;
    }

    protected function getCustomerPhone()
    {
        return $this->payment->phone;
    }

    /**
     * Get the customer data.
     *
     * @return [type] [description]
     */
    protected function getCustomerData()
    {
        return (object) [
            'email'     => $this->getCustomerEmail(),
            'name'      => $this->getCustomerName(),
            'phone'     => $this->getCustomerPhone(),
        ];
    }

    /**
     * Get the Payment info.
     *
     * @return [type] [description]
     */
    protected function getPaymentInfo()
    {
        $paymentMethod = null;
        if ($this->payment->paidWith(['echannel', 'bank_transfer'])) {
            if (! empty($this->payment->midtrans)) {
                $paymentInfo = json_decode(unserialize($this->payment->midtrans->payment_midtrans_info));

                if (! empty($paymentInfo)) {
                    $virtualBank = isset($paymentInfo->va_numbers) ? $paymentInfo->va_numbers : null;
                    $billKey = isset($paymentInfo->bill_key) ? $paymentInfo->bill_key : null;
                    $billerCode = isset($paymentInfo->biller_code) ? $paymentInfo->biller_code : null;
                    $paymentType = isset($paymentInfo->payment_type) ? $paymentInfo->payment_type : null;
                    $pdfUrl = isset($paymentInfo->pdf_url) ? $paymentInfo->pdf_url : null;

                    $vaNumber = isset($paymentInfo->permata_va_number) ? $paymentInfo->permata_va_number : null;

                    switch ($paymentInfo->payment_type) {
                        case 'credit_card':
                            $bank = isset($paymentInfo->bank) ? $paymentInfo->bank : null;
                            break;
                        case 'echannel':
                            $bank = 'mandiri';
                            break;
                        case 'bank_transfer':
                            $bank = 'permata';
                            if (empty($vaNumber)) {
                                $bank = ! empty($virtualBank) ? $virtualBank[0]->bank : $bank;
                                $vaNumber = ! empty($virtualBank) ? $virtualBank[0]->va_number : null;
                            }
                            break;
                        default:
                            $bank = null;
                            break;
                    }

                    $bankDetail = ! empty($bank) ? $this->bankMapper[$bank] : null;

                    $paymentMethod = array_filter([
                        'bank' => $bank,
                        'va_number' => $vaNumber,
                        'bill_key' => $billKey,
                        'biller_code' => $billerCode,
                        'payment_type' => $paymentType,
                        'pdf_url' => $pdfUrl,
                        'bank_detail' => $bankDetail,
                    ]);
                }
            }
        }

        return $paymentMethod;
    }
}
