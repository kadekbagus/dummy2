<?php
class LuckyDrawAnnouncement extends Eloquent
{
    /**
     * LuckyDrawAnnouncement Model
     *
     * @author Ahmad <ahmad@dominopos.com>
     */

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    protected $table = 'lucky_draw_announcements';

    protected $primaryKey = 'lucky_draw_announcement_id';

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

    /**
     * Join with lucky_draws
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     */
    public function scopeJoinLuckyDraw($query)
    {
        $query->join('lucky_draws',
                     'lucky_draws.lucky_draw_id', '=',
                     'lucky_draw_numbers.lucky_draw_id');

        return $query;
    }

    /**
     * Lucky Draw has many uploaded media.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     */
    public function media()
    {
        return $this->hasMany('Media', 'object_id', 'lucky_draw_id')
                    ->where('object_name', 'lucky_draw');
    }

    /**
     * Lucky Draw Announcement strings can be translated to many languages.
     */
    public function translations()
    {
        return $this->hasMany('LuckyDrawAnnouncementTranslation', 'lucky_draw_announcement_id', 'lucky_draw_announcement_id')->excludeDeleted()->whereHas('language', function($has) {
            $has->where('merchant_languages.status', 'active');
        });
    }
}
