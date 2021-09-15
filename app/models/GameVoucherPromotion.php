<?php

class GameVoucherPromotion extends Eloquent
{
    protected $primaryKey = 'game_voucher_promotion_id';

    protected $table = 'game_voucher_promotions';

    public function details () {
        return $this->hasMany(GameVoucherPromotionDetail::class, 'game_voucher_promotion_id', 'game_voucher_promotion_id');
    }

    public function available_voucher()
    {
        return $this->hasOne(GameVoucherPromotionDetail::class)
            ->whereNull('payment_transaction_id');
    }

    public function active_provider_product()
    {
        return $this->belongsTo(ProviderProduct::class, 'provider_product_id', 'provider_product_id')
            ->where('provider_products.status', 'active');
    }
}
