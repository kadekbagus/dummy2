<?php

class PartnerBanner extends Eloquent
{
    protected $primaryKey = 'partner_banner_id';

    protected $table = 'partner_banners';

    public function media()
    {
        return $this->hasMany('Media', 'object_id', 'partner_banner_id')
                    ->where('object_name', 'partner_banner');
    }
}
