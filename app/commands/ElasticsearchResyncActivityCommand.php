<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Orbit\FakeJob;
use Orbit\Queue\Elasticsearch\ESActivityUpdateQueue;

class ElasticsearchResyncActivityCommand extends Command
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'elasticsearch:resync-activity';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Resync activity data from MySQL to Elasticsearch based on activity id';

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
        $job = new FakeJob();
        $data = [
            'activity_id' => $this->option('activity-id'),
            'referer' => 'from-resync-commandline',
            'orbit_referer' => 'from-rsync-commandline'
        ];
        try {
            $esQueue = new ESActivityUpdateQueue();
            $response = $esQueue->fire($job, $data);

            if ($response['status'] === 'fail') {
                throw new Exception($response['message'], 1);
            }

            $this->info(sprintf('Activity id "%s" has been successfully synced to Elasticsearch server', $data['activity_id']));
        } catch (Exception $e) {
            $this->error(sprintf('Failed to sync activity id "%s", message: %s', $data['activity_id'], $e->getMessage()));
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
            array('activity-id', null, InputOption::VALUE_REQUIRED, 'The activity id from MySQL source.', null),
        );
    }

}
