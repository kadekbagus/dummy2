<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class CreatePartnerCompetitorCommand extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'partner:create-competitor';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command for import partner link from json file. ';

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
     * Read the json file.
     */
    protected function readJSON($file)
    {
        if (! file_exists($file) ) {
           throw new Exception('Could not found json file.');
        }

        $conf = @json_decode(file_get_contents($file), true);
        $basefile = $basefile = basename($file);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception( sprintf('Error JSON %s: %s', $basefile, json_last_error_msg()) );
        }

        return $conf;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function fire()
    {
        try {

            $fileName = $this->option('json-file');
            $basefile = basename($fileName);

            $data = $this->readJSON($fileName);
            $partner_id = $this->option('partner-id');
            $competitors = $data['competitors'];
            $unique_competitors = array_unique($competitors);

            // duplicate competitor id validation
            if (count($competitors) !== count($unique_competitors)) {
                $get_duplicate = array_diff_key($competitors, $unique_competitors);
                $errorMessage = '';

                foreach ($get_duplicate as $idx => $competitor_id) {
                    $errorMessage = $errorMessage . "\n" . sprintf('Row %s Partner competitor with id %s has duplicate.', $idx, $competitor_id);
                }

                throw new Exception($errorMessage);
            }

            // validatoin partner id is exists
            $this->registerCustomValidation();

            $validator = Validator::make(
                array(
                    'partner_id'  => $partner_id,
                    'competitors' => $competitors,
                ),
                array(
                    'partner_id'  => 'required|orbit.exist.partner_id',
                    'competitors' => 'required|array',
                ),
                array(
                    'orbit.exist.partner_id' => 'Partner ID does not exist',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                throw new Exception($errorMessage);
            }

            DB::beginTransaction();

            // delete partner competitor before insert
            $partner_competitor = PartnerCompetitor::where('partner_id', $partner_id)->delete(TRUE);
            // print information
            $this->info( sprintf('Partner id %s competitors successfully deleted.', $partner_id) );

            foreach ($unique_competitors as $competitor_id) {
                // validation support type
                $validator = Validator::make(
                    array(
                        'competitor_id' => $competitor_id,
                    ),
                    array(
                        'competitor_id' => 'required|orbit.exist.partner_id',
                    ),
                    array(
                        'orbit.exist.partner_id' => 'Competitor Partner ID does not exist',
                    )
                );

                // Run the validation
                if ($validator->fails()) {
                    $errorMessage = $validator->messages()->first();
                    throw new Exception($errorMessage);
                }

                // insert to db
                $newpartnercompetitor = new PartnerCompetitor();
                $newpartnercompetitor->partner_id = $partner_id;
                $newpartnercompetitor->competitor_id = $competitor_id;
                $newpartnercompetitor->save();

                // print information
                $this->info( sprintf('Partner id %s successfully link to competitor with id %s.', $partner_id, $competitor_id) );
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollback();
            $this->error('Line #' . $e->getLine() . ': ' . $e->getMessage());
        }
    }


    protected function registerCustomValidation()
    {
        // Check the partner id is exists
        Validator::extend('orbit.exist.partner_id', function ($attribute, $value, $parameters) {
            $checkPartnerId = Partner::excludeDeleted()->where('partner_id', $value)->first();

            if (empty($checkPartnerId)) {
                return FALSE;
            }

            return TRUE;
        });
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
            array('json-file', null, InputOption::VALUE_REQUIRED, 'JSON file.'),
            array('partner-id', null, InputOption::VALUE_REQUIRED, 'Partner ID.'),
        );
    }

}
