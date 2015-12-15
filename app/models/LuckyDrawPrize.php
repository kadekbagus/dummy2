<?php

/**
 * LuckyDrawPrize Model
 *
 * @author Ahmad <ahmad@dominopos.com>
 */
class LuckyDrawPrize extends Eloquent
{
    use ModelStatusTrait;

    protected $table = 'lucky_draw_prizes';

    protected $primaryKey = 'lucky_draw_prize_id';

    public function luckyDraw()
    {
        return $this->belongsTo('LuckyDraw', 'lucky_draw_id', 'lucky_draw_id');
    }

    public function creator()
    {
        return $this->belongsTo('User', 'created_by', 'user_id');
    }

    public function modifier()
    {
        return $this->belongsTo('User', 'modified_by', 'user_id');
    }

	public function winners()
    {
        return $this->hasMany('LuckyDrawWinner', 'lucky_draw_prize_id', 'lucky_draw_prize_id');
    }    
}