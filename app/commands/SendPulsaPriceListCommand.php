<?php

use Carbon\Carbon;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Orbit\Notifications\Pulsa\Subscription\PulsaPriceListNotification;

/**
 * @author Budi <budi@dominopos.com>
 */
class SendPulsaPriceListCommand extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'pulsa:send-customer-email-pricelist';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to send email of pulsa pricelist to customers/GTM User.';

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
        $this->info("Getting list of customers...");
        $userList = UserExtended::select('users.user_id', 'users.user_email', 'users.user_firstname', 'users.user_lastname', 'pulsa_email_subscription')
            ->join('users', 'extended_users.user_id', '=', 'users.user_id')
            ->where('pulsa_email_subscription', 'yes');

        if ($this->option('users')) {
            $userList->whereIn('extended_users.user_id', explode(',', $this->option('users')));
        }

        $sent = 0;
        $userList->chunk($this->option('chunk'), function($users) use ($sent) {
            $sent += $users->count();
            $this->info("Sending email to {$users->count()} customers...");

            foreach($users as $user) {
                (new PulsaPriceListNotification($user))->send();
            }
        });

        if ($sent > 0) {
            $this->info("Emails ({$sent}) are on the way!");
        }
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [];
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return array(
            array('users', null, InputOption::VALUE_OPTIONAL, "Send to specific user_id(s) (separated by comma).", ''),
            array('chunk', null, InputOption::VALUE_OPTIONAL, 'Number of customer to fetch per query.', 20),
        );
    }
}
