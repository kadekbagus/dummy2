<?php

class GameVoucherPromotion extends Eloquent
{
    protected $primaryKey = 'game_voucher_promotion_id';

    protected $table = 'game_voucher_promotions';

    public function details () {
        return $this->hasMany(GameVoucherPromotion::class, 'game_voucher_promotion_id', 'game_voucher_promotion_id');
    }
}
