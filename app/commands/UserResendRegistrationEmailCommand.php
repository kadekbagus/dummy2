<?php
/**
 * @author Rio Astamal <rio@dominopos.com>
 * @desc Resend email activation to particular user
 */

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Orbit\FakeJob;
use Orbit\Queue\RegistrationMail as QueueEmailRegistration;

class UserResendRegistrationEmailCommand extends Command
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'user:resend-registration-mail';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Resend registration email to user based on user email address.';

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
        $emailOrUserId = trim($this->argument('email'));
        $dryRun = $this->option('dry-run');
        $mode = $this->option('find-mode');
        $messagePrefix = '';

        if (empty($emailOrUserId)) {
            // Get from STDIN
            $emailOrUserId = trim(file_get_contents('php://stdin'));
        }
        $cc = trim($this->option('cc'));

        if ($dryRun) {
            $messagePrefix = '[DRY RUN] ';
        }

        switch ($mode) {
            case 'user_id':
                $user = User::excludeDeleted()->Consumers()->where('user_id', $emailOrUserId)->first();
                break;

            default:
            case 'email':
                $user = User::excludeDeleted()->Consumers()->where('user_email', $emailOrUserId)->first();
                break;
        }

        if (! is_object($user)) {
            $this->error(sprintf('%sCustomer with email/user_id "%s" is not found.', $messagePrefix, $emailOrUserId));
            return FALSE;
        }

        $queueData = [
            'mode' => 'gotomalls',
            'user_id' => $user->user_id
        ];

        if (! empty($cc)) {
            $queueData['cc_email'] = $cc;
        }

        $queueEmail = new QueueEmailRegistration();
        $queueEmail->fire($job, $queueData);

        $this->info(sprintf('%sRegistration email has been to %s - User ID: %s.', $messagePrefix, $user->user_email, $user->user_id));
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return array(
            ['email', InputArgument::OPTIONAL, 'Email of the user.', NULL]
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
            ['cc', NULL, InputOption::VALUE_OPTIONAL, 'Copy carbon (cc) email address. Useful for debug.', NULL],
            ['find-mode', NULL, InputOption::VALUE_OPTIONAL, 'Find mode for searching user, "email" or "user_id".', 'email'],
            ['dry-run', NULL, InputOption::VALUE_NONE, 'Do not send email.', NULL]
        );
    }

}
