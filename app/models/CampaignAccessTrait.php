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
        $prefix = DB::getTablePrefix();

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
        $builder->leftJoin('user_campaign as uc', DB::raw('uc.campaign_id'), '=', "{$table_name}.{$field_name}")
                ->leftJoin('campaign_account as ca', DB::raw('ca.user_id'), '=', DB::raw('uc.user_id'))
                ->leftJoin('campaign_account as cas', DB::raw('cas.parent_user_id'), '=', DB::raw('ca.parent_user_id'))
        ->where(function ($q) use ($user, $prefix) {
            $q->WhereRaw("ca.user_id = (select parent_user_id from {$prefix}campaign_account where user_id = '{$user->user_id}')
                            or
                          ca.parent_user_id = (select parent_user_id from {$prefix}campaign_account where user_id = '{$user->user_id}')")
                ->orWhere(DB::raw('ca.user_id'), '=', $user->user_id)
                ->orWhere(DB::raw('ca.parent_user_id'), '=', $user->user_id);
        })
        ->where(function ($q) use ($type) {
            if ($type !== 'coupon') {
                $q->where('news.object_type', $type);
            }
        })
        ->groupBy("{$table_name}.{$field_name}");

        return $builder;
    }

}
