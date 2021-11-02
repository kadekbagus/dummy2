<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class DigitalProductStatusUpdateCommand extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'digital-product:update-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update digital product display status based on GTM product code.';

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
        try {
            $code = $this->option('code');
            $status = $this->option('status');
            $previousStatus = $status == 'active' ? 'inactive' : 'active';

            $digitalProduct = DigitalProduct::where('code', $code)
                ->first();

            if (is_object($digitalProduct)) {
                if ($digitalProduct->status == $status) {
                    $this->info($code . ' is already ' . $status . '. No changes made.');
                } elseif ($digitalProduct->status == $previousStatus) {
                    if ($digitalProduct->status == 'inactive' && $digitalProduct->updated_by != 'cron-job') {
                        $this->info($code . ' is made inactive by admin. No changes made.');
                    } else {
                        $digitalProduct->status = $status;
                        $digitalProduct->updated_by = 'cron-job';
                        $digitalProduct->save();

                        $this->info('Updating ' . $code . ' status to ' . $status . ' successful.');
                    }
                }
            } else {
                throw new Exception("Digital Product not found", 1);
            }
        } catch (\Exception $e) {
            $this->error($e->getMessage());
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
            array('code', null, InputOption::VALUE_REQUIRED, 'GTM Digital Product Code', null),
            array('status', null, InputOption::VALUE_REQUIRED, 'Update digital product status to', null),
        );
    }

}
