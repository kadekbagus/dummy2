<?php
/**
 * Model for representing the reward detail translations table.
 *
 * @author Irianto <irianto@dominopos.com>
 */
class RewardDetailTranslation extends Eloquent
{
    protected $primaryKey = 'reward_detail_translation_id';
    protected $table = 'reward_detail_translations';

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    public function media()
    {
        return $this->hasMany('Media', 'object_id', 'reward_detail_translation_id')
                    ->where('object_name', 'reward_detail');
    }

    public function rewardSignUpDesktopBackground()
    {
        return $this->media()->where('media_name_id', '=', 'reward_signup_bg_desktop');
    }

    public function rewardSignUpMobileBackground()
    {
        return $this->media()->where('media_name_id', '=', 'reward_signup_bg_mobile');
    }
}
