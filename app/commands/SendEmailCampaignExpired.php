<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class SendEmailCampaignExpired extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'campaign:send-email-campaign-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send Email about campaign expired';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function fire()
    {
        $list_campaign = $this->getExpiredCampaignQuery();
        $total_email = count($list_campaign);
        if ($total_email > 0) {
            $this->info("Success, Send {$total_email} Email Campaign Expired!");
        } else {
            $this->info("Success, No Campaign Expired!");
        }
    }

    /**
     * Get campaign expired.
     *
     * @return array
     */
    public function getExpiredCampaignQuery(){
        $prefix = DB::getTablePrefix();

        // get news list
        $news = DB::table('news')->select(
                    'news.news_id as campaign_id',
                    'news.news_name as campaign_name',
                    'news.object_type as campaign_type',
                    // query for get status active based on timezone
                    DB::raw("
                            CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired'
                            THEN {$prefix}campaign_status.campaign_status_name
                            ELSE (
                                CASE WHEN {$prefix}news.end_date < (
                                    SELECT min(CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name))
                                    FROM {$prefix}news_merchant onm
                                        LEFT JOIN {$prefix}merchants om ON om.merchant_id = onm.merchant_id
                                        LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                        LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                    WHERE onm.news_id = {$prefix}news.news_id
                                )
                                THEN 'expired'
                                ELSE {$prefix}campaign_status.campaign_status_name
                                END
                            )
                            END AS campaign_status,
                            CASE WHEN {$prefix}news.end_date = (
                                SELECT DATE_FORMAT(min(CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name)), '%Y-%m-%d %H:%i:00')
                                FROM {$prefix}news_merchant onm
                                    LEFT JOIN {$prefix}merchants om ON om.merchant_id = onm.merchant_id
                                    LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                    LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                WHERE onm.news_id = {$prefix}news.news_id
                            )
                            THEN 'true'
                            ELSE 'false'
                            END as send_email,
                            (
                                select GROUP_CONCAT(IF(m.object_type = 'tenant', CONCAT(m.name,' at ', pm.name), CONCAT('Mall at ',m.name) ) separator '<br/>')
                                from {$prefix}news_merchant
                                left join {$prefix}merchants m on m.merchant_id = {$prefix}news_merchant.merchant_id
                                left join {$prefix}merchants pm on m.parent_id = pm.merchant_id
                                where {$prefix}news_merchant.news_id = {$prefix}news.news_id
                            ) as campaign_location,
                            (
                                select DATE_FORMAT({$prefix}news.end_date, '%d %M %Y %H:%i')
                            ) as end_date
                        "),
                        'news.created_at')
                    ->leftJoin('news_merchant', 'news_merchant.news_id', '=', 'news.news_id')
                    ->leftJoin('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                    ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'news.campaign_status_id')
                    ->where('news.object_type', '=', 'news')
                    ->havingRaw("campaign_status = 'expired' AND send_email = 'true'")
                    ->groupBy('campaign_id')
                    ->orderBy('news.created_at', 'desc');

        $promotions = DB::table('news')->select(
                    'news.news_id as campaign_id',
                    'news.news_name as campaign_name',
                    'news.object_type as campaign_type',
                    // query for get status active based on timezone
                    DB::raw("
                            CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired'
                            THEN {$prefix}campaign_status.campaign_status_name
                            ELSE (
                                CASE WHEN {$prefix}news.end_date < (
                                    SELECT min(CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name))
                                    FROM {$prefix}news_merchant onm
                                        LEFT JOIN {$prefix}merchants om ON om.merchant_id = onm.merchant_id
                                        LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                        LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                    WHERE onm.news_id = {$prefix}news.news_id
                                )
                                THEN 'expired'
                                ELSE {$prefix}campaign_status.campaign_status_name
                                END
                            )
                            END AS campaign_status,
                            CASE WHEN {$prefix}news.end_date = (
                                SELECT DATE_FORMAT(min(CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name)), '%Y-%m-%d %H:%i:00')
                                FROM {$prefix}news_merchant onm
                                    LEFT JOIN {$prefix}merchants om ON om.merchant_id = onm.merchant_id
                                    LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                    LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                WHERE onm.news_id = {$prefix}news.news_id
                            )
                            THEN 'true'
                            ELSE 'false'
                            END as send_email,
                            (
                                select GROUP_CONCAT(IF(m.object_type = 'tenant', CONCAT(m.name,' at ', pm.name), CONCAT('Mall at ',m.name) ) separator '<br/>')
                                from {$prefix}news_merchant
                                left join {$prefix}merchants m on m.merchant_id = {$prefix}news_merchant.merchant_id
                                left join {$prefix}merchants pm on m.parent_id = pm.merchant_id
                                where {$prefix}news_merchant.news_id = {$prefix}news.news_id
                            ) as campaign_location,
                            (
                                select DATE_FORMAT({$prefix}news.end_date, '%d %M %Y %H:%i')
                            ) as end_date
                        "),
                        'news.created_at')
                    ->leftJoin('news_merchant', 'news_merchant.news_id', '=', 'news.news_id')
                    ->leftJoin('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                    ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'news.campaign_status_id')
                    ->where('news.object_type', '=', 'promotion')
                    ->havingRaw("campaign_status = 'expired' AND send_email = 'true'")
                    ->groupBy('campaign_id')
                    ->orderBy('news.created_at', 'desc');

        // get coupon list
        $coupons = DB::table('promotions')->select(DB::raw("
                            {$prefix}promotions.promotion_id as campaign_id,
                            {$prefix}promotions.promotion_name as campaign_name,
                            'coupon' as campaign_type,
                            CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired'
                            THEN {$prefix}campaign_status.campaign_status_name
                            ELSE (
                                CASE WHEN {$prefix}promotions.end_date < (
                                    SELECT min(CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name))
                                    FROM {$prefix}promotion_retailer opt
                                        LEFT JOIN {$prefix}merchants om ON om.merchant_id = opt.retailer_id
                                        LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                        LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                    WHERE opt.promotion_id = {$prefix}promotions.promotion_id)
                                THEN 'expired'
                                ELSE {$prefix}campaign_status.campaign_status_name
                                END
                            )
                            END AS campaign_status,
                            CASE WHEN {$prefix}promotions.end_date = (
                                SELECT DATE_FORMAT(min(CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name)), '%Y-%m-%d %H:%i:00')
                                FROM {$prefix}promotion_retailer opt
                                    LEFT JOIN {$prefix}merchants om ON om.merchant_id = opt.retailer_id
                                    LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                    LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                WHERE opt.promotion_id = {$prefix}promotions.promotion_id)
                            THEN 'true'
                            ELSE 'false'
                            END AS send_email,
                            (
                                select GROUP_CONCAT(IF(m.object_type = 'tenant', CONCAT(m.name,' at ', pm.name), CONCAT('Mall at ',m.name)) separator '<br/>') from {$prefix}promotion_retailer
                                left join {$prefix}merchants m on m.merchant_id = {$prefix}promotion_retailer.retailer_id
                                left join {$prefix}merchants pm on m.parent_id = pm.merchant_id
                                where {$prefix}promotion_retailer.promotion_id = {$prefix}promotions.promotion_id
                            ) as campaign_location,
                            (
                                select DATE_FORMAT({$prefix}promotions.end_date, '%d %M %Y %H:%i')
                            ) as end_date
                        "),
                        'promotions.created_at')
                        ->leftJoin('campaign_status', 'promotions.campaign_status_id', '=', 'campaign_status.campaign_status_id')
                        ->leftJoin('promotion_retailer', 'promotion_retailer.promotion_id', '=', 'promotions.promotion_id')
                        ->leftJoin('merchants', 'merchants.merchant_id', '=', 'promotion_retailer.retailer_id')
                        ->havingRaw("campaign_status = 'expired' AND send_email = 'true'")
                        ->groupBy('campaign_id')
                        ->orderBy(DB::raw("{$prefix}promotions.created_at"), 'desc');

        $result = $news->unionAll($promotions)->unionAll($coupons);

        $querySql = $result->toSql();

        $campaigns = DB::table(DB::Raw("({$querySql}) as campaign"))->mergeBindings($result)
                    ->orderBy('campaign_name', 'asc')
                    ->get();

        $list_campaign = [];
        foreach ($campaigns as $key => $campaign) {
            // Send email process to the queue
            Queue::push('Orbit\\Queue\\CampaignMail', [
                'campaignType'       => ucfirst($campaign->campaign_type),
                'campaignName'       => $campaign->campaign_name,
                'campaignLocation'   => $campaign->campaign_location,
                'eventType'          => 'expired',
                'date'               => $campaign->end_date,
                'campaignId'         => $campaign->campaign_id,
                'mode'               => 'expired'
            ]);

            $list_campaign[$key] = $campaign->campaign_name;
        }

        return $list_campaign;
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return array();
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return array();
    }

}
