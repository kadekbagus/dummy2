<?php
/**
 * Merchant or Retailer Activation. It should be executed by cron at some
 * interval. Example below use midnight as an example.
 *
 * # Change the status of merchant and retailer
 * 0 0 * * * cd /var/www/production/orbit-shop && /usr/bin/php artisan merchant:activation
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class merchantActivation extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'merchant:activation';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Merchant or retailer activation based on their starting and end date.';

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
        $type = $this->option('type');
        $prefix = DB::getTablePrefix();
        $objectTypeQuery = '';
        $query = "-- Set status to 'active' when now is between start and end date activity
update {{PREFIX}}merchants set status='active' where {{OBJECT_TYPE_QUERY}}
date(start_date_activity) is not null and date(end_date_activity) is not null and
(
  curdate() between date(start_date_activity) and date(end_date_activity)
) and status != 'deleted';

-- Set status to 'inactive' when now is not between start and end date activity
update {{PREFIX}}merchants set status='inactive' where {{OBJECT_TYPE_QUERY}}
date(start_date_activity) is not null and date(end_date_activity) is not null and
(
  curdate() not between date(start_date_activity) and date(end_date_activity)
) and status != 'deleted';

-- Set status to 'active' when now is greater then start date and end date activity is null
update {{PREFIX}}merchants set status='active' where {{OBJECT_TYPE_QUERY}}
date(start_date_activity) is not null and
(date(end_date_activity) is null or date(end_date_activity) = '0000-00-00') and
curdate() >= start_date_activity and status != 'deleted';

-- Set status to 'inactive' when now is less then start date and end date activity is null
update {{PREFIX}}merchants set status='inactive' where {{OBJECT_TYPE_QUERY}}
date(start_date_activity) is not null and
(date(end_date_activity) is null or date(end_date_activity) = '0000-00-00') and
curdate() < start_date_activity and status != 'deleted';

-- Set status to 'active' when start date and end date activity is null
update {{PREFIX}}merchants set status='active' where {{OBJECT_TYPE_QUERY}}
(date(start_date_activity) is null or date(start_date_activity) = '0000-00-00') and
(date(end_date_activity) is null or date(end_date_activity) = '0000-00-00') and
status != 'deleted';";

        // Replace the table prefix
        $query = str_replace('{{PREFIX}}', $prefix, $query);

        switch ($type) {
            case 'retailer':
                $this->info('Changing status of retailer.');
                $objectTypeQuery = sprintf("object_type='%s' and ", 'retailer');
                break;

            case 'merchant':
                $this->info('Changing status of merchant.');
                $objectTypeQuery = sprintf("object_type='%s' and ", 'merchant');
                break;

            default:
                $this->info('Changing status of merchant and retailer.');
        }

        $query = str_replace('{{OBJECT_TYPE_QUERY}}', $objectTypeQuery, $query);

        // Execute the RAW SQL
        $this->info("\nExecuting Queries: \n" . $query);
        DB::unprepared($query);
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
            array('type', null, InputOption::VALUE_OPTIONAL, 'Type of activation could be \'merchant\' or \'retailer\'.', 'all'),
        );
    }

}
