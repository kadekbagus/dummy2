<?php
/**
 * @author Tian <tian@dominopos.com>
 * @desc Insert or delete agreement on settings table
 */
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class ConfigAgreement extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'config:agreement';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Insert or delete agreement on settings table.';

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
        $mallId = $this->option('retailerid');
        $setting_value = $this->option('status');

        $setting_name = 'agreement';
        $object_type = 'merchant';
        $status = 'active';

        $updatedsetting = Setting::excludeDeleted()
                                 ->where('object_type', $object_type)
                                 ->where('object_id', $mallId)
                                 ->where('setting_name', $setting_name)
                                 ->where('status', $status)
                                 ->first();

        if (empty($updatedsetting)) {
            // do insert
            $this->info('Creating agreement setting...');
            $updatedsetting = new Setting();
            $updatedsetting->setting_name = $setting_name;
            $updatedsetting->setting_value = $setting_value;
            $updatedsetting->object_id = $mallId;
            $updatedsetting->object_type = $object_type;
            $updatedsetting->status = $status;

            $updatedsetting->save();
        } else {
            // do update
            $this->info('Updating agreement setting...');
            $updatedsetting->setting_value = $setting_value;

            $updatedsetting->save();
        }

        $this->info('Done.');
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
            array('retailerid', null, InputOption::VALUE_REQUIRED, 'Retailer ID or Mall ID.', null),
            array('status', null, InputOption::VALUE_REQUIRED, 'Status: yes or no.', null),
        );
    }

}
