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
    public function scopeAllowedForPMPUser($builder, $user, $type, $mall = null) {
        $table_name = '';
        $field_name = '';

        switch ($type) {
            case 'news':
                $table_name = 'news';
                $relation_merchant = 'news_merchant';
                $field_name = 'news_id';
                $field_merchant_id = 'merchant_id';
                break;

            case 'promotion':
                $table_name = 'news';
                $relation_merchant = 'news_merchant';
                $field_name = 'news_id';
                $field_merchant_id = 'merchant_id';
                break;

            case 'coupon':
                $table_name = 'promotions';
                $relation_merchant = 'promotion_retailer';
                $field_name = 'promotion_id';
                $field_merchant_id = 'retailer_id';
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
                ->join("{$relation_merchant}", "{$relation_merchant}.{$field_name}", '=', 'user_campaign.campaign_id')
                ->join('merchants', 'merchants.merchant_id', '=', "{$relation_merchant}.{$field_merchant_id}")
        ->where(function ($q) use ($user) {
            $q->where('campaign_account.user_id', $user->user_id)
              ->orWhere('campaign_account.parent_user_id', $user->user_id);
        })
        ->Where(function ($q) use ($type) {
            if ($type !== 'coupon') {
                $q->where('news.object_type', $type);
            }
        })
        ->where(function ($q) use ($mall) {
            if (! is_null($mall)) {
                $q->where('merchants.merchant_id', $mall->merchant_id)
                  ->orWhere('merchants.parent_id', $mall->merchant_id);
            }
        });

        return $builder;
    }

}
