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
        return $this->hasMany('NewsTranslation', 'news_id', 'news_id')
                ->where('news_translations.status', '!=', 'deleted')
                ->has('language')
                ->join('languages', 'languages.language_id', '=', 'news_translations.merchant_language_id');
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
        $prefix = DB::getTablePrefix();
        return $this->belongsToMany('CampaignLocation', 'news_merchant', 'news_id', 'merchant_id')
                ->select('merchants.*', DB::raw("IF({$prefix}merchants.object_type = 'tenant', (select language_id from {$prefix}languages where name = pm.mobile_default_language), (select language_id from {$prefix}languages where name = {$prefix}merchants.mobile_default_language)) as default_language"))
                ->leftjoin('merchants as pm', DB::raw("pm.merchant_id"), '=', 'merchants.parent_id')
            ->withPivot('object_type');
    }

    public function esCampaignLocations()
    {
        $prefix = DB::getTablePrefix();
        return $this->belongsToMany('CampaignLocation', 'news_merchant', 'news_id', 'merchant_id')
                ->select(
                    'merchants.merchant_id',
                    'merchants.name',
                    'merchants.object_type',
                    DB::raw('oms.city,
                    oms.province,
                    oms.country'),
                    DB::raw("
                        (CASE WHEN {$prefix}merchants.object_type = 'tenant'
                            THEN {$prefix}merchants.parent_id
                            ELSE {$prefix}merchants.merchant_id
                        END) as parent_id
                    "),
                    DB::raw("
                        (CASE WHEN {$prefix}merchants.object_type = 'tenant'
                            THEN oms.name
                            ELSE {$prefix}merchants.name
                        END) as mall_name
                    ")
                )
                ->leftJoin(DB::raw("{$prefix}merchants oms"), DB::raw("oms.merchant_id"), '=', DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN {$prefix}merchants.parent_id ELSE {$prefix}merchants.merchant_id END"))
                ;
    }

    public function country()
    {
        $prefix = DB::getTablePrefix();
        return $this->belongsToMany('CampaignLocation', 'news_merchant', 'news_id', 'merchant_id')
                ->select(DB::raw('oms.country'))
                ->leftJoin(DB::raw("{$prefix}merchants oms"), DB::raw("oms.merchant_id"), '=', DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN {$prefix}merchants.parent_id ELSE {$prefix}merchants.merchant_id END"))
                ->groupBy(DB::raw('oms.country'));
    }

    public function city()
    {
        $prefix = DB::getTablePrefix();
        return $this->belongsToMany('CampaignLocation', 'news_merchant', 'news_id', 'merchant_id')
                ->select(DB::raw('oms.city'))
                ->leftJoin(DB::raw("{$prefix}merchants oms"), DB::raw("oms.merchant_id"), '=', DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN {$prefix}merchants.parent_id ELSE {$prefix}merchants.merchant_id END"))
                ->groupBy(DB::raw('oms.city'));
    }

    public function campaignObjectPartners()
    {
        $prefix = DB::getTablePrefix();
        return $this->hasMany('ObjectPartner', 'object_id', 'news_id')
                      ->select('object_partner.object_id', DB::raw("{$prefix}partners.partner_id"), DB::raw("{$prefix}partners.partner_name"), DB::raw("{$prefix}partners.token"), DB::raw("{$prefix}partners.is_exclusive"))
                      ->leftjoin('partners', 'partners.partner_id', '=', 'object_partner.partner_id');
    }

    public function adverts()
    {
        return $this->hasMany('Advert', 'link_object_id', 'news_id')
            ->leftJoin('advert_link_types', 'adverts.advert_link_type_id', '=', 'advert_link_types.advert_link_type_id')
            ->where('advert_link_types.advert_type', '=', 'promotion');
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

    public function mediaPokestop()
    {
        return $this->hasOne('Media', 'object_id', 'news_id')
                    ->where('object_name', 'pokestop');
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
                    ->join('keywords', 'keywords.keyword_id', '=', 'keyword_object.keyword_id')
                    ->groupBy('keyword');
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
