<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class CampaignDailySpendingCalculation extends Command {

    /**
     * This artisan command for calculate total campaign daily spending (news, promotion, coupon) per mall per campaign and per date
     * This command will be implement with cronjob / scheduler every hour
     *
     * @var string
     */
    protected $name = 'campaign:spending-daily-calculation';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Daily spending calculation per day.';

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
        $updateSpendingAllowed = Config::get('orbit.campaign_spending.update_spending_on_cron', TRUE);
        if ($updateSpendingAllowed === FALSE) {
            $this->info('Campaign spending calculation via artisan is disabled.');

            return FALSE;
        }

        // date_default_timezone_set('UTC')
        $this->info('=== Running at ' . date('l j \of F Y h:i:s A') . " ===");

        // Start time for log
        $started_time = microtime(true);

        $prefix = DB::getTablePrefix();

        // Get mall which have timezone
        $getMall = Mall::select('merchant_id','name','timezone_name',DB::raw(" DATE_FORMAT(CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', timezone_name), '%H') AS tz "))
                ->leftJoin('timezones', 'timezones.timezone_id', '=', 'merchants.timezone_id')
                ->where('object_type','mall')
                ->where('status', '!=', 'deleted')
                ->where('merchants.timezone_id','!=', '')
                ->get();

        // Get all mall
        if (count($getMall) > 0) {

            $totalCampaign = 0;

            foreach ($getMall as $key => $valMall) {
                // get offset timezone
                $dt = new DateTime('now', new DateTimeZone($valMall->timezone_name));
                $now = $dt->format('Y-m-d');
                $timezoneOffset = $dt->format('P');
                $mallId = $valMall->merchant_id;

                // Check mall time is 00 hours
                if ($valMall->tz == '00') {

                    $this->info('Mall name = ' . $valMall->name);

                    // ============================== News and Promotions ==============================
                    // Check campaign in mall which have status not expired or stopped and have tenant with parent id this mall
                    $newsAndPromotions = News::select('news.*', 'campaign_status.order',
                            DB::raw("CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired' THEN {$prefix}campaign_status.campaign_status_name ELSE (CASE WHEN {$prefix}news.end_date < (SELECT CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', ot.timezone_name)
                                FROM {$prefix}merchants om
                                LEFT JOIN {$prefix}timezones ot on ot.timezone_id = om.timezone_id
                                WHERE om.merchant_id = {$prefix}news.mall_id)
                                THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END) END AS campaign_status"))
                        ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'news.campaign_status_id')
                        ->whereNotIn(DB::raw("CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired' THEN {$prefix}campaign_status.campaign_status_name ELSE (CASE WHEN {$prefix}news.end_date < (SELECT CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', ot.timezone_name)
                                    FROM {$prefix}merchants om
                                    LEFT JOIN {$prefix}timezones ot on ot.timezone_id = om.timezone_id
                                    WHERE om.merchant_id = {$prefix}news.mall_id)
                                THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END) END"), ['expired', 'stopped'] )
                        ->whereHas('tenants', function($q) use($mallId){
                            $q->where('parent_id', $mallId);
                        })
                        ->get();

                    // Check campaign which have link to tenant and mall in this mall
                    if (count($newsAndPromotions) > 0) {
                        foreach ($newsAndPromotions as $key => $valNewsPromotions) {

                            \DB::beginTransaction();

                            $startLoadTimeCampaign = microtime(true);

                            // Check per campaign which have this mall
                            // Insert campaign daily spending calculation
                            $campaignId = $valNewsPromotions->news_id;
                            $campaignType = $valNewsPromotions->object_type;
                            $beginDate = $valNewsPromotions->begin_date;
                            $endDate = $valNewsPromotions->end_date;
                            $procResults = DB::statement("CALL prc_campaign_detailed_cost({$this->quote($campaignId)}, {$this->quote($campaignType)}, {$this->quote($now)}, {$this->quote($now)}, {$this->quote($mallId)})");

                            if ($procResults === false) {
                                // Continue to next loop
                                continue;
                            }

                            $getspending = DB::table(DB::raw('tmp_campaign_cost_detail'))->first();

                            if (count($getspending) > 0) {

                                $daily = CampaignDailySpending::where('date', '=', $getspending->date_in_utc)->where('campaign_id', '=', $campaignId)->where('mall_id', '=', $mallId)->first();

                                if (count($daily) > 0) {
                                    $dailySpending = CampaignDailySpending::find($daily['campaign_daily_spending_id']);
                                } else {
                                    $dailySpending = new CampaignDailySpending;
                                }

                                $dailySpending->date = $getspending->date_in_utc;
                                $dailySpending->campaign_type = $campaignType;
                                $dailySpending->campaign_id = $campaignId;
                                $dailySpending->mall_id = $mallId;
                                $dailySpending->number_active_tenants = $getspending->campaign_number_tenant;
                                $dailySpending->base_price = $getspending->base_price;
                                $dailySpending->campaign_status = $getspending->campaign_status;
                                $dailySpending->total_spending = $getspending->daily_cost;
                                $dailySpending->save();

                                if ($dailySpending) {
                                    // Commit the changes
                                    DB::commit();

                                    $endLoadTimeCampaign = microtime(true);
                                    $loadTimeCampaign = $endLoadTimeCampaign - $startLoadTimeCampaign;

                                    $totalCampaign++;
                                    $this->info($totalCampaign . '. Inserted campaign_id = ' . $campaignId . ', campaign_type = '. $campaignType . ', time = ' .$loadTimeCampaign . ' seconds'  );
                                } else {
                                    // Rollback the changes
                                    DB::rollBack();
                                }

                            } else {
                                // Rollback the changes
                                DB::rollBack();
                            }

                        }
                    }

                    // ============================== Coupons ==============================
                    // Check campaign which have link to tenant and mall in this mall
                    $coupons = Coupon::select('promotions.*', 'campaign_status.order',
                            DB::raw("CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired' THEN {$prefix}campaign_status.campaign_status_name ELSE (CASE WHEN {$prefix}promotions.end_date < (SELECT CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', ot.timezone_name)
                                FROM {$prefix}merchants om
                                LEFT JOIN {$prefix}timezones ot on ot.timezone_id = om.timezone_id
                                WHERE om.merchant_id = {$prefix}promotions.merchant_id)
                                THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END) END AS campaign_status"))
                        ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'promotions.campaign_status_id')
                        ->whereNotIn(DB::raw("CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired' THEN {$prefix}campaign_status.campaign_status_name ELSE (CASE WHEN {$prefix}promotions.end_date < (SELECT CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', ot.timezone_name)
                                FROM {$prefix}merchants om
                                LEFT JOIN {$prefix}timezones ot on ot.timezone_id = om.timezone_id
                                WHERE om.merchant_id = {$prefix}promotions.merchant_id)
                                THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END) END"), ['expired', 'stopped'] )
                        ->whereHas('tenants', function($q) use($mallId){
                            $q->where('parent_id', $mallId);
                        })
                        ->get();

                    // Check campaign which have link to tenant and mall in this mall
                    if (count($coupons) > 0) {
                        foreach ($coupons as $key => $valCoupons) {

                            \DB::beginTransaction();

                            $startLoadTimeCampaign = microtime(true);

                            // Check per campaign which have this mall
                            // Insert campaign daily spending calculation
                            $campaignId = $valCoupons->promotion_id;
                            $campaignType = 'coupon';
                            $beginDate = $valCoupons->begin_date;
                            $endDate = $valCoupons->end_date;
                            $procResults = DB::statement("CALL prc_campaign_detailed_cost({$this->quote($campaignId)}, {$this->quote($campaignType)}, {$this->quote($now)}, {$this->quote($now)}, {$this->quote($mallId)})");

                            if ($procResults === false) {
                                // Continue to next loop
                                continue;
                            }

                            $getspending = DB::table(DB::raw('tmp_campaign_cost_detail'))->first();

                            if (count($getspending) > 0) {

                                $daily = CampaignDailySpending::where('date', '=', $getspending->date_in_utc)->where('campaign_id', '=', $campaignId)->where('mall_id', '=', $mallId)->first();

                                if (count($daily) > 0) {
                                    $dailySpending = CampaignDailySpending::find($daily['campaign_daily_spending_id']);
                                } else {
                                    $dailySpending = new CampaignDailySpending;
                                }

                                $dailySpending->date = $getspending->date_in_utc;
                                $dailySpending->campaign_type = $campaignType;
                                $dailySpending->campaign_id = $campaignId;
                                $dailySpending->mall_id = $mallId;
                                $dailySpending->number_active_tenants = $getspending->campaign_number_tenant;
                                $dailySpending->base_price = $getspending->base_price;
                                $dailySpending->campaign_status = $getspending->campaign_status;
                                $dailySpending->total_spending = $getspending->daily_cost;
                                $dailySpending->save();

                                if ($dailySpending) {
                                    // Commit the changes
                                    DB::commit();

                                    $endLoadTimeCampaign = microtime(true);
                                    $loadTimeCampaign = $endLoadTimeCampaign - $startLoadTimeCampaign;

                                    $totalCampaign++;
                                    $this->info($totalCampaign . '. Inserted campaign_id = ' . $campaignId . ', campaign_type = '. $campaignType . ', time = ' .$loadTimeCampaign . ' seconds'  );
                                } else {
                                    // Rollback the changes
                                    DB::rollBack();
                                }

                            } else {
                                // Rollback the changes
                                DB::rollBack();
                            }

                        }

                    }

                }

            }
        }

        // =================== Check time ===================
        $this->info('Calculation successfully, Loaded time  = ' . (microtime(true) - $started_time) . ' seconds, total campaign data = ' . $totalCampaign);

    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return array(
            // array('example', InputArgument::REQUIRED, 'An example argument.'),
        );
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return array(
            // array('example', null, InputOption::VALUE_OPTIONAL, 'An example option.', null),
        );
    }

    protected function getTimezone($current_mall)
    {
        $timezone = Mall::leftJoin('timezones','timezones.timezone_id','=','merchants.timezone_id')
            ->where('merchants.merchant_id','=', $current_mall)
            ->first();

        return $timezone->timezone_name;
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }


}
