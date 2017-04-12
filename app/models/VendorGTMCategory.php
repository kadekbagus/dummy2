<?php

class VendorGTMCategory extends Eloquent
{
    protected $primaryKey = 'vendor_gtm_category_id';

    protected $table = 'vendor_gtm_categories';

    public function grabCategory()
    {
        return $this->belongsTo('GrabCategory', 'vendor_category_id', 'grab_category_id');
    }
}