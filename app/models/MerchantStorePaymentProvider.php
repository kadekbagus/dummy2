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

}