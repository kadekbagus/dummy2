<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use \ViewItemUser;
use \Exception;

class DeleteGuestUser extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'guest:cleanup-user';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete guest user.';

    /**
     * Guest role name.
     *
     * @var string
     */
    protected $guestRoleName = 'guest';

    /**
     * Default day threshold.
     *
     * @var int
     */
    protected $defaultDays = 7;

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
            $days = $this->option('days');
            $this->checkAndSetDaysOption($days);

            $guestRoleIds = Role::roleIdsByName([$this->guestRoleName]);

            if (empty($guestRoleIds)) {
                throw new Exception("Guest role id is not found", 1);
            }

            $guestRoleId = $guestRoleIds[0];

            echo "Counting records...\n\n";
            $dateString = '-' . $this->defaultDays . ' days';
            $dateThreshold = date('Y-m-d H:i:s', strtotime($dateString));

            $deletedItems = DB::table('users')
                ->leftJoin('roles', 'users.user_role_id', '=', 'roles.role_id')
                ->where('roles.role_id', $guestRoleId)
                ->where('users.created_at', '<', $dateThreshold)
                ->lists('user_id');

            if (empty($deletedItems)) {
                throw new Exception("No records found.", 1);
            }

            $proceed = $this->ask('Deleting [' . count($deletedItems) . "] records beyond [". $dateThreshold ."]. Proceed? (y/n)\n");

            if ($proceed === 'y') {
                echo "Deleting users records...\n";
                DB::table('users')
                    ->whereIn('user_id', $deletedItems)
                    ->delete();

                echo "Deleting user_details records...\n";
                DB::table('user_details')
                    ->whereIn('user_id', $deletedItems)
                    ->delete();

                echo count($deletedItems) . " guest user deleted. Done.\n";
            } else {
                echo "Aborted.\n";
            }
        } catch (Exception $e) {
            echo $e->getMessage();
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
            array('days', null, InputOption::VALUE_OPTIONAL, 'Number of day threshold to keep the records, beyond that will be deleted. (Default: ' . $this->defaultDays . ' days)'),
        );
    }

    /**
     * Check days option.
     *
     * @return void|Exception
     */
    private function checkAndSetDaysOption($days = null)
    {
        if (! is_null($days)) {
            if (!preg_match('/^[0-9]+$/', $days)) {
                throw new InvalidArgumentException('Days must be a positive integer');
            }
            $days = (int)$days;
            if ($days <= 0) {
                throw new InvalidArgumentException('Days must be a positive integer');
            }

            $this->defaultDays = $days;
        }
    }
}
