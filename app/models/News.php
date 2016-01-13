<?php
class News extends Eloquent
{
    /**
     * News Model
     *
     * @author Tian <tian@dominopos.com>
     */

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    protected $table = 'news';

    protected $primaryKey = 'news_id';

    public function mall()
    {
        return $this->belongsTo('Retailer', 'mall_id', 'merchant_id')->isMall();
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
     * Event strings can be translated to many languages.
     */
    public function translations()
    {
        return $this->hasMany('NewsTranslation', 'news_id', 'news_id')->excludeDeleted()->whereHas('language', function($has) {
            $has->where('merchant_languages.status', 'active');
        });
    }

    public function tenants()
    {
        return $this->belongsToMany('Tenant', 'news_merchant', 'news_id', 'merchant_id')
            ->withPivot('object_type')
            ->where('merchants.is_mall', 'no')
            ->where('news_merchant.object_type', 'retailer');
    }

    /**
     * News has many uploaded media.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function media()
    {
        return $this->hasMany('Media', 'object_id', 'news_id')
                    ->where('object_name', 'news');
    }

    public function genders()
    {
        return $this->hasMany('CampaignGender', 'campaign_id', 'news_id');
    }

    public function ages()
    {
        return $this->hasMany('CampaignAge', 'campaign_id', 'news_id')
                    ->join('age_ranges', 'age_ranges.age_range_id', '=', 'campaign_age.age_range_id');
    }

    public function keywords()
    {
        return $this->hasMany('KeywordObject', 'object_id', 'news_id')
                    ->join('keywords', 'keywords.keyword_id', '=', 'keyword_object.keyword_id');
    }

    public function scopeIsNews($query)
    {
        return $query->where('object_type', 'news');
    }

    public function scopeOfMallId($query, $mallId)
    {
        return $query->where('mall_id', $mallId);
    }

    public function scopeIsPromotion($query)
    {
        return $query->where('object_type', 'promotion');
    }

    public function scopeOfRunningDate($query, $date)
    {
        return $query->where('begin_date', '<=', $date)->where('end_date', '>=', $date);
    }

}
