<?php
/**
 * Command for manually sending Promotional Event Code manually
 * @author Ahmad Anshori <ahmad@dominopos.com>
 */

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Orbit\FakeJob;
use Orbit\Queue\PromotionalEventMail;

class SendPromotionalEventMailCommand extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'promotional-event:resend-code-email';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Resend Promotional Event Code Manually';

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
            // collect parameters
            $campaignId = $this->option('campaign-id');
            // @todo: make dynamic language depends on user preference
            $language = 'id';

            $input = ! empty($this->option('user-id')) ? $this->option('user-id') : file_get_contents("php://stdin");
            $input = trim($input);

            if (empty($campaignId)) {
                throw new Exception("campaign-id is required", 1);
            }

            if (empty($input)) {
                throw new Exception("user-id needed.", 1);
            }

            $job = new FakeJob();
            $data = [
                'campaignId'         => $campaignId,
                'userId'             => $input,
                'languageId'         => $language
            ];

            $response = $this->syncData($job, $data);

            if ($response['status'] === 'fail') {
                throw new Exception(sprintf('Failed to send email to UserID: %s; Reason: %s;', $input, $response['message']), 1);
            }

            if ($response['message'] === 'Dry run mode') {
                $this->info(sprintf('Successfully send email to UserID: %s; Data: %s', $input, serialize($response['data'])));
            } else {
                $this->info(sprintf('Successfully send email to UserID: %s;', $input));
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
            array('user-id', null, InputOption::VALUE_REQUIRED, 'User ID to receive the mail.', null),
            array('campaign-id', null, InputOption::VALUE_REQUIRED, 'Campaign ID of the Promotional Event.', null),
            array('dry-run', null, InputOption::VALUE_NONE, 'Run in dry-run mode, to display data.', null),
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
                'message' => 'Dry run mode',
                'data' => $data
            ];
        }

        $queue = new PromotionalEventMail();
        $response = $queue->fire($job, $data);

        return $response;
    }

}
