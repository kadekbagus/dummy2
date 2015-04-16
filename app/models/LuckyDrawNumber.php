<?php
class LuckyDrawNumber extends Eloquent
{
    /**
     * LuckyDrawNumber Model
     *
     * @author Tian <tian@dominopos.com>
     */

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    protected $table = 'lucky_draw_numbers';

    protected $primaryKey = 'lucky_draw_number_id';

    public function luckyDraw()
    {
        return $this->belongsTo('LuckyDraw', 'lucky_draw_id', 'lucky_draw_id');
    }

    public function user()
    {
        return $this->belongsTo('User', 'user_id', 'user_id');
    }

    public function receipts()
    {
        return $this->belongsToMany('LuckyDrawReceipt', 'lucky_draw_number_receipt', 'lucky_draw_number_id', 'lucky_draw_receipt_id');
    }

    public function creator()
    {
        return $this->belongsTo('User', 'created_by', 'user_id');
    }

    public function modifier()
    {
        return $this->belongsTo('User', 'modified_by', 'user_id');
    }

    /**
     * Join with lucky_draw_receipts
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     */
    public function scopeJoinReceipts($query) {
        $query->join('lucky_draw_number_receipt',
                     'lucky_draw_number_receipt.lucky_draw_number_id', '=',
                     'lucky_draw_numbers.lucky_draw_number_id')
              ->join('lucky_draw_receipts', function($join) {
                     $join->on('lucky_draw_receipts.lucky_draw_receipt_id', '=', 'lucky_draw_number_receipt.lucky_draw_receipt_id');
                     $join->on('lucky_draw_receipts.object_type', '=', DB::raw("'lucky_draw'"));
               })->join('merchants', 'merchants.merchant_id', '=', 'lucky_draw_receipts.receipt_retailer_id');

        return $query;
    }

    /**
     * Join with lucky_draws
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     */
    public function scopeJoinLuckyDraw($query) {
        $query->join('lucky_draws',
                     'lucky_draws.lucky_draw_id', '=',
                     'lucky_draw_numbers.lucky_draw_id');

        return $query;
    }
}
