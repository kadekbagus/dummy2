<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class PulsaStatusUpdateCommand extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'pulsa:update-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update pulsa display status based on MCash pulsa code.';

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

            $pulsa = Pulsa::where('pulsa_code', $code)
                ->first();

            if (is_object($pulsa)) {
                if ($pulsa->status == $status) {
                    $this->info($code . ' is already ' . $status . '. No changes made.');
                } elseif ($pulsa->status == $previousStatus) {
                    if ($pulsa->status == 'inactive' && $pulsa->updated_by != 'cron-job') {
                        $this->info($code . ' is made inactive by admin. No changes made.');
                    } else {
                        $pulsa->status = $status;
                        $pulsa->updated_by = 'cron-job';
                        $pulsa->save();

                        $this->info('Updating ' . $code . ' status to ' . $status . ' successful.');
                    }
                }
            } else {
                throw new Exception("Pulsa not found", 1);
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
            array('code', null, InputOption::VALUE_REQUIRED, 'MCash Pulsa Code', null),
            array('status', null, InputOption::VALUE_REQUIRED, 'Update pulsa product status to', null),
        );
    }

}
