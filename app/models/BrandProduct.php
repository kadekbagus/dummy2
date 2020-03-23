<?php

class BrandProduct extends Eloquent
{
    protected $primaryKey = 'brand_product_id';

    protected $table = 'brand_products';

    public function media()
    {
        return $this->hasMany('Media', 'object_id', 'brand_product_id')
                    ->where('object_name', 'brand_product');
    }

    public function BrandProductMainPhotos()
    {
        return $this->media()->where('media_name_id', 'brand_product_main_photo');
    }

    public function BrandProductPhotos()
    {
    	return $this->media()->where('media_name_id', 'brand_product_photos');
    }
}