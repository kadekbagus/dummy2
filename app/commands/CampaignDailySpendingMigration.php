<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Carbon\Carbon as Carbon;

class CampaignDailySpendingMigration extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'campaign:spending-daily-migrate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Insert campaign spending calculation per campaign type, per mall and per day.';

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
        // Start time for log
        $started_time = microtime(true);

        // Truncate first before inserted data
        $deletedTable = CampaignDailySpending::truncate();

        // Check all campaign existing (news, promotions, coupons)
        if (! $deletedTable ){
            // Do Nothing
        }

        $this->info("Success, truncated table campaign_spending !");

        // =================== Migration for news and promotions ===================
        $idKey = 1;
        $totalCampaign = 0;
        $newsAndPromotions = News::excludeDeleted()->get();

        if (count($newsAndPromotions) > 0) {
            foreach ($newsAndPromotions as $key => $valNewsPromotions) {

                $campaignId = $valNewsPromotions->news_id;
                $campaignType = $valNewsPromotions->object_type;
                $startDate = $valNewsPromotions->begin_date;
                $endDate = $valNewsPromotions->end_date;

                // Get mall per campaign
                $campaignHistory = CampaignHistory::select('merchants.parent_id', 'merchant_id', 'merchants.is_mall',
                    DB::raw("
                        CASE
                            WHEN is_mall = 'yes' and parent_id IS NULL THEN merchant_id
                            WHEN is_mall = 'yes' and parent_id IS NOT NULL THEN merchant_id
                            ELSE parent_id
                        END AS 'mall_id'
                    "))
                    ->leftJoin('merchants', 'merchants.merchant_id', '=', 'campaign_histories.campaign_external_value')
                    ->where('campaign_external_value', '!=', '')
                    ->where('merchants.object_type', '!=', 'mall_group') // Mall group not included link to tenant
                    ->where('campaign_id', '=', $campaignId)
                    ->groupBy('mall_id')
                    ->get();

                foreach ($campaignHistory as $mall) {

                    $mallId = $mall->mall_id;

                    if ($mallId != '') {
                        $mallTimezone = $this->getTimezone($mallId);
                        $nowMall = Carbon::now($mallTimezone);
                        $dateNowMall = $nowMall->toDateString();

                        // Check end date campaign more than today
                        if ($endDate > $dateNowMall ) {
                            $endDate = $dateNowMall;
                        }

                        $procResults = DB::statement("CALL prc_campaign_detailed_cost( {$this->quote($campaignId)}, {$this->quote($campaignType)}, {$this->quote($startDate)}, {$this->quote($endDate)}, {$this->quote($mallId)})");

                        if ($procResults === false) {
                            // Do Nothing
                        }

                        $getSpending = DB::table(DB::raw('tmp_campaign_cost_detail'))
                            ->groupBy('date_in_utc')
                            ->get();


                        if (count($getSpending) > 0) {
                            foreach ($getSpending as $key => $valTmp) {
                                $dailySpending = new CampaignDailySpending();
                                $dailySpending->campaign_daily_spending_id = $idKey;
                                $dailySpending->date = $valTmp->date_in_utc;
                                $dailySpending->campaign_type = $campaignType;
                                $dailySpending->campaign_id = $campaignId;
                                $dailySpending->mall_id = $mallId;
                                $dailySpending->number_active_tenants = $valTmp->campaign_number_tenant;
                                $dailySpending->base_price = $valTmp->base_price;
                                $dailySpending->campaign_status = $valTmp->campaign_status;
                                $dailySpending->total_spending = $valTmp->daily_cost;
                                $dailySpending->save();
                                $idKey++;
                            }
                        }
                    }

                }
                $totalCampaign++;
                $this->info($totalCampaign . '. campaign_id = ' . $campaignId . ', campaign_type = '.$campaignType );

            }


        }
        $this->info('Success, Inserted campaign daily spending for news !');
        $this->info('Success, Inserted campaign daily spending for promotions !');


        // =================== Migration for coupons ===================
        $idKey = $idKey;
        $coupons = Coupon::excludeDeleted()->get();

        if (count($coupons) > 0) {
            foreach ($coupons as $key => $valCoupon) {

                $campaignId = $valCoupon->promotion_id;
                $campaignType = 'coupon';
                $startDate = $valCoupon->begin_date;
                $endDate = $valCoupon->end_date;

                // Get mall per campaign
                $campaignHistory = CampaignHistory::select('merchants.parent_id', 'merchant_id', 'merchants.is_mall',
                    DB::raw("
                        CASE
                            WHEN is_mall = 'yes' and parent_id IS NULL THEN merchant_id
                            WHEN is_mall = 'yes' and parent_id IS NOT NULL THEN merchant_id
                            ELSE parent_id
                        END as 'mall_id'
                    "))
                    ->leftJoin('merchants', 'merchants.merchant_id', '=', 'campaign_histories.campaign_external_value')
                    ->where('campaign_external_value', '!=', '')
                    ->where('merchants.object_type', '!=', 'mall_group') // Mall group not included link to tenant
                    ->where('campaign_id', '=', $campaignId)
                    ->groupBy('mall_id')
                    ->get();

                foreach ($campaignHistory as $mall) {

                    $mallId = $mall->mall_id;

                    if ($mallId != '') {
                        $mallTimezone = $this->getTimezone($mallId);
                        $nowMall = Carbon::now($mallTimezone);
                        $dateNowMall = $nowMall->toDateString();

                        // Check end date campaign more than today
                        if ($endDate > $dateNowMall ) {
                            $endDate = $dateNowMall;
                        }

                        $procResults = DB::statement("CALL prc_campaign_detailed_cost( {$this->quote($campaignId)}, {$this->quote($campaignType)}, {$this->quote($startDate)}, {$this->quote($endDate)}, {$this->quote($mallId)})");

                        if ($procResults === false) {
                            // Do Nothing
                        }

                        $getSpending = DB::table(DB::raw('tmp_campaign_cost_detail'))
                            ->groupBy('date_in_utc')
                            ->get();

                        if (count($getSpending) > 0) {
                            foreach ($getSpending as $key => $valTmp) {
                                $dailySpending = new CampaignDailySpending();
                                $dailySpending->campaign_daily_spending_id = $idKey;
                                $dailySpending->date = $valTmp->date_in_utc;
                                $dailySpending->campaign_type = $campaignType;
                                $dailySpending->campaign_id = $campaignId;
                                $dailySpending->mall_id = $mallId;
                                $dailySpending->number_active_tenants = $valTmp->campaign_number_tenant;
                                $dailySpending->base_price = $valTmp->base_price;
                                $dailySpending->campaign_status = $valTmp->campaign_status;
                                $dailySpending->total_spending = $valTmp->daily_cost;
                                $dailySpending->save();
                                $idKey++;
                            }
                        }
                    }

                }

                $totalCampaign++;
                $this->info($totalCampaign . '. campaign_id = ' . $campaignId . ', campaign_type = '.$campaignType );

            }
        }

        $this->info('Success, Inserted campaign daily spending for coupon !');

        // =================== Check time ===================
        $totalInsertedSpending = $idKey - 1;
        $this->info('Migration successfully, Loaded time  = ' . (microtime(true) - $started_time) . ' seconds, total campaign data = ' . $totalCampaign . ', total inserted row to daily spending = ' . $totalInsertedSpending );

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