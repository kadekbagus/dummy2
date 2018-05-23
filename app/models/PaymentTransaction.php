<?php

class PaymentTransaction extends Eloquent
{
    /**
     * Payment transcations Model
     * Saving payment transactios
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     */

    use ModelStatusTrait;

    protected $primaryKey = 'payment_transaction_id';

    protected $table = 'payment_transactions';

    /**
     * Payment - Coupon Sepulsa relation.
     *
     * @author Budi <budi@dominopos.com>
     * 
     * @return [type] [description]
     */
    public function coupon_sepulsa()
    {
        return $this->belongsTo('CouponSepulsa', 'object_id', 'promotion_id');
    }

    /**
     * Payment - Coupon relation.
     *
     * @author Budi <budi@dominopos.com>
     * 
     * @return [type] [description]
     */
    public function coupon()
    {
        return $this->belongsTo('Coupon', 'object_id', 'promotion_id');
    }

    /**
     * Determine if the payment is fully completed or not.
     *
     * @author Budi <budi@dominopos.com>
     *
     * @todo  use proper status for indicating completed payment. At the moment these statuses are assumption.
     * @return [type] [description]
     */
    public function completed()
    {
        return in_array($this->status, ['success', 'paid', 'settlement']);
    }
}
