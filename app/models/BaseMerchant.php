<?php

class BaseMerchant extends Eloquent
{
    /**
     * Base Merchant Model
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    protected $primaryKey = 'base_merchant_id';

    protected $table = 'base_merchants';

    public function media()
    {
        return $this->hasMany('Media', 'object_id', 'base_merchant_id')
                    ->where('object_name', 'base_merchant');
    }

    public function mediaOrig()
    {
        return $this->hasMany('Media', 'object_id', 'base_merchant_id')
                    ->where('object_name', 'base_merchant')
                    ->where('media_name_long', 'like', '%_orig')
                    ->orderBy('metadata', 'asc');
    }

    public function mediaCroppedDefault()
    {
        return $this->hasMany('Media', 'object_id', 'base_merchant_id')
                    ->where('object_name', 'base_merchant')
                    ->where('media_name_long', 'like', '%_cropped_default')
                    ->orderBy('metadata', 'asc');
    }

    public function mediaResizedDefault()
    {
        return $this->hasMany('Media', 'object_id', 'base_merchant_id')
                    ->where('object_name', 'base_merchant')
                    ->where('media_name_long', 'like', '%_resized_default');
    }

    public function mediaLogo()
    {
        return $this->media()->where('media_name_id', 'base_merchant_logo');
    }

    public function mediaLogoOrig()
    {
        return $this->mediaOrig()->where('media_name_id', 'base_merchant_logo');
    }

    public function baseMerchantCategory()
    {
        return $this->hasMany('BaseMerchantCategory', 'base_merchant_id', 'base_merchant_id')
                    ->join('categories', 'categories.category_id', '=', 'base_merchant_category.category_id');
    }

    public function baseMerchantTranslation()
    {
        return $this->hasMany('BaseMerchantTranslation', 'base_merchant_id', 'base_merchant_id');
    }

    public function keywords()
    {
        return $this->hasMany('BaseMerchantKeyword', 'base_merchant_id', 'base_merchant_id')
                    ->join('keywords', 'keywords.keyword_id', '=', 'base_merchant_keyword.keyword_id');
    }

    /**
     * Merchant has many partner
     *
     * @author Shelgi Prasetyo <shelgi@dominopos.com>
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function partners()
    {
        $prefix = DB::getTablePrefix();
        return $this->hasMany('BaseObjectPartner', 'object_id', 'base_merchant_id')
                      ->select('base_object_partner.object_id', DB::raw("{$prefix}partners.partner_id"), DB::raw("{$prefix}partners.partner_name"))
                      ->leftjoin('partners', 'partners.partner_id', '=', 'base_object_partner.partner_id');
    }
}
