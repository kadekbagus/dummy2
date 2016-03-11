<?php

/**
 * This artisan command for migrate old campaign spending calculation and saved the calculation to campaign_spending table
 *
 * @author Firmansyah <firmansyah@myorbit.com>
 */

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class CampaignSpendingMigrate extends Command {

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'campaign:spending-migrate';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Insert campaign spending per campaign type and per mall';

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
		// Migrate old campaign
		// Truncate first before inserted data
        $deletedTable = CampaignSpendingCount::truncate();

        if ($deletedTable) {

            $this->info("Success, truncated table campaign_spending !");

        	// Get all news data
        	// @todo check uuid duplicate
        	$idKey = 0;
        	$newsAndPromotions = News::excludeDeleted()->get();
        	if (count($newsAndPromotions) > 0) {
        		foreach ($newsAndPromotions as $key => $valNewsPromotions) {
        			$spendingNewsPromotion = new CampaignSpendingCount();
					$spendingNewsPromotion->campaign_spending_id = $idKey;
					$spendingNewsPromotion->campaign_id = $valNewsPromotions->news_id;
					$spendingNewsPromotion->campaign_type = $valNewsPromotions->object_type;
					$spendingNewsPromotion->spending = 0;
					$spendingNewsPromotion->mall_id = $valNewsPromotions->mall_id;
					$spendingNewsPromotion->begin_date = $valNewsPromotions->begin_date;
					$spendingNewsPromotion->end_date = $valNewsPromotions->end_date;
					$spendingNewsPromotion->save();
					$idKey++;
        		}
        	}

            $this->info("Success, news data inserted !");
            $this->info("Success, promotions data inserted !");

            $idKeyCoupon = $idKey + 1;
        	$coupons = Coupon::excludeDeleted()->get();
        	if (count($coupons) > 0) {
        		foreach ($coupons as $key => $valCoupon) {
        			$spendingCoupons = new CampaignSpendingCount();
					$spendingCoupons->campaign_spending_id = $idKeyCoupon;
					$spendingCoupons->campaign_id = $valCoupon->promotion_id;
					$spendingCoupons->campaign_type = 'coupon';
					$spendingCoupons->spending = 0;
					$spendingCoupons->mall_id = $valCoupon->merchant_id;
					$spendingCoupons->begin_date = $valCoupon->begin_date;
					$spendingCoupons->end_date = $valCoupon->end_date;
					$spendingCoupons->save();
					$idKeyCoupon++;
        		}
        	}
            $this->info("Success, coupons data inserted !");
        }

        // Calculate
        $prefix = DB::getTablePrefix();

        // Get mall which have timezone
        $getMall = Mall::select('merchant_id','name','timezone_name',DB::raw(" DATE_FORMAT(CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', timezone_name), '%H') AS tz "))
                ->leftJoin('timezones', 'timezones.timezone_id', '=', 'merchants.timezone_id')
                ->where('object_type','mall')
                ->where('status', '!=', 'deleted')
                ->where('merchants.timezone_id','!=', '')
                ->get();

        // Get all mall
        foreach ($getMall as $key => $val) {

            // get offset timezone
            $dt = new DateTime('now', new DateTimeZone($val->timezone_name));
            $now = $dt->format('Y-m-d');
            $timezoneOffset = $dt->format('P');

        	$news = DB::statement("
        							UPDATE {$prefix}campaign_spendings as old
									SET spending = (SELECT IFNULL(fnc_campaign_cost(old.campaign_id, 'news', old.begin_date, {$this->quote($now)}, {$this->quote($timezoneOffset)}), 0.00))
									WHERE old.mall_id = {$this->quote($val->merchant_id)}
									AND campaign_type = 'news'
								");
        	if ($news) {
        		$this->info("Success, news campaign spending in mall_id " . $val->merchant_id . " updated !");
        	}

        	$promotion = DB::statement("
        							UPDATE {$prefix}campaign_spendings as old
									SET spending = (SELECT IFNULL(fnc_campaign_cost(old.campaign_id, 'promotion', old.begin_date, {$this->quote($now)}, {$this->quote($timezoneOffset)}), 0.00))
									WHERE old.mall_id = {$this->quote($val->merchant_id)}
									AND campaign_type = 'promotion'
								");

        	if ($promotion) {
        		$this->info("Success, promotion campaign spending in mall_id " . $val->merchant_id . " updated !");
        	}

        	$coupon = DB::statement("
        							UPDATE {$prefix}campaign_spendings as old
									SET spending = (SELECT IFNULL(fnc_campaign_cost(old.campaign_id, 'coupon', old.begin_date, {$this->quote($now)}, {$this->quote($timezoneOffset)}), 0.00))
									WHERE old.mall_id = {$this->quote($val->merchant_id)}
									AND campaign_type = 'coupon'
								");

        	if ($coupon) {
        		$this->info("Success, coupon campaign spending in mall_id " . $val->merchant_id . " updated !");
        	}

        }

	}

	/**
	 * Get the console command arguments.
	 *
	 * @return array
	 */
	protected function getArguments()
	{
		return array(
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
		);
	}

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }

}
