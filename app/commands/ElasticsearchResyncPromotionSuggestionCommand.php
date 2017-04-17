<?php
/**
 * Command for resync promotion data from MySQL to Elasticsearch based on promotion id
 * @author Firmansyah <firmansyah@dominopos.com>
 */

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Orbit\FakeJob;
use Orbit\Queue\Elasticsearch\ESPromotionSuggestionUpdateQueue;

class ElasticsearchResyncPromotionSuggestionCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'elasticsearch:resync-promotion-suggestion';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Resync promotion data from MySQL to Elasticsearch based on news id';

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
            $input = ! empty($this->option('id')) ? $this->option('id') : file_get_contents("php://stdin");
            $input = trim($input);

            if (empty($input)) {
                throw new Exception("Input needed.", 1);
            }

            $job = new FakeJob();
            $data = [
                'news_id' => $input
            ];
            try {
                $response = $this->syncData($job, $data);

                if ($response['status'] === 'fail') {
                    throw new Exception($response['message'], 1);
                }

                $this->info(sprintf('%sNews ID: "%s" has been successfully synced to Elasticsearch server (Promotion Suggestion Index)', $this->stdoutPrefix, $data['news_id']));
            } catch (Exception $e) {
                $this->error(sprintf('%sFailed to sync promotion ID "%s", message: %s', $this->stdoutPrefix, $data['news_id'], $e->getMessage()));
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
            array('id', null, InputOption::VALUE_OPTIONAL, 'Promotion id to sync.', null),
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

        $esQueue = new ESPromotionSuggestionUpdateQueue();
        $response = $esQueue->fire($job, $data);

        return $response;
    }
}
