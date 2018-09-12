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
        $paymentMethod = [];

        if (! empty($this->payment->midtrans) && $this->payment->paidWith(['echannel', 'bank_transfer'])) {
            $paymentMethod = json_decode(unserialize($this->payment->midtrans->payment_midtrans_info), true);
        }

        return $paymentMethod;
    }
}
