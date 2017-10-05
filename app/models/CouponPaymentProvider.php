<?php
/**
 * Saving payment method provider
 *
 * @author Firmansyah <firmansyah@dominopos.com>
 */
class CouponPaymentProvider extends Eloquent
{
    protected $primaryKey = 'coupon_payment_provider_id';

    protected $table = 'coupon_payment_provider';

    /**
     * CouponPaymentProvider belongs to PaymentProvider.
     *
     * @author Shelgi <shelgi@dominopos.com>
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function paymentProvider()
    {
        return $this->belongsTo('PaymentProvider', 'payment_provider_id', 'payment_provider_id');
    }

}