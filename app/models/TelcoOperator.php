<?php
class TelcoOperator extends Eloquent
{
    protected $table = 'telco_operators';

    protected $primaryKey = 'telco_operator_id';

    public function media()
    {
        return $this->hasMany('Media', 'object_id', 'partner_id')
                    ->where('object_name', 'partner');
    }

    public function mediaOrig()
    {
        return $this->media()
                    ->where('media_name_long', 'like', '%_orig')
                    ->orderBy('metadata', 'asc');
    }

	public function mediaLogo()
    {
        return $this->media()->where('media_name_id', 'telco_logo');
    }

    public function mediaLogoOrig()
    {
        return $this->mediaOrig()->where('media_name_id', 'telco_logo');
    }
}