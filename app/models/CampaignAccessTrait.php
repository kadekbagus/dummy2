<?php
/**
 * Traits for storing common method that used for specify the access of pmp user account to specific campaign
 *
 * @author Irianto <irianto@dominopos.com>
 */
trait CampaignAccessTrait
{
    /**
     * Method for specify access on pmp portal.
     *
     * @author Irianto <irianto@dominopos.com>
     * @param object $user user
     * @param string $type type of campaign: news, promotion, coupon
     * @return builder
     */
    public function scopeAllowedForPMPUser($builder, $user, $type) {
        $table_name = '';
        $field_name = '';

        switch ($type) {
            case 'news':
                $table_name = 'news';
                $field_name = 'news_id';
                break;

            case 'promotion':
                $table_name = 'news';
                $field_name = 'news_id';
                break;

            case 'coupon':
                $table_name = 'promotions';
                $field_name = 'promotion_id';
                break;

            default:
                throw new Exception("Wrong campaign type supplied", 1);
        }

        $user_id = $user->user_id;
        if ($user->role->role_name === 'Campaign Employee') {
            $campaign_account = CampaignAccount::where('user_id', '=', $user->user_id)
                                                ->first();
            $user_id = $campaign_account->parent_user_id;
        }

        if ($user->isPMPAdmin())
        {
            // Return the query builder as it is
            return $builder;
        }

        // This should be for other PMP roles, the additional query
        // should be wrappred inde the parenthis () to make
        // the original query unaffected
        $builder->leftJoin('user_campaign', 'user_campaign.campaign_id', '=', "{$table_name}.{$field_name}")
        ->where(function($q) use ($user_id) {
            $q->where('user_campaign.user_id', $user_id);
        });

        return $builder;
    }

}
