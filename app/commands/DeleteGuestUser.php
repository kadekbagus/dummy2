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
            $confirm = $this->option('yes');
            $limit = $this->option('limit');

            $this->checkAndSetDaysOption($days);

            $guestRoleIds = Role::roleIdsByName([$this->guestRoleName]);

            if (empty($guestRoleIds)) {
                throw new Exception("Guest role id is not found", 1);
            }

            $guestRoleId = $guestRoleIds[0];

            echo "Counting records...\n\n";
            $dateString = '-' . $this->defaultDays . ' days';
            $dateThreshold = date('Y-m-d 00:00:00', strtotime($dateString));

            $data = DB::table('users')
                ->leftJoin('roles', 'users.user_role_id', '=', 'roles.role_id')
                ->where('roles.role_id', $guestRoleId)
                ->where('users.created_at', '<', $dateThreshold)
                ->orderBy('users.created_at', 'desc');

            $deletedItems = clone $data;
            $deletedItems = $deletedItems->take($limit)->lists('user_id');

            if ($data->count() < $limit) {
                if ($data->count() === 0) {
                    throw new Exception("No records found.", 1);
                }
                $limit = $data->count();
            }

            if (! $confirm) {
                $question = "Are you sure want to delete " . $limit . " guest data, which ages over " . $days ." days with total " . $data->count() . " record(s)? [y|n]";
                if (! $this->confirm($question, false)) {
                    $confirm = false;
                    return;
                }
            }

            echo "Deleting users records...\n";
            DB::table('users')
                ->whereIn('user_id', $deletedItems)
                ->delete();

            echo "Deleting user_details records...\n";
            DB::table('user_details')
                ->whereIn('user_id', $deletedItems)
                ->delete();

            echo count($deletedItems) . " guest user deleted. Done.\n";
           
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
            array('limit', null, InputOption::VALUE_OPTIONAL, 'Limitation for delete records, default is 5000', 5000),
            array('yes', null, InputOption::VALUE_NONE, 'Confirmation to delete guest'),
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
