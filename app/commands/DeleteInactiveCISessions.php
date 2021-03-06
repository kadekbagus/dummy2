<?php
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

/**
 * Deletes inactive mobile-ci sessions and inserts logout activity for the corresponding users.
 *
 * Possible upstart start script (/etc/init/orbit-ci-session-delete-inactive.conf) BEGIN
 *
 * chdir /var/www/production/orbit-mall-api/
 * exec /var/www/production/orbit-mall-api/artisan ci-session:delete-inactive --inactive-for=300 --interval=15 --batch-size=10 --application-id=1
 *
 * start on (filesystem and runlevel [2345])
 * stop on runlevel [016]
 *
 * respawn
 * respawn limit 10 30
 *
 * setuid git
 * setgid git
 *
 * Possible upstart start script END
 */
class DeleteInactiveCISessions extends Command
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'ci-session:delete-inactive';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deletes inactive mobile-ci sessions and logs the users out.';

    /**
     * Create a new command instance.
     *
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     */
    public function fire()
    {
        $inactive_for = $this->checkInactiveForArgument();
        $interval = $this->checkIntervalOption();
        $batch_size = $this->checkBatchSizeOption();
        $application_id = $this->checkApplicationIdOption();
        $macs = null;
        while (true) {
            $ts = time();
            DB::connection()->beginTransaction();
            try {
                $this->runSingleIteration($application_id, $inactive_for, $batch_size);
                DB::connection()->commit();
            } catch (Exception $e) {
                DB::connection()->rollBack();
                throw $e;
            }
            $sleep_until = $ts + $interval;
            while ($sleep_until <= time()) {
                $sleep_until += $interval;
            }
            time_sleep_until($sleep_until);
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
            array('inactive-for', null, InputOption::VALUE_REQUIRED, 'Seconds since the user\'s last activity'),
            array('interval', null, InputOption::VALUE_REQUIRED,  'Run check every n seconds'),
            array('batch-size', null, InputOption::VALUE_REQUIRED,  'How many rows to delete'),
            array('application-id', null, InputOption::VALUE_REQUIRED,  'Application ID'),
        );
    }

    private function checkInactiveForArgument()
    {
        $inactive_for = $this->option('inactive-for');
        if (!preg_match('/^[0-9]+$/', $inactive_for)) {
            throw new InvalidArgumentException('Inactive for must be a positive integer');
        }
        $inactive_for = (int)$inactive_for;
        if ($inactive_for <= 0) {
            throw new InvalidArgumentException('Inactive for must be a positive integer');
        }
        return $inactive_for;
    }

    private function checkIntervalOption()
    {
        $interval = $this->option('interval');
        if (!preg_match('/^[0-9]+$/', $interval)) {
            throw new InvalidArgumentException('Interval must be a positive integer');
        }
        $interval = (int)$interval;
        if ($interval <= 0) {
            throw new InvalidArgumentException('Interval must be a positive integer');
        }
        return $interval;
    }

    private function checkBatchSizeOption()
    {
        $batch_size = $this->option('batch-size');
        if (!preg_match('/^[0-9]+$/', $batch_size)) {
            throw new InvalidArgumentException('Batch size must be a positive integer');
        }
        $batch_size = (int)$batch_size;
        if ($batch_size <= 0) {
            throw new InvalidArgumentException('Batch size must be a positive integer');
        }
        return $batch_size;
    }

    private function checkApplicationIdOption()
    {
        $application_id = $this->option('application-id');
        if (!preg_match('/^[0-9]+$/', $application_id)) {
            throw new InvalidArgumentException('Application ID must be a positive integer');
        }
        $application_id = (int)$application_id;
        if ($application_id < 0) {
            throw new InvalidArgumentException('Application ID must not be negative');
        }
        return $application_id;
    }

    private function runSingleIteration($application_id, $inactive_for, $batch_size)
    {
        $users_logged_out = 0;
        $prefix = DB::getTablePrefix();
        $results = DB::select(
            'select session_id, session_data from `' . $prefix . 'sessions` where last_activity < UNIX_TIMESTAMP() - ? and application_id = ? ORDER BY last_activity LIMIT ' . $batch_size . ' FOR UPDATE',
            array($inactive_for, $application_id));
        $to_delete = [];
        foreach ($results as $row) {
            $data = @unserialize($row->session_data);
            if ($data === false) {
                // unable to be unserialized
                $to_delete[] = $row->session_id;
                continue;
            }
            if (!isset($data->value)) {
                // no value
                $to_delete[] = $row->session_id;
                continue;
            }
            $data = $data->value;
            if (!is_array($data)) {
                // ...
                $to_delete[] = $row->session_id;
                continue;
            }
            if (!isset($data['logged_in'])) {
                $to_delete[] = $row->session_id;
                continue;
            }
            if (!$data['logged_in']) {
                $to_delete[] = $row->session_id;
                continue;
            }
            if (!isset($data['user_id'])) {
                $to_delete[] = $row->session_id;
                continue;
            }
            if (!isset($data['location_id'])) {
                $to_delete[] = $row->session_id;
                continue;
            }
            $user_id = $data['user_id'];
            $location_id = $data['location_id'];
            $this->logoutUser($user_id, $location_id, $row->session_id);
            $users_logged_out = $users_logged_out + 1;
            $to_delete[] = $row->session_id;
        }

        $this->deleteSessions($to_delete);
        $this->line(sprintf('CI session cleanup: %d users logged out, %d sessions deleted', $users_logged_out, count($to_delete)));
    }

    private function logoutUser($user_id, $location_id, $session_id)
    {
        $prefix = DB::getTablePrefix();
        $rows = DB::select('SELECT object_type FROM `' . $prefix . 'merchants` WHERE merchant_id = ?', [$location_id]);

        $location_type = null;
        foreach ($rows as $row) {
            $location_type = $row->object_type;
        }

        $location = null;
        if ($location_type === 'mall') {
            $location = Mall::find($location_id);
        } elseif ($location_type === 'retailer') {
            $location = Retailer::find($location_id);
        } elseif ($location_type === 'merchant') {
            $location = Merchant::find($location_id);
        } elseif ($location_type === 'tenant') {
            $location = Tenant::find($location_id);
        }

        $activity = Activity::mobileci()
            ->setActivityType('logout')
            ->setUser(User::find($user_id))
            ->setActivityName('logout_ok')
            ->setActivityNameLong('Sign Out')
            ->setModuleName('Application')
            ->setLocation($location)
            ->setSessionId($session_id)
            ->responseOK();
        $activity->location_id = $location_id;
        $activity->save();
    }

    private function deleteSessions($to_delete)
    {
        $prefix = DB::getTablePrefix();
        foreach ($to_delete as $session_id) {
            DB::delete('DELETE FROM `' . $prefix . 'sessions` WHERE session_id = ?', [$session_id]);
        }
    }

}
