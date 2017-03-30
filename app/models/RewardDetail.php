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
}
