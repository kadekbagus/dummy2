<?php

class CampaignHistory extends Eloquent
{
    /**
     * CampaignHistory Model
     *
     * @author Tian <tian@dominopos.com>
     */

    protected $table = 'campaign_histories';

    protected $primaryKey = 'campaign_history_id';

    public function campaignHistoryActions()
    {
        return $this->belongsTo('CampaignHistoryActions', 'campaign_history_action_id', 'campaign_history_action_id');
    }

    public static function getRowCost($campaignid, $status, $action, $now, $iscoupon)
    {
        $tablePrefix = DB::getTablePrefix();
        $cost = DB::table('campaign_histories');
        if ($iscoupon) {
            if ($status === 'active') {
                if ($action === "add") {
                    $cost->selectraw(DB::raw("{$tablePrefix}campaign_histories.number_active_tenants + 1 as tenants, (((CASE WHEN {$tablePrefix}campaign_histories.created_at < {$tablePrefix}promotions.begin_date THEN DATEDIFF('" . $now . "', {$tablePrefix}campaign_histories.created_at) ELSE DATEDIFF('" . $now . "', {$tablePrefix}promotions.begin_date) END) * {$tablePrefix}campaign_histories.number_active_tenants * {$tablePrefix}campaign_price.base_price) + {$tablePrefix}campaign_price.base_price + {$tablePrefix}campaign_histories.campaign_cost) AS cost"));
                } else if ($action === "delete") {
                    $cost->selectraw(DB::raw("{$tablePrefix}campaign_histories.number_active_tenants - 1 as tenants, (CASE WHEN '".$now."' < {$tablePrefix}promotions.begin_date THEN (((CASE WHEN {$tablePrefix}campaign_histories.created_at < {$tablePrefix}promotions.begin_date THEN DATEDIFF('" . $now . "', {$tablePrefix}campaign_histories.created_at) ELSE DATEDIFF('" . $now . "', {$tablePrefix}promotions.begin_date) END) * {$tablePrefix}campaign_histories.number_active_tenants * {$tablePrefix}campaign_price.base_price) - {$tablePrefix}campaign_price.base_price + {$tablePrefix}campaign_histories.campaign_cost) ELSE (((CASE WHEN {$tablePrefix}campaign_histories.created_at < {$tablePrefix}promotions.begin_date THEN DATEDIFF('" . $now . "', {$tablePrefix}campaign_histories.created_at) ELSE DATEDIFF('" . $now . "', {$tablePrefix}promotions.begin_date) END) * {$tablePrefix}campaign_histories.number_active_tenants * {$tablePrefix}campaign_price.base_price) + {$tablePrefix}campaign_histories.campaign_cost) END) AS cost"));
                } else {
                    $cost->select("number_active_tenants as tenants", DB::raw("(CASE WHEN DATE_FORMAT('".$now."','%Y-%m-%d') <= {$tablePrefix}campaign_histories.created_at THEN {$tablePrefix}campaign_histories.campaign_cost ELSE (({$tablePrefix}campaign_histories.number_active_tenants * {$tablePrefix}campaign_price.base_price) + {$tablePrefix}campaign_histories.campaign_cost) END) AS cost"));
                }
            } else {
                if ($action === "add") {
                    $cost->selectraw("(number_active_tenants+1) as tenants, campaign_cost as cost");
                } else if ($action === "delete") {
                    $cost->selectraw("(number_active_tenants-1) as tenants, campaign_cost as cost");
                } else {
                    $cost->select("number_active_tenants as tenants", "campaign_cost as cost");
                }
            }
            $cost->join('promotions', 'campaign_histories.campaign_id', '=', 'promotions.promotion_id')
                ->join('campaign_price', 'campaign_price.campaign_id', '=', 'promotions.promotion_id');
        } else {
            if ($status === 'active') {
                if ($action === "add") {
                    $cost->selectraw(DB::raw("{$tablePrefix}campaign_histories.number_active_tenants + 1 as tenants, (((CASE WHEN {$tablePrefix}campaign_histories.created_at < {$tablePrefix}news.begin_date THEN DATEDIFF('" . $now . "', {$tablePrefix}campaign_histories.created_at) ELSE DATEDIFF('" . $now . "', {$tablePrefix}news.begin_date) END) * {$tablePrefix}campaign_histories.number_active_tenants * {$tablePrefix}campaign_price.base_price) + {$tablePrefix}campaign_price.base_price + {$tablePrefix}campaign_histories.campaign_cost) AS cost"));
                } else if ($action === "delete") {
                    $cost->selectraw(DB::raw("{$tablePrefix}campaign_histories.number_active_tenants - 1 as tenants, (CASE WHEN '".$now."' < {$tablePrefix}news.begin_date THEN (((CASE WHEN {$tablePrefix}campaign_histories.created_at < {$tablePrefix}news.begin_date THEN DATEDIFF('" . $now . "', {$tablePrefix}campaign_histories.created_at) ELSE DATEDIFF('" . $now . "', {$tablePrefix}news.begin_date) END) * {$tablePrefix}campaign_histories.number_active_tenants * {$tablePrefix}campaign_price.base_price) - {$tablePrefix}campaign_price.base_price + {$tablePrefix}campaign_histories.campaign_cost) ELSE (((CASE WHEN {$tablePrefix}campaign_histories.created_at < {$tablePrefix}news.begin_date THEN DATEDIFF('" . $now . "', {$tablePrefix}campaign_histories.created_at) ELSE DATEDIFF('" . $now . "', {$tablePrefix}news.begin_date) END) * {$tablePrefix}campaign_histories.number_active_tenants * {$tablePrefix}campaign_price.base_price) + {$tablePrefix}campaign_histories.campaign_cost) END) AS cost"));
                } else {
                    $cost->select("number_active_tenants as tenants", DB::raw("(CASE WHEN DATE_FORMAT('".$now."','%Y-%m-%d') <= {$tablePrefix}campaign_histories.created_at THEN {$tablePrefix}campaign_histories.campaign_cost ELSE (({$tablePrefix}campaign_histories.number_active_tenants * {$tablePrefix}campaign_price.base_price) + {$tablePrefix}campaign_histories.campaign_cost) END) AS cost"));
                }
            } else {
                if ($action === "add") {
                    $cost->selectraw("(number_active_tenants+1) as tenants, campaign_cost as cost");
                } else if ($action === "delete") {
                    $cost->selectraw("(number_active_tenants-1) as tenants, campaign_cost as cost");
                } else {
                    $cost->select("number_active_tenants as tenants", "campaign_cost as cost");
                }
            }
            $cost->join('news', 'campaign_histories.campaign_id', '=', 'news.news_id')
                ->join('campaign_price', 'campaign_price.campaign_id', '=', 'news.news_id');
        }
        
        return $cost->where('campaign_histories.campaign_id', '=', $campaignid)
                  ->orderBy('campaign_histories.campaign_history_id', 'DESC');
    }

}