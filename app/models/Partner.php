<?php

class Partner extends Eloquent
{
        /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    protected $table = 'partners';
    protected $primaryKey = 'partner_id';

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

    public function mediaCropped()
    {
        return $this->media()
                    ->where('media_name_long', 'like', '%_cropped_default')
                    ->orderBy('metadata', 'asc');
    }

    public function mediaLogo()
    {
        return $this->media()->where('media_name_id', 'partner_logo');
    }

    public function mediaLogoOrig()
    {
        return $this->mediaOrig()->where('media_name_id', 'partner_logo');
    }

    public function mediaImage()
    {
        return $this->media()->where('media_name_id', 'partner_image');
    }

    public function mediaImageOrig()
    {
        return $this->mediaOrig()->where('media_name_id', 'partner_image');
    }

    public function mediaBanner()
    {
        return $this->media()->where('media_name_id', 'partner_banner');
    }

    public function mediaBannerOrig()
    {
        return $this->mediaOrig()->where('media_name_id', 'partner_banner');
    }

    public function partnerAffectedGroup()
    {
        return $this->hasMany('PartnerAffectedGroup', 'partner_id', 'partner_id');
    }

    public function translations()
    {
        return $this->hasMany('PartnerTranslation', 'partner_id', 'partner_id')
                ->where('partner_translations.status', '!=', 'deleted')
                ->has('language')
                ->join('languages', 'languages.language_id', '=', 'partner_translations.language_id');
    }

    public function supportedLanguages()
    {
        return $this->hasMany('ObjectSupportedLanguage', 'object_id', 'partner_id')
            ->where('object_supported_language.object_type', '=', 'partner');
    }
}
