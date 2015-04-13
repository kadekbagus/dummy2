<?php
class LuckyDraw extends Eloquent
{
    /**
     * LuckyDraw Model
     *
     * @author Tian <tian@dominopos.com>
     */

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    protected $table = 'lucky_draws';

    protected $primaryKey = 'lucky_draw_id';

    public function mall()
    {
        return $this->belongsTo('Retailer', 'mall_id', 'merchant_id');
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
        return $this->hasMany('LuckyDrawWinner', 'lucky_draw_id', 'lucky_draw_id');
    }

    public function numbers()
    {
        return $this->hasMany('LuckyDrawNumber', 'lucky_draw_id', 'lucky_draw_id');
    }

    /**
     * Lucky Draw has many uploaded media.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function media()
    {
        return $this->hasMany('Media', 'object_id', 'lucky_draw_id')
                    ->where('object_name', 'lucky_draw');
    }

}
