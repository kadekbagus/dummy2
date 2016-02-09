<?php
class LuckyDrawWinner extends Eloquent
{
    /**
     * LuckyDrawWinner Model
     *
     * @author Tian <tian@dominopos.com>
     */

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    protected $table = 'lucky_draw_winners';

    protected $primaryKey = 'lucky_draw_winner_id';

    public function luckyDraw()
    {
        return $this->belongsTo('LuckyDraw', 'lucky_draw_id', 'lucky_draw_id');
    }

    public function number()
    {
        return $this->belongsTo('LuckyDrawNumber', 'lucky_draw_number_id', 'lucky_draw_number_id');
    }

    public function creator()
    {
        return $this->belongsTo('User', 'created_by', 'user_id');
    }

    public function modifier()
    {
        return $this->belongsTo('User', 'modified_by', 'user_id');
    }

    public function prize()
    {
        return $this->belongsTo('LuckyDrawPrize', 'lucky_draw_prize_id', 'lucky_draw_prize_id');
    }
}
