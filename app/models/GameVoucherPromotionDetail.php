<?php

class GameVoucherPromotionDetail extends Eloquent
{
    protected $primaryKey = 'game_voucher_promotion_detail_id';

    protected $table = 'game_voucher_promotion_details';

    public function transaction ()
    {
        return $this->belongsTo(PaymentTransaction::class, 'payment_transaction_id', 'payment_transaction_id');
    }

    public function game_voucher()
    {
        return $this->belongsTo(GameVoucherPromotion::class, 'game_voucher_promotion_id', 'game_voucher_promotion_id');
    }
}
