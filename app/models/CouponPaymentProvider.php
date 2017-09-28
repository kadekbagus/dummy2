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

}