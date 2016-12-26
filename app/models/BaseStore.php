<?php

class BaseStore extends Eloquent
{
    /**
     * BaseStore Model
     *
     * @author Irianto <irianto@dominopos.com>
     */

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    protected $primaryKey = 'base_store_id';

    protected $table = 'base_stores';

    /**
     * Base Store has many uploaded media.
     *
     * @author Irianto <irianto@dominopos.com>
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function media()
    {
        return $this->hasMany('Media', 'object_id', 'base_store_id')
                    ->where('object_name', 'base_store');
    }

    /**
     * Base Store has many uploaded media with original type.
     *
     * @author Irianto <irianto@dominopos.com>
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function mediaOrig()
    {
        return $this->media()
                    ->where('media_name_long', 'like', '%_orig')
                    ->orderBy('metadata', 'asc');
    }

    /**
     * Base Store has many uploaded media with cropped_default type.
     *
     * @author Irianto <irianto@dominopos.com>
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function mediaCroppedDefault()
    {
        return $this->media()
                    ->where('media_name_long', 'like', '%_cropped_default')
                    ->orderBy('metadata', 'asc');
    }

    /**
     * Base Store has many uploaded media with resize default type.
     *
     * @author Irianto <irianto@dominopos.com>
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function mediaResizedDefault()
    {
        return $this->media()
                    ->where('media_name_long', 'like', '%_resized_default');
    }

    /**
     * Base Store has many uploaded images.
     *
     * @author Irianto <irianto@dominopos.com>
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function mediaImage()
    {
        return $this->media()->where('media_name_id', 'base_store_image');
    }

    /**
     * Base Store has many uploaded images with original type.
     *
     * @author Irianto <irianto@dominopos.com>
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function mediaImageOrig()
    {
        return $this->mediaOrig()->where('media_name_id', 'base_store_image');
    }

    /**
     * Base Store has many uploaded images with cropped_default type.
     *
     * @author Irianto <irianto@dominopos.com>
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function mediaImageCroppedDefault()
    {
        return $this->mediaCroppedDefault()->where('media_name_id', 'base_store_image');
    }

    /**
     * Base Store has many uploaded maps.
     *
     * @author Irianto <irianto@dominopos.com>
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function mediaMap()
    {
        return $this->media()->where('media_name_id', 'base_store_map');
    }

    /**
     * Base Store has many uploaded maps with original type.
     *
     * @author Irianto <irianto@dominopos.com>
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function mediaMapOrig()
    {
        return $this->mediaOrig()->where('media_name_id', 'base_store_map');
    }

    /**
     * Get all store data (presync)
     *
     * @author Shelgi Prasetyo <shelgi@dominopos.com>
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function getAllPreSyncStore()
    {
        $prefix = DB::getTablePrefix();
        $stores = static::select('base_stores.base_store_id',
                                'base_merchants.base_merchant_id',
                                'base_merchants.name',
                                'base_merchants.description',
                                'base_stores.status',
                                DB::raw("media.path"),
                                'base_stores.merchant_id',
                                'base_merchants.url',
                                'base_stores.floor_id',
                                'objects.object_name',
                                'base_stores.unit',
                                'base_stores.verification_number',
                                'merchants.name as location_name'
                            )
                            ->join('base_merchants', 'base_merchants.base_merchant_id', '=', 'base_stores.base_merchant_id')
                            ->leftJoin('objects', 'objects.object_id', '=', 'base_stores.floor_id')
                            ->leftJoin('merchants', 'base_stores.merchant_id', '=', 'merchants.merchant_id')
                            ->leftJoin(DB::raw("{$prefix}media media"), function ($q) {
                                $q->on(DB::raw("media.object_id"), '=', 'base_merchants.base_merchant_id')
                                  ->on(DB::raw("media.media_name_id"), '=', DB::raw("'base_merchant_logo'"))
                                  ->on(DB::raw("media.object_name"), '=', DB::raw("'base_merchant'"));
                            })
                            ->where('base_stores.status', '!=', 'deleted')
                            ->groupBy('base_stores.base_store_id');

        return $stores;
    }

}
