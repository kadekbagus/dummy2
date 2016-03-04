<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

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
        $prefix = DB::getTablePrefix();

        $newsQuery = "UPDATE {$prefix}news as old
                        SET old.campaign_status_id =
                            CASE
                                WHEN old.campaign_status_id = (SELECT campaign_status_id FROM {$prefix}campaign_status where campaign_status_name = 'expired')
                                    THEN old.campaign_status_id
                                ELSE
                                    (CASE
                                        WHEN (SELECT CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', ot.timezone_name)
                                                FROM orb_merchants om
                                                    LEFT JOIN orb_timezones ot on ot.timezone_id = om.timezone_id
                                                WHERE om.merchant_id = old.mall_id) > end_date
                                            THEN
                                                (SELECT campaign_status_id FROM {$prefix}campaign_status where campaign_status_name = 'expired')
                                        ELSE old.campaign_status_id
                                    END)
                            END,
                        old.status =
                            CASE
                                WHEN (SELECT CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', ot.timezone_name)
                                                FROM orb_merchants om
                                                    LEFT JOIN orb_timezones ot on ot.timezone_id = om.timezone_id
                                                WHERE om.merchant_id = old.mall_id) > end_date THEN 'inactive'
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
                                        WHEN (SELECT CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', ot.timezone_name)
                                                FROM orb_merchants om
                                                    LEFT JOIN orb_timezones ot on ot.timezone_id = om.timezone_id
                                                WHERE om.merchant_id = old.merchant_id) > end_date
                                            THEN
                                                (SELECT campaign_status_id FROM {$prefix}campaign_status where campaign_status_name = 'expired')
                                        ELSE old.campaign_status_id
                                    END)
                            END,
                        old.status =
                            CASE
                                WHEN (SELECT CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', ot.timezone_name)
                                                FROM orb_merchants om
                                                    LEFT JOIN orb_timezones ot on ot.timezone_id = om.timezone_id
                                                WHERE om.merchant_id = old.merchant_id) > end_date THEN 'inactive'
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
                                            WHEN (SELECT CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', ot.timezone_name)
                                                FROM orb_merchants om
                                                    LEFT JOIN orb_timezones ot on ot.timezone_id = om.timezone_id
                                                WHERE om.merchant_id = old.mall_id) > end_date
                                                THEN
                                                    (SELECT campaign_status_id FROM {$prefix}campaign_status where campaign_status_name = 'expired')
                                            ELSE old.campaign_status_id
                                        END)
                                END,
                            old.status =
                                CASE
                                    WHEN (SELECT CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', ot.timezone_name)
                                                FROM orb_merchants om
                                                    LEFT JOIN orb_timezones ot on ot.timezone_id = om.timezone_id
                                                WHERE om.merchant_id = old.mall_id) > end_date THEN 'inactive'
                                    ELSE old.status
                                END
                            ";

        DB::statement($newsQuery);
        $this->info("Success, Data News Updated!");
        $this->info("Success, Data Promotions Updated!");

        DB::statement($couponQuery);
        $this->info("Success, Data Coupons Updated!");

        DB::statement($luckyDrawQuery);
        $this->info("Success, Data Lucky Draws Updated!");

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
