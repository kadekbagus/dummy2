<?php
/**
 * MerchantSocialMedia Model
 *
 * @author Qosdil A. <qosdil@dominopos.com>
 */
class MerchantSocialMedia extends Eloquent
{
    protected $primaryKey = 'merchant_social_media_id';
    protected $table = 'merchant_social_media';

    public function socialMedia()
    {
        return $this->belongsTo('SocialMedia');
    }
}
