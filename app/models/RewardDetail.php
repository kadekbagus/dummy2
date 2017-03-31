<?php
/**
 * Model for representing the reward details table.
 *
 * @author Irianto <irianto@dominopos.com>
 */
class RewardDetail extends Eloquent
{
    protected $primaryKey = 'reward_detail_id';
    protected $table = 'reward_details';

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;


    public function rewardTranslations()
    {
        return $this->hasMany('RewardDetailTranslation', 'reward_detail_id', 'reward_detail_id')
            ->join('languages', 'languages.language_id', '=', 'reward_detail_translations.language_id');
    }

    public function rewardCodes()
    {
        return $this->hasMany('RewardDetailCode', 'reward_detail_id', 'reward_detail_id');
    }
}
