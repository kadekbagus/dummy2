<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class CampaignSpendingCalculation extends Command {

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'campaign:spending';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Counting campaign spending and save to {prefix}_campaign_spending.';

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
        $prefix = DB::getTablePrefix();

        // Get mall which have timezone
        $getMall = Mall::select('merchant_id','name','timezone_name',DB::raw(" DATE_FORMAT(CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', timezone_name), '%H:%i') AS tz "))
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

            // if ($val->tz === '00:00') {
                // Get all campaign news from mall
            	$news = DB::statement("
            							UPDATE {$prefix}campaign_spending as old
										SET spending = (SELECT IFNULL(fnc_campaign_cost(old.campaign_id, 'news', old.begin_date, {$this->quote($now)}, {$this->quote($timezoneOffset)}), 0.00))
										WHERE old.mall_id = {$this->quote($val->merchant_id)}
										AND campaign_type = 'news'
									");
            	if ($news) {
            		$this->info("Success, news campaign spending in mall_id " . $val->merchant_id . " updated !");
            	}

            	$promotion = DB::statement("
            							UPDATE {$prefix}campaign_spending as old
										SET spending = (SELECT IFNULL(fnc_campaign_cost(old.campaign_id, 'promotion', old.begin_date, {$this->quote($now)}, {$this->quote($timezoneOffset)}), 0.00))
										WHERE old.mall_id = {$this->quote($val->merchant_id)}
										AND campaign_type = 'promotion'
									");

            	if ($promotion) {
            		$this->info("Success, promotion campaign spending in mall_id " . $val->merchant_id . " updated !");
            	}

            	$coupon = DB::statement("
            							UPDATE {$prefix}campaign_spending as old
										SET spending = (SELECT IFNULL(fnc_campaign_cost(old.campaign_id, 'coupon', old.begin_date, {$this->quote($now)}, {$this->quote($timezoneOffset)}), 0.00))
										WHERE old.mall_id = {$this->quote($val->merchant_id)}
										AND campaign_type = 'coupon'
									");

            	if ($coupon) {
            		$this->info("Success, coupon campaign spending in mall_id " . $val->merchant_id . " updated !");
            	}
            // }

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
            // array('migrate', null,  InputOption::VALUE_REQUIRED, 'Y for first setup / N for cronjob'),
			// array('example', null, InputOption::VALUE_OPTIONAL, 'An example option.', null),
		);
	}

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }

}
