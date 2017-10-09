<?php
/**
 * Saving merchant store payment provider
 *
 * @author Firmansyah <firmansyah@dominopos.com>
 */
class MerchantStorePaymentProvider extends Eloquent
{
    protected $primaryKey = 'payment_provider_store_id';

    protected $table = 'merchant_store_payment_provider';

    /**
     * MerchantStorePaymentProvider belongs to PaymentProvider.
     *
     * @author Shelgi <shelgi@dominopos.com>
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function paymentProvider()
    {
        return $this->belongsTo('PaymentProvider', 'payment_provider_id', 'payment_provider_id')
                    ->where('payment_providers.status', 'active');
    }

}