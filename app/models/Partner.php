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

    public function banners()
    {
        return $this->hasMany('PartnerBanner', 'partner_id', 'partner_id');
    }

    public function mediaPhotos()
    {
        return $this->hasMany('Media', 'object_id', 'partner_id')
                    ->where('media_name_id', 'partner_photos')
                    ->where('object_name', 'partner');
    }

    public function mediaCustomPhotos()
    {
        return $this->hasMany('Media', 'object_id', 'partner_id')
                    ->where('media_name_id', 'partner_custom_photos')
                    ->where('object_name', 'partner');
    }

    public function categories()
    {
        return $this->belongsToMany('Category', 'partner_categories', 'partner_id', 'category_id');
    }

    public function social_media()
    {
        return $this->belongsToMany('SocialMedia', 'object_social_media', 'object_id', 'social_media_id');
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
