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

        if ($user->isCampaignAdmin())
        {
            // Return the query builder as it is
            return $builder;
        }

        // This should be for other PMP roles, the additional query
        // should be wrappred inde the parenthis () to make
        // the original query unaffected
        $builder->leftJoin('user_campaign', 'user_campaign.campaign_id', '=', "{$table_name}.{$field_name}")
                ->join('campaign_account', 'campaign_account.user_id', '=', 'user_campaign.user_id')
        ->where(function ($q) use ($user) {
            $q->where('campaign_account.user_id', $user->user_id)
              ->orWhere('campaign_account.parent_user_id', $user->user_id);
        })
        ->Where(function ($q) use ($type) {
            if ($type !== 'coupon') {
                $q->where('news.object_type', $type);
            }
        });

        return $builder;
    }

}
