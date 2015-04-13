<?php

class LuckyDrawNumberReceipt extends Eloquent
{
    /**
     * LuckyDrawNumberReceipt Model
     *
     * @author Tian <tian@dominopos.com>
     */

    protected $table = 'lucky_draw_number_receipt';

    protected $primaryKey = 'lucky_draw_number_receipt_id';

    public function number()
    {
        return $this->belongsTo('LuckyDrawNumber', 'lucky_draw_number_id', 'lucky_draw_number_id');
    }

    public function receipt()
    {
        return $this->belongsTo('LuckyDrawReceipt', 'lucky_draw_receipt_id', 'lucky_draw_receipt_id');
    }
}
