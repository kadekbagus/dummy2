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

    protected $guarded = [];

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
     * Relation to Pulsa model.
     *
     * @return [type] [description]
     */
    public function pulsa()
    {
        return $this->belongsTo('Pulsa', 'object_id', 'pulsa_item_id');
    }

    public function discount_code()
    {
        return $this->belongsTo('DiscountCode', 'object_id', 'discount_code_id');
    }

    public function discount()
    {
        return $this->belongsTo('Discount', 'object_id', 'discount_id');
    }

    public function digital_product()
    {
        return $this->belongsTo('DigitalProduct', 'object_id', 'digital_product_id');
    }

    public function provider_product()
    {
        return $this->belongsTo('ProviderProduct');
    }

    public function order()
    {
        return $this->belongsTo('Order', 'object_id', 'order_id');
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
        if ($this->currency === 'IDR') {
            return $this->currency . ' ' . number_format($this->price, 0, ',', '.');
        }

        return $this->currency . ' ' . number_format($this->price, 2, '.', ',');
    }

    /**
     * Get the formatted total with currency code.
     *
     * @return [type] [description]
     */
    public function getTotal()
    {
        $total = $this->price * $this->quantity;
        if ($this->currency === 'IDR') {
            return $this->currency . ' ' . number_format($total, 0, ',', '.');
        }

        return $this->currency . ' ' . number_format($total, 2, '.', ',');
    }
}
