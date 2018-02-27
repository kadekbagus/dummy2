<?php
/**
 * Command for manually re-insert unobtained promotional code by registering via social media
 * should be temporary fix until issue on register_with_reward via social media resolved
 * @author Ahmad Anshori <ahmad@dominopos.com>
 */

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Orbit\Helper\PromotionalEvent\PromotionalEventProcessor;

class ReinsertUnobtainedPromotionalEventCodeCommand extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'promotional-event:re-obtain-code';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Re-insert Promotional Event Code manually (should be temporary fix)';

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
            // @todo: make dynamic object_type depends on reward_type
            $rewardType = 'news';

            $input = ! empty($this->option('user-id')) ? $this->option('user-id') : file_get_contents("php://stdin");
            $input = trim($input);

            if (empty($campaignId)) {
                throw new Exception("campaign-id is required", 1);
            }

            if (empty($input)) {
                throw new Exception("user-id needed.", 1);
            }

            if ($this->option('dry-run')) {
                $this->info(sprintf('[DRY RUN] Successfully generate code for UserID: %s; ', $input));
                return;
            }

            DB::beginTransaction();
            PromotionalEventProcessor::create($input, $campaignId, $rewardType, $language)->insertRewardCode();
            DB::commit();

            $this->info(sprintf('Successfully generate code for UserID: %s; ', $input));
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

}
