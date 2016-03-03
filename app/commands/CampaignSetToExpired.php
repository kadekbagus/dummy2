<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Carbon\Carbon as Carbon;


class CampaignSetToExpired extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'campaign:set-to-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set campaign status to expired when past the end date and time';

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
        $mallId = $this->option('merchant_id');
        $campaign = strtolower($this->option('campaign'));
        $campaigns = ['news', 'promotions', 'coupons', 'lucky_draws'];

        $mall = Mall::with('timezone')
                      ->where('status', '!=', 'deleted')
                      ->where('merchant_id', '=', $mallId)
                      ->first();

        if (empty($mall)) {
            $this->error('Merchant or mall is not found.');
        }

        if (! in_array($campaign, $campaigns)) {
            $this->error('Campaign is not found.');
        }

        $mallTime = Carbon::now($mall->timezone->timezone_name);
        $prefix = DB::getTablePrefix();

        $newsQuery = "UPDATE {$prefix}news as old
                        SET old.campaign_status_id =
                            CASE
                                WHEN old.campaign_status_id = (SELECT campaign_status_id FROM {$prefix}campaign_status where campaign_status_name = 'expired')
                                    THEN old.campaign_status_id
                                ELSE
                                    (CASE
                                        WHEN {$this->quote($mallTime)} > end_date
                                            THEN
                                                (SELECT campaign_status_id FROM {$prefix}campaign_status where campaign_status_name = 'expired')
                                        ELSE old.campaign_status_id
                                    END)
                            END,
                        old.status =
                            CASE
                                WHEN {$this->quote($mallTime)} > end_date THEN 'inactive'
                                ELSE old.status
                            END
                        ";

        $couponQuery = "UPDATE {$prefix}promotions as old
                        SET old.campaign_status_id =
                            CASE
                                WHEN old.campaign_status_id = (SELECT campaign_status_id FROM {$prefix}campaign_status where campaign_status_name = 'expired')
                                    THEN old.campaign_status_id
                                ELSE
                                    (CASE
                                        WHEN {$this->quote($mallTime)} > end_date
                                            THEN
                                                (SELECT campaign_status_id FROM {$prefix}campaign_status where campaign_status_name = 'expired')
                                        ELSE old.campaign_status_id
                                    END)
                            END,
                        old.status =
                            CASE
                                WHEN {$this->quote($mallTime)} > end_date THEN 'inactive'
                                ELSE old.status
                            END
                        ";

        $luckyDrawQuery = "UPDATE {$prefix}lucky_draws as old
                            SET old.campaign_status_id =
                                CASE
                                    WHEN old.campaign_status_id = (SELECT campaign_status_id FROM {$prefix}campaign_status where campaign_status_name = 'expired')
                                        THEN old.campaign_status_id
                                    ELSE
                                        (CASE
                                            WHEN {$this->quote($mallTime)} > end_date
                                                THEN
                                                    (SELECT campaign_status_id FROM {$prefix}campaign_status where campaign_status_name = 'expired')
                                            ELSE old.campaign_status_id
                                        END)
                                END,
                            old.status =
                                CASE
                                    WHEN {$this->quote($mallTime)} > end_date THEN 'inactive'
                                    ELSE old.status
                                END
                            ";

        if ($campaign === 'news' || $campaign ==='promotions') {
            DB::statement($newsQuery);
            $campaign = ucfirst($campaign);
            $this->info("Success, Data {$campaign} Updated!");
        }
        if ($campaign === 'coupons') {
            DB::statement($couponQuery);
            $this->info("Success, Data Coupons Updated!");
        }
        if ($campaign === 'lucky_draws') {
            DB::statement($luckyDrawQuery);
            $this->info("Success, Data Lucky Draws Updated!");
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
            array('campaign', null, InputOption::VALUE_REQUIRED, 'Type of campaign ( news, coupons, promotions, lucky_draws )'),
        );
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }

}
