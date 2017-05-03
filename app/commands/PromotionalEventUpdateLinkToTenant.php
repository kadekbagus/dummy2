<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Orbit\FakeJob;
use Orbit\Queue\Elasticsearch\ESNewsUpdateQueue;

class PromotionalEventUpdateLinkToTenant extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'promotional-event:update-link-to-tenant';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Artisan command for update promotional-event link to tenant';

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

        $json = file_get_contents($file);
        return $this->readJSONString($json);
    }

    /**
     * Read JSON from string
     *
     * @return string|mixed
     */
    protected function readJSONString($json)
    {
        $conf = @json_decode($json, TRUE);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception( sprintf('Error parsing JSON: %s', json_last_error_msg()) );
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
            $data = '';
            $validation_data = [];
            $validation_error = [];

            if ($fileName === 'stdin') {
                $json = file_get_contents('php://stdin');
                $data = $this->readJSONString($json);
            } else {
                $data = $this->readJSON($fileName);
            }

            $dryRun = $this->option('dry-run');

            if ($dryRun) {
                $this->info('[DRY RUN MODE - Not Insert on DB] ');
            }

            $newsId = trim($data['news_id']);
            $deleteTenant = (array) $data['delete_tenant'];
            $addTenant = (array) $data['add_tenant'];

            $validation_data = [
                'news_id'     => $newsId,
                'delete_tenant' => $deleteTenant,
                'add_tenant'    => $addTenant
            ];

            $validation_error = [
                'news_id'     => 'required|orbit.empty.news_id'
            ];

            $news = News::leftJoin('campaign_account', 'campaign_account.user_id', '=', 'news.created_by')
                            ->where('news.news_id', $newsId)
                            ->first();

            if (count($deleteTenant) > 0) {
                foreach ($deleteTenant as $key => $del) {
                    $validation_data['delete_tenant_' . $del] = $del;
                    $validation_error['delete_tenant_' . $del] = 'orbit.empty.delete_tenant:' . $newsId;
                }
            }

            if (count($addTenant) > 0) {
                foreach ($addTenant as $key => $add) {
                    $validation_data['add_tenant_' . $add] = $add;
                    $validation_error['add_tenant_' . $add] = 'orbit.empty.add_tenant:' . $newsId;
                }
            }

            $this->registerCustomValidation();

            $validator = Validator::make(
                $validation_data,
                $validation_error
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                throw new Exception($errorMessage);
            }

            // delete link to tenat
            foreach ($deleteTenant as $key => $del) {
                $delTenant = NewsMerchant::where('news_id', $newsId)
                                            ->where('merchant_id', $del)
                                            ->delete('true');

                $this->info(sprintf('Delete merchant_id %s, in campaign %s.', $del, $newsId));
            }

            // add link to tenant
            foreach ($addTenant as $key => $add) {
                $objectType = CampaignLocation::select('object_type') ->where('merchant_id', $add)->first();

                $newsMerchant = new NewsMerchant();
                $newsMerchant->merchant_id = $add;
                $newsMerchant->news_id = $newsId;
                $newsMerchant->object_type = $objectType->object_type;
                $newsMerchant->save();

                $this->info(sprintf('Add merchant_id %s, in campaign %s.', $add, $newsId));
            }

            // update es
            $job = new FakeJob();
            $data = [
                'news_id' => $newsId
            ];

            $esQueue = new ESNewsUpdateQueue();
            $response = $esQueue->fire($job, $data);
            $this->info("Done");
        } catch (Exception $e) {
            $this->error('Line #' . $e->getLine() . ': ' . $e->getMessage());
        }
    }

    protected function registerCustomValidation()
    {
        // Check the existance of mall country
        Validator::extend('orbit.empty.news_id', function ($attribute, $value, $parameters) {
            $news = News::where('news_id', $value)->where('is_having_reward', 'Y')->first();

            if (! is_object($news)) {
                return FALSE;
            }

            return TRUE;
        });

        // Check the existance of mall city
        Validator::extend('orbit.empty.delete_tenant', function ($attribute, $value, $parameters) {
            $newsId = $parameters[0];

            $checkDelete = NewsMerchant::where('news_id', $newsId)
                                            ->where('merchant_id', $value)
                                            ->first();

            if (! is_object($checkDelete)) {
                return FALSE;
            }

            return TRUE;
        });

        // Check the existance of mall city
        Validator::extend('orbit.empty.add_tenant', function ($attribute, $value, $parameters) {
            $newsId = $parameters[0];

            $checkAdd = NewsMerchant::where('news_id', $newsId)
                                            ->where('merchant_id', $value)
                                            ->first();

            if (is_object($checkAdd)) {
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
            array('dry-run', null, InputOption::VALUE_NONE, 'Dry run, do not Insert to db_ip_cities.', null),
            array('json-file', null, InputOption::VALUE_REQUIRED, 'JSON file.'),
        );
    }

}
