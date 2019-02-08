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
     * BaseStore belongs to BaseMerchant.
     *
     * @author Ahmad <ahmad@dominopos.com>
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function baseMerchant()
    {
        return $this->belongsTo('BaseMerchant', 'base_merchant_id', 'base_merchant_id');
    }

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
     * Base Store has many uploaded images.
     *
     * @author kadek <kadek@dominopos.com>
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function mediaImageGrab()
    {
        return $this->media()->where('media_name_id', 'base_store_image_grab');
    }

    /**
     * Base Store has many uploaded images with original type.
     *
     * @author kadek <kadek@dominopos.com>
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function mediaImageGrabOrig()
    {
        return $this->mediaOrig()->where('media_name_id', 'base_store_image_grab');
    }

    /**
     * Base Store has many uploaded images with cropped_default type.
     *
     * @author kadek <kadek@dominopos.com>
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function mediaImageGrabCroppedDefault()
    {
        return $this->mediaCroppedDefault()->where('media_name_id', 'base_store_image_grab');
    }

    /**
     * Base Store has many payment provider
     *
     * @author Shelgi <shelgi@dominopos.com>
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function merchantStorePaymentProvider()
    {
        return $this->hasMany('MerchantStorePaymentProvider', 'object_id', 'base_store_id')
                    ->where('merchant_store_payment_provider.object_type', 'store');
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
                                'base_merchants.country_id',
                                'base_merchants.mobile_default_language',
                                'base_stores.status',
                                DB::raw("media.path"),
                                'base_stores.merchant_id',
                                'base_merchants.url',
                                'base_stores.floor_id',
                                'base_stores.phone',
                                'objects.object_name',
                                'base_stores.unit',
                                'base_stores.verification_number',
                                'base_stores.is_payment_acquire',
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

    /**
     * Merchant to payment acquire
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     * @return \Illuminate\Database\Eloquent\Builder
     */

    public function bank()
    {
        return $this->hasMany('ObjectBank', 'object_id', 'base_store_id')
                    ->select('object_banks.*', 'banks.bank_name')
                    ->leftjoin('banks', 'banks.bank_id', '=', 'object_banks.bank_id')
                    ->where('object_banks.object_type', '=', 'base_store');
    }

    public function financialContactDetail()
    {
        return $this->hasOne('ObjectFinancialDetail', 'object_id', 'base_store_id')
                    ->where('object_financial_details.object_type', '=', 'base_store');
    }

    public function objectContact()
    {
        return $this->hasOne('ObjectContact', 'object_id', 'base_store_id')
                    ->where('object_contacts.object_type', '=', 'base_store');
    }

    public function paymentProvider()
    {
        return $this->hasMany('MerchantStorePaymentProvider', 'object_id', 'base_store_id')
                    ->select('merchant_store_payment_provider.*', 'payment_providers.payment_name')
                    ->leftjoin('payment_providers', 'payment_providers.payment_provider_id', '=', 'merchant_store_payment_provider.payment_provider_id')
                    ->where('merchant_store_payment_provider.object_type', '=', 'base_store');
    }

    public function productTags()
    {
        return $this->hasMany('BaseStoreProductTag', 'base_store_id', 'base_store_id')
                    ->join('product_tags', 'product_tags.product_tag_id', '=', 'base_store_product_tag.product_tag_id');
    }

    public function baseStoreTranslation()
    {
        return $this->hasMany('BaseStoreTranslation', 'base_store_id', 'base_store_id');
    }

    public function supportedLanguage()
    {
        return $this->hasMany('ObjectSupportedLanguage', 'object_id', 'base_merchant_id')
                    ->join('languages', 'languages.language_id', '=', 'object_supported_language.language_id')
                    ->where('object_supported_language.object_type', '=', 'base_merchant');
    }

    public function mediaBanner()
    {
        return $this->media()->where('media_name_id', 'base_store_banner');
    }

}
