<?php

/**
 * Related payment information specific for Midtrans.
 *
 * @author Budi <budi@dominopos.com>
 */
class PaymentMidtrans extends Eloquent
{

    protected $primaryKey = 'payment_midtrans_id';

    protected $table = 'payment_midtrans';

    /**
     * Link to Payment.
     * 
     * @return [type] [description]
     */
    public function payment()
    {
        return $this->belongsTo('PaymentTransaction', 'payment_transaction_id', 'payment_transaction_id');
    }

}
