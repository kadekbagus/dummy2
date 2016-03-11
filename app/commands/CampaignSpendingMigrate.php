<?php

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
        $deletedTable = CampaignSpendingCount::truncate();

        if ($deletedTable) {

            $this->info("Success, truncated table campaign_spending !");

        	// Get all news data
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
            $this->info("Success, coupon data inserted !");
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

}
