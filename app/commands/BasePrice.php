<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class BasePrice extends Command {

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'campaign:base-price';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Insert campaign base price for merchant';

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
		$merchantId = $this->option('merchant_id');
        $merchant = Mall::where('status', '!=', 'deleted')
                      ->where('merchant_id', '=', $merchantId)
                      ->first();

        if (empty($merchant)) {
            $this->error('Merchant or mall is not found.');
        }

        $type = $this->option('type');
        $price = $this->option('price');

        $baseprice = CampaignBasePrice::where('merchant_id', $merchantId)
                     ->where('campaign_type', $type)
                     ->first();

        if (empty($baseprice)) {
            // Insert
            $campaignbaseprice = new CampaignBasePrice();
            $campaignbaseprice->merchant_id = $merchantId;
            $campaignbaseprice->price = $price;
            $campaignbaseprice->campaign_type = $type;
            $campaignbaseprice->status = 'active';
            $campaignbaseprice->save();

            $this->info("Success, Data Inserted!");
        } else {
            // Update
            $baseprice->price = $price;
            $baseprice->save();

            $this->info("Success, Data Updated!");
        }
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
		return array(
            array('merchant_id', null, InputOption::VALUE_REQUIRED, 'Mall or Merchant ID.'),
            array('type', null, InputOption::VALUE_REQUIRED, 'Type of campaign ( news, coupon, promotion )'),
            array('price', null, InputOption::VALUE_REQUIRED, 'Base price for type of campaign'),
            
        );
	}

}
