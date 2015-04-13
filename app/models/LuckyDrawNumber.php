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

}
