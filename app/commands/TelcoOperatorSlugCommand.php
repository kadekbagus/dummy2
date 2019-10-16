<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class TelcoOperatorSlugCommand extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'telco:generate-slug';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description.';

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
            $take = 50;
            $skip = 0;

            do {
                $telcos = TelcoOperator::select('telco_operator_id', 'name')
                                       ->skip($skip)
                                       ->take($take)
                                       ->get();

                $skip = $take + $skip;

                foreach ($telcos as $telco) {
                    $telcoUpdate = TelcoOperator::where('telco_operator_id', '=', $telco->telco_operator_id)->first();
                    $telcoUpdate->slug = Str::slug($telcoUpdate->name);
                    $telcoUpdate->save();
                    $this->info(sprintf('telco "%s" telco_operator_id "%s" has been successfully update', $telco->name, $telco->telco_operator_id));
                }
            } while (count($telcos) > 0);

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
        );
    }

}
