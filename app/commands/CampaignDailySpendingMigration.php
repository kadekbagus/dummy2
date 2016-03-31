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
        $timeStart = microtime(true);

        // ========================================================================

        // Truncate first before inserted data
        $deletedTable = CampaignDailySpending::truncate();

        // Check all campaign existing (news, promotions, coupons)
        if (! $deletedTable ){
            // Do Nothing
        }

        $this->info("Success, truncated table campaign_spending !");

        // =================== Migration for news and promotions ===================
        $idKeyNewsPromotions = 1;
        $newsAndPromotions = News::excludeDeleted()
        // ->where('news_id', 'Ki2WQzxo8OpvWTF1')
        ->get();

        if (count($newsAndPromotions) > 0) {
            foreach ($newsAndPromotions as $key => $valNewsPromotions) {

                $campaignId = $valNewsPromotions->news_id;
                $campaignType = $valNewsPromotions->object_type;
                $startDate = $valNewsPromotions->begin_date;
                $endDate = $valNewsPromotions->end_date;

                // Get mall per campaign
                $campaignHistory = CampaignHistory::select('merchants.parent_id as mall_id')
                    ->leftJoin('merchants', 'merchants.merchant_id', '=', 'campaign_histories.campaign_external_value')
                    ->where('campaign_external_value', '!=', '')
                    ->where('campaign_id', '=', $campaignId)
                    ->groupBy('merchants.parent_id')
                    ->get();

                foreach ($campaignHistory as $mall) {
                    // Check end date campaign more than today
                    $mallId = $mall->mall_id;
                    $mallTimezone = $this->getTimezone($mallId);
                    $nowMall = Carbon::now($mallTimezone);
                    $dateNowMall = $nowMall->toDateString();

                    if ($endDate < $dateNowMall ) {
                        $endDate = $dateNowMall;
                    }

                    $procResults = DB::statement("CALL prc_campaign_detailed_cost( {$this->quote($campaignId)}, {$this->quote($campaignType)}, {$this->quote($startDate)}, {$this->quote($endDate)}, {$this->quote($mallId)})");


                    if ($procResults === false) {
                        // Do Nothing
                    }

                    $getSpending = DB::table(DB::raw('tmp_campaign_cost_detail'))
                        ->groupBy('date_in_utc')
                        ->get();

                    if (count($getSpending)) {
                        foreach ($getSpending as $key => $valTmp) {
                            $dailySpending = new CampaignDailySpending();
                            $dailySpending->campaign_daily_spending_id = $idKeyNewsPromotions;
                            $dailySpending->date = $valTmp->date_in_utc;
                            $dailySpending->campaign_type = $campaignType;
                            $dailySpending->campaign_id = $campaignId;
                            $dailySpending->mall_id = $mallId;
                            $dailySpending->number_active_tenants = $valTmp->campaign_number_tenant;
                            $dailySpending->base_price = $valTmp->base_price;
                            $dailySpending->campaign_status = $valTmp->campaign_status;
                            $dailySpending->total_spending = $valTmp->daily_cost;
                            $dailySpending->save();
                            $idKeyNewsPromotions++;
                        }
                    }
                }

            }
        }
        $this->info('Success, Inserted campaign daily spending for news !');
        $this->info('Success, Inserted campaign daily spending for promotions !');


        // =================== Migration for coupons ===================
        $idKeyCoupon = $idKeyNewsPromotions + 1;
        $coupons = Coupon::excludeDeleted()->get();

        if (count($coupons) > 0) {
            foreach ($coupons as $key => $valCoupon) {

                $campaignId = $valCoupon->promotion_id;
                $campaignType = 'coupon';
                $startDate = $valCoupon->begin_date;
                $endDate = $valCoupon->end_date;

                // Get mall per campaign
                $campaignHistory = CampaignHistory::select('merchants.parent_id as mall_id')
                    ->leftJoin('merchants', 'merchants.merchant_id', '=', 'campaign_histories.campaign_external_value')
                    ->where('campaign_external_value', '!=', '')
                    ->where('campaign_id', '=', $campaignId)
                    ->groupBy('merchants.parent_id')
                    ->get();

                foreach ($campaignHistory as $mall) {
                    // Check end date campaign more than today
                    $mallId = $mall->mall_id;
                    $mallTimezone = $this->getTimezone($mallId);
                    $nowMall = Carbon::now($mallTimezone);
                    $dateNowMall = $nowMall->toDateString();

                    if ($endDate < $dateNowMall ) {
                        $endDate = $dateNowMall;
                    }

                    $procResults = DB::statement("CALL prc_campaign_detailed_cost( {$this->quote($campaignId)}, {$this->quote($campaignType)}, {$this->quote($startDate)}, {$this->quote($endDate)}, {$this->quote($mallId)})");


                    if ($procResults === false) {
                        // Do Nothing
                    }

                    $getSpending = DB::table(DB::raw('tmp_campaign_cost_detail'))
                        ->groupBy('date_in_utc')
                        ->get();

                    if (count($getSpending)) {
                        foreach ($getSpending as $key => $valTmp) {
                            $dailySpending = new CampaignDailySpending();
                            $dailySpending->campaign_daily_spending_id = $idKeyCoupon;
                            $dailySpending->date = $valTmp->date_in_utc;
                            $dailySpending->campaign_type = $campaignType;
                            $dailySpending->campaign_id = $campaignId;
                            $dailySpending->mall_id = $mallId;
                            $dailySpending->number_active_tenants = $valTmp->campaign_number_tenant;
                            $dailySpending->base_price = $valTmp->base_price;
                            $dailySpending->campaign_status = $valTmp->campaign_status;
                            $dailySpending->total_spending = $valTmp->daily_cost;
                            $dailySpending->save();
                            $idKeyCoupon++;
                        }
                    }
                }


            }
        }
        $this->info('Success, Inserted campaign daily spending for coupon !');

        // =================== Check time ===================

        $diff = microtime(true) - $timeStart;
        $sec = intval($diff);
        $micro = ($diff - $sec);

        $this->info('Success, This migration. Loaded time = ' . $micro . ' ms');
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