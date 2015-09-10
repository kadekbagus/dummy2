<?php
/**
 * Update setting based on setting_name and merchant_id
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class MerchantSetting extends Command
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'config:merchant-setting';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Insert or update a setting for particular merchant/mall based on its id.';

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
        $merchant = DB::table('merchants')
                      ->where('status', '!=', 'deleted')
                      ->where('merchant_id', '=', $mercahntId)
                      ->first();

        if (empty($merchant)) {
            $this->error('Merchant or mall is not found.');
        }

        $name = $this->option('setting_name');
        $value = $this->option('setting_value');

        $setting = DB::table('settings')
                     ->where('setting_name', $name)
                     ->where('object_id', $merchantId)
                     ->where('object_type', 'merchant')
                     ->first();

        $value = $this->option('setting_value');
        if (empty($setting)) {
            // Insert
            DB::table('settings')->insert([
                'setting_name' => $name,
                'setting_value' => $value,
                'object_id' => $merchantId,
                'object_type' => 'merchant'
            ]);
        } else {
            // Update
            DB::table('settings')
              ->where('setting_name', $name)
              ->where('object_id', $merchantId)
              ->where('object_type', 'merchant')
              ->update(['setting_value' => $value]);
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
            array('merchant_id', null, InputArgument::REQUIRED, 'Mall or Merchant ID.'),
            array('setting_name', null, InputArgument::REQUIRED, 'Name of the setting.'),
            array('setting_value', null, InputArgument::REQUIRED, 'Value of the setting.'),
        );
    }

}
