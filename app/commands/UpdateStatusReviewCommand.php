<?php
/**
 * Artisan command to update Review approval_status to pending
 * expected input are array of review _id: eg. ['59796d594340d712fb4a7d44', '59797bb34340d712fb4a7d45']
 */
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Orbit\Helper\MongoDB\Client as MongoClient;
use Carbon\Carbon;

class UpdateStatusReviewCommand extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'update:status-review';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Artisan command to update Review approval_status to pending expected input are array of review _id: eg. ['59796d594340d712fb4a7d44', '59797bb34340d712fb4a7d45']";

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
            $status = $this->option('status');
            $approval_status = $this->option('approval-status');

            $data = '';

            if ($fileName === 'stdin') {
                $json = file_get_contents('php://stdin');
                $data = $this->readJSONString($json);
            } else {
                $data = $this->readJSON($fileName);
            }

            $dryRun = $this->option('dry-run');

            if ($dryRun) {
                $this->info('[DRY RUN MODE] ');
                $this->info("Review IDs : \n" . implode("\n", $data));
                return;
            }

            $reviewIds = (array) $data;

            $validation_data = [
                'review_ids'        => $reviewIds,
                'approval_status'   => $approval_status,
                'status'            => $status,
            ];

            $validation_error = [
                'review_ids'        => 'required|array',
                'approval_status'   => 'in:active,pending',
                'status'            => 'in:active,pending',
            ];

            $validator = Validator::make(
                $validation_data,
                $validation_error
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                throw new Exception($errorMessage);
            }

            if (! empty($status)) {
                $body['status'] = $status;
            }
            if (! empty($approval_status)) {
                $body['approval_status'] = $approval_status;
            }
            if (! empty($status) || ! empty($approval_status)) {
                $timestamp = date("Y-m-d H:i:s");
                $date = Carbon::createFromFormat('Y-m-d H:i:s', $timestamp, 'UTC');
                $dateTime = $date->toDateTimeString();

                $body['updated_at'] = $dateTime;
            }

            if (isset($body) && ! empty($body)) {
                $queryString = [];
                foreach ($reviewIds as $key => $reviewId) {
                    $queryString['review_ids'][$key] = $reviewId;
                }

                $mongoConfig = Config::get('database.mongodb');
                $mongoClient = MongoClient::create($mongoConfig);
                $endPoint = "reviews-status";

                $response = $mongoClient->setFormParam($body)
                                        ->setQueryString($queryString)
                                        ->setEndPoint($endPoint)
                                        ->request('PUT');

                $listOfRec = $response->data;

                $this->info("Done");
            } else {
                $this->info("Nothing changed.");
            }
        } catch (Exception $e) {
            $this->error('Line #' . $e->getLine() . ': ' . $e->getMessage());
        }
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
            array('approval-status', null, InputOption::VALUE_OPTIONAL, 'Optional. Review approval status, in: active, pending.'),
            array('status', null, InputOption::VALUE_OPTIONAL, 'Optional. Review Status, in: active, pending.'),
            array('json-file', null, InputOption::VALUE_REQUIRED, 'JSON file.'),
            array('dry-run', null, InputOption::VALUE_NONE, 'Dry run, not updating, only showing inputs.', null),
        );
    }

}
