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

        DB::statement($this->getExpiredCampaignQuery('news'));
        $this->info("Success, Data News Updated!");
        $this->info("Success, Data Promotions Updated!");

        DB::statement($this->getExpiredCampaignQuery('coupons'));
        $this->info("Success, Data Coupons Updated!");

        DB::statement($this->getExpiredCampaignQuery('lucky_draws'));
        $this->info("Success, Data Lucky Draws Updated!");

    }

    /**
     * Get update campaign expired query.
     *
     * @return array
     */
    public function getExpiredCampaignQuery($campaign){
        $prefix = DB::getTablePrefix();
        $query = '';

        if ($campaign === 'news' || $campaign === 'promotions') {
            $query = "UPDATE {$prefix}news as old
                            SET old.campaign_status_id =
                                CASE
                                    WHEN old.campaign_status_id = (SELECT campaign_status_id FROM {$prefix}campaign_status where campaign_status_name = 'expired')
                                        THEN old.campaign_status_id
                                    ELSE
                                        (CASE
                                            WHEN (SELECT CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', ot.timezone_name)
                                                    FROM {$prefix}merchants om
                                                        LEFT JOIN {$prefix}timezones ot on ot.timezone_id = om.timezone_id
                                                    WHERE om.merchant_id = old.mall_id) > end_date
                                                THEN
                                                    (SELECT campaign_status_id FROM {$prefix}campaign_status where campaign_status_name = 'expired')
                                            ELSE old.campaign_status_id
                                        END)
                                END,
                            old.status =
                                CASE
                                    WHEN (SELECT CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', ot.timezone_name)
                                                    FROM {$prefix}merchants om
                                                        LEFT JOIN {$prefix}timezones ot on ot.timezone_id = om.timezone_id
                                                    WHERE om.merchant_id = old.mall_id) > end_date THEN 'inactive'
                                    ELSE old.status
                                END
                            ";
        }

        if ($campaign === 'coupons') {
            $query = "UPDATE {$prefix}promotions as old
                            SET old.campaign_status_id =
                                CASE
                                    WHEN old.campaign_status_id = (SELECT campaign_status_id FROM {$prefix}campaign_status where campaign_status_name = 'expired')
                                        THEN old.campaign_status_id
                                    ELSE
                                        (CASE
                                            WHEN (SELECT CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', ot.timezone_name)
                                                    FROM {$prefix}merchants om
                                                        LEFT JOIN {$prefix}timezones ot on ot.timezone_id = om.timezone_id
                                                    WHERE om.merchant_id = old.merchant_id) > end_date
                                                THEN
                                                    (SELECT campaign_status_id FROM {$prefix}campaign_status where campaign_status_name = 'expired')
                                            ELSE old.campaign_status_id
                                        END)
                                END,
                            old.status =
                                CASE
                                    WHEN (SELECT CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', ot.timezone_name)
                                                    FROM {$prefix}merchants om
                                                        LEFT JOIN {$prefix}timezones ot on ot.timezone_id = om.timezone_id
                                                    WHERE om.merchant_id = old.merchant_id) > end_date THEN 'inactive'
                                    ELSE old.status
                                END
                            ";
        }

        if ($campaign === 'lucky_draws') {
            $query = "UPDATE {$prefix}lucky_draws as old
                                SET old.campaign_status_id =
                                    CASE
                                        WHEN old.campaign_status_id = (SELECT campaign_status_id FROM {$prefix}campaign_status where campaign_status_name = 'expired')
                                            THEN old.campaign_status_id
                                        ELSE
                                            (CASE
                                                WHEN (SELECT CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', ot.timezone_name)
                                                    FROM {$prefix}merchants om
                                                        LEFT JOIN {$prefix}timezones ot on ot.timezone_id = om.timezone_id
                                                    WHERE om.merchant_id = old.mall_id) > end_date
                                                    THEN
                                                        (SELECT campaign_status_id FROM {$prefix}campaign_status where campaign_status_name = 'expired')
                                                ELSE old.campaign_status_id
                                            END)
                                    END,
                                old.status =
                                    CASE
                                        WHEN (SELECT CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', ot.timezone_name)
                                                    FROM {$prefix}merchants om
                                                        LEFT JOIN {$prefix}timezones ot on ot.timezone_id = om.timezone_id
                                                    WHERE om.merchant_id = old.mall_id) > end_date THEN 'inactive'
                                        ELSE old.status
                                    END
                                ";
        }

        return $query;
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
