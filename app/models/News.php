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
    use CampaignStatusTrait;
    use CampaignAccessTrait;

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

    public function campaignLocations()
    {
        return $this->belongsToMany('CampaignLocation', 'news_merchant', 'news_id', 'merchant_id')
            ->withPivot('object_type');
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

    public function campaign_status()
    {
        return $this->belongsTo('CampaignStatus', 'campaign_status_id', 'campaign_status_id');
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

    /**
     * Campaign Status scope
     *
     * @author Irianto <irianto@dominopos.com>
     * @todo change campaign status to expired when over the end date
     */
    public function scopeCampaignStatus($query, $campaign_status, $mallTimezone = NULL)
    {
        $prefix = DB::getTablePrefix();
        $quote = function($arg)
        {
            return DB::connection()->getPdo()->quote($arg);
        };

        if ($mallTimezone != NULL) {
            return $query->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'news.campaign_status_id')
                        ->where(DB::raw("CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired'
                                        THEN {$prefix}campaign_status.campaign_status_name
                                            ELSE (
                                                CASE WHEN {$prefix}news.end_date < {$quote($mallTimezone)}
                                                THEN 'expired'
                                                    ELSE {$prefix}campaign_status.campaign_status_name
                                                END)
                                        END"), $campaign_status);
        }
        return $query->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'news.campaign_status_id')
                    ->where(DB::raw("CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired'
                                    THEN {$prefix}campaign_status.campaign_status_name
                                        ELSE (
                                            CASE WHEN {$prefix}news.end_date < UTC_TIMESTAMP()
                                            THEN 'expired'
                                                ELSE {$prefix}campaign_status.campaign_status_name
                                            END)
                                    END"), $campaign_status);
    }

}
