<?php

/**
 * Payment Transcations Detail Model.
 * 
 * Store the detail of transaction, such as the item(s) that is being bought.
 * This model should be linked to a real purchasable model, e.g Coupon.
 *
 * @author Budi <budi@dominopos.com>
 * @todo  add polymorphic relationship for easier access to the real purchasable model.
 */
class PaymentTransactionDetail extends Eloquent
{
    use ModelStatusTrait;

    protected $primaryKey = 'payment_transaction_detail_id';

    protected $table = 'payment_transaction_details';

    /**
     * Link to Payment.
     * 
     * @return [type] [description]
     */
    public function payment()
    {
        return $this->belongsTo('PaymentTransaction', 'payment_transaction_id', 'payment_transaction_id');
    }

    /**
     * Link to Coupon Sepulsa.
     *
     * @return [type] [description]
     */
    public function coupon_sepulsa()
    {
        return $this->belongsTo('CouponSepulsa', 'object_id', 'promotion_id');
    }

    /**
     * Link to Coupon.
     *
     * @return [type] [description]
     */
    public function coupon()
    {
        return $this->belongsTo('Coupon', 'object_id', 'promotion_id');
    }

    /**
     * Link to Issued Coupon
     *
     * @return [type] [description]
     */
    public function issued_coupon()
    {
        return $this->hasOne('IssuedCoupon', 'transaction_id', 'payment_transaction_id');
    }

    /**
     * Link to detail normal/paypro.
     * 
     * @return [type] [description]
     */
    public function normal_paypro_detail()
    {
        return $this->hasOne('PaymentTransactionDetailNormalPaypro', 'payment_transaction_detail_id', 'payment_transaction_detail_id');
    }

    /**
     * Determine if the payment is for Sepulsa Deals.
     *
     * @return [type] [description]
     */
    public function forSepulsa()
    {
        if (! empty($this->coupon)) {
            return $this->coupon->promotion_type === Coupon::TYPE_SEPULSA;
        }

        return false;
    }

    /**
     * Determine if the payment is for Hot Deals.
     *
     * @return [type] [description]
     */
    public function forHotDeals()
    {
        if (! empty($this->coupon)) {
            return $this->coupon->promotion_type === Coupon::TYPE_HOT_DEALS;
        }

        return false;
    }

    /**
     * Get the formatted price with currency code.
     * 
     * @return [type] [description]
     */
    public function getPrice()
    {
        return $this->currency . ' ' . number_format($this->price, 0, ',', '');
    }

    /**
     * Get the formatted total with currency code.
     * 
     * @return [type] [description]
     */
    public function getTotal()
    {
        return $this->currency . ' ' . number_format($this->price * $this->quantity, 0, ',', '');
    }

}
