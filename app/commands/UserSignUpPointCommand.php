<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Orbit\Events;

class UserSignUpPointCommand extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'user-signup:point';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Give signup point to all active user.';

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
            $take = 50;
            $skip = 0;

            $roleId = $this->option('consumer-role-id');

            if (empty($roleId)) {
                throw new Exception("Consumer Role ID is required", 1);
            }

            do {
                $users = User::leftJoin('user_game_events', 'user_game_events.user_id', '=', 'users.user_id')
                               ->where('users.status', '=', 'active')
                               ->where('users.user_role_id', '=', $roleId)
                               ->whereNull('user_game_events.user_id')
                               ->skip($skip)
                               ->take($take)
                               ->get();

                $skip = $take + $skip;

                foreach ($users as $user) {
                    Event::fire('orbit.user.activation.success', $user);
                    $this->info(sprintf('username "%s" user_id "%s" has been successfully get signup point', $user->username, $user->user_id));
                }
            } while (count($users) > 0);

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
            array('consumer-role-id', null, InputOption::VALUE_REQUIRED, '"Consumer" role_id', null),
        );
    }

}
