<?php
class TransactionDetailPromotion extends Eloquent
{
    /**
    * Transaction Detail Promotions model
    *
    * @author kadek <kadek@dominopos.com>
    */

    protected $table = 'transaction_detail_promotions';

    protected $primaryKey = 'transaction_detail_promotion_id';

    public function promotion()
    {
        return $this->belongsTo('Promotion', 'promotion_id', 'promotion_id');
    }

    public function transaction()
    {
        return $this->belongsTo('Transaction', 'transaction_id', 'transaction_id');
    }
}