<?php
/**
 * Command for resync coupon data from MySQL to Elasticsearch based on coupon id
 * @author Ahmad Anshori <ahmad@dominopos.com>
 */

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Orbit\FakeJob;
use Orbit\Queue\ElasticSearch\ESCouponUpdateQueue;

class ElasticsearchResyncCouponCommand extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'elasticsearch:resync-coupon';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Resync coupon data from MySQL to Elasticsearch based on coupon id';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct($poster = 'default')
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
            $input = ! empty($this->argument('id')) ? $this->argument('id') : file_get_contents("php://stdin");

            if (empty($input)) {
                throw new Exception("Input needed.", 1);
            }

            $job = new FakeJob();
            $data = [
                'coupon_id' => $input
            ];
            try {
                $esQueue = new ESCouponUpdateQueue();
                $response = $esQueue->fire($job, $data);

                if ($response['status'] === 'fail') {
                    throw new Exception($response['message'], 1);
                }

                $this->info(sprintf('Coupon ID: "%s" has been successfully synced to Elasticsearch server', $data['coupon_id']));
            } catch (Exception $e) {
                $this->error(sprintf('Failed to sync Coupon ID "%s", message: %s', $data['coupon_id'], $e->getMessage()));
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
            array('id', null, InputOption::VALUE_OPTIONAL, 'Coupon ID.', null)
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
