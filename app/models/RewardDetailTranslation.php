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
}
