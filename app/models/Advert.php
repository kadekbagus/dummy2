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