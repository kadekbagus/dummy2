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

            do {
                $users = User::select('users.user_id','users.username')
                               ->leftJoin('roles', 'roles.role_id', '=', 'users.user_role_id')
                               ->leftJoin('user_game_events', 'user_game_events.user_id', '=', 'users.user_id')
                               ->leftJoin('variables', 'variables.variable_id', '=', 'user_game_events.variable_id')
                               ->where('users.status', '=', 'active')
                               ->where('roles.role_name', '=', 'Consumer')
                               ->where('variables.variable_slug', '!=', 'sign_up')
                               ->skip($skip)
                               ->take($take)
                               ->get();

                $skip = $take + $skip;

                foreach ($users as $key => $val) {
                    $user = User::with('role')->where('user_id', '=', $val->user_id)->first();
                    Event::fire('orbit.user.activation.success', $user);
                    $this->info(sprintf('username "%s" user_id "%s" has been successfully get signup point', $val->username, $val->user_id));
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
            array('dry-run', null, InputOption::VALUE_NONE, 'Run in dry-run mode, no data will be sent', null),
        );
    }

}
