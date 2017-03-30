<?php
/**
 * Model for representing the reward detail codes table.
 *
 * @author Irianto <irianto@dominopos.com>
 */
class RewardDetailCode extends Eloquent
{
    protected $primaryKey = 'reward_detail_code_id';
    protected $table = 'reward_detail_codes';

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;
}
