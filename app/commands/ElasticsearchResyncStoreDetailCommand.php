<?php
/**
 * Command for resync store data from MySQL to Elasticsearch based on store name and country
 * @author shelgi<shelgi@dominopos.com>
 */

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Orbit\FakeJob;
use Orbit\Queue\Elasticsearch\ESStoreDetailUpdateQueue;

class ElasticsearchResyncStoreDetailCommand extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'elasticsearch:resync-store-detail';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Resync store data from MySQL to Elasticsearch based on store name';

    /**
     * Prefix for message list.
     *
     * @var string
     */
    protected $stdoutPrefix = '';

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
            $name = ! empty($this->option('name')) ? $this->option('name') : file_get_contents("php://stdin");
            $name = trim($name);

            $separator = $this->option('separator');

            if (empty($name) && empty($separator)) {
                throw new Exception("Input needed.", 1);
            }

            $input = explode($separator, $name);

            $job = new FakeJob();
            $data = [
                'name' => $input[0],
                'country' => $input[1]
            ];
            try {
                $response = $this->syncData($job, $data);

                if ($response['status'] === 'fail') {
                    throw new Exception($response['message'], 1);
                }
                // $this->info($data['country']);
                $this->info(sprintf('%sStore Name: "%s" in "%s" has been successfully synced to Elasticsearch server', $this->stdoutPrefix, $data['name'], $data['country']));
            } catch (Exception $e) {
                $this->error(sprintf('%sFailed to sync Store Name "%s" in "%s", message: %s', $this->stdoutPrefix, $data['name'], $data['country'], $e->getMessage()));
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
            array('name', null, InputOption::VALUE_OPTIONAL, 'Store name with country to sync.', null),
            array('separator', null, InputOption::VALUE_OPTIONAL, 'Separator between the store name and the country', ','),
            array('dry-run', null, InputOption::VALUE_NONE, 'Run in dry-run mode, no data will be sent to Elasticsearch.', null),
        );
    }

    /**
     * Fake response
     *
     * @param boolean $dryRun
     */
    protected function syncData($job, $data)
    {
        if ($this->option('dry-run')) {
            $this->stdoutPrefix = '[DRY RUN] ';

            return [
                'status' => 'ok',
                'message' => 'Dry run mode'
            ];
        }

        $esQueue = new ESStoreDetailUpdateQueue();
        $response = $esQueue->fire($job, $data);

        return $response;
    }

}
