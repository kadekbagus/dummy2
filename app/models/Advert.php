<?php
class Advert extends Eloquent
{
    /**
     * Advert Model
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     */

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;
    use CampaignAccessTrait;

    protected $table = 'adverts';
    protected $primaryKey = 'advert_id';

    const ADVERT_PROMOTION_ERROR_CODE = 1301;
    const ADVERT_COUPON_ERROR_CODE = 1302;
    const ADVERT_STORE_ERROR_CODE = 1303;
    const ADVERT_NEWS_ERROR_CODE = 1304;

    public function creator()
    {
        return $this->belongsTo('User', 'created_by', 'user_id');
    }

    public function modifier()
    {
        return $this->belongsTo('User', 'modified_by', 'user_id');
    }

    public function media()
    {
        return $this->hasMany('Media', 'object_id', 'advert_id')
                    ->where('object_name', 'advert');
    }

    public function media_orig()
    {
        return $this->media()->where('media_name_long', '=', 'advert_image_orig');
    }

    public function locations()
    {
        return $this->hasMany('AdvertLocation', 'advert_id', 'advert_id');
    }

    public function advertLocations()
    {
        $prefix = DB::getTablePrefix();
        return $this->hasMany('AdvertLocation', 'advert_id', 'advert_id')
                    ->select('advert_id',
                        DB::raw("CASE
                                    WHEN {$prefix}advert_locations.location_id = '0' and {$prefix}advert_locations.location_type = 'gtm' THEN '0'
                                    ELSE merchant_id
                                END AS 'merchant_id'"),
                        DB::raw("CASE
                                    WHEN {$prefix}advert_locations.location_id = '0' and {$prefix}advert_locations.location_type = 'gtm' THEN 'gtm'
                                    ELSE name
                                END AS 'name'"))
                    ->leftJoin('merchants', 'merchants.merchant_id', '=', 'advert_locations.location_id');
    }

}