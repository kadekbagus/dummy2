<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Orbit\Models\Gamification\UserGameEvent;
use Orbit\Models\Gamification\UserVariable;

class ResetGamePoint extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'gamification:reset-points';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset all users gamification purchase points.';

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
        $take = 50;
        $skip = 0;

        // Setup end date
        $endDate = $this->option('end-date');
        $userId = $this->option('user-id');
        $dryRun = $this->option('dry-run', FALSE);

        if (empty($endDate)) {
            throw new Exception("End date is required", 1);
        }

        if ($dryRun) {
            $this->info('[***DRY RUN MODE***] ');
        }

        // Delete records before end date
        do {
            $userGameEvents = UserGameEvent::whereIn('object_type', ['pulsa', 'coupon', 'digital_product'])
                ->where('created_at', '<', $endDate)
                ->skip($skip)
                ->take($take);

            if (! empty($userId)) {
                $userGameEvents = $userGameEvents->where('user_id', $userId);
            }
            $userGameEvents = $userGameEvents->get();

            $skip = $take + $skip;

            foreach ($userGameEvents as $userGameEvent) {
                $this->info(sprintf('Adjusting User ID: "%s" total game points...', $userGameEvent->user_id));

                // Readjust total points
                $userVariable = UserVariable::where('user_id', $userGameEvent->user_id)
                    ->where('user_id', $userGameEvent->user_id)
                    ->where('variable_id', $userGameEvent->variable_id)
                    ->first();

                if (is_object($userVariable)) {
                    if (! empty($userVariable->value)) {
                        $userVariable->value = $userVariable->value - 1;
                    }
                    if (! empty($userVariable->total_points)) {
                        $userVariable->total_points = $userVariable->total_points - $userGameEvent->point;
                    }

                    if (! $dryRun) {
                        $userVariable->save();
                    }
                }

                $extendedUser = UserExtended::where('user_id', $userGameEvent->user_id)->firstOrFail();
                if (is_object($extendedUser)) {
                    if (! empty($extendedUser->total_game_points)) {
                        $extendedUser->total_game_points = $extendedUser->total_game_points - $userGameEvent->point;
                        if (! $dryRun) {
                            $extendedUser->save();
                        }
                    }
                }

                // delete usergame event
                if (! $dryRun) {
                    $deletedUserGameEvent = UserGameEvent::where('user_game_event_id', $userGameEvent->user_game_event_id)
                        ->firstOrFail();
                    $deletedUserGameEvent->delete();
                }

                $this->info(sprintf('User ID: "%s" total game points is adjusted by (-%s) points', $userGameEvent->user_id, $userGameEvent->point));
            }
        } while (count($userGameEvents) > 0);
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
            array('end-date', null, InputOption::VALUE_REQUIRED, 'Date limit for point reset', null),
            array('user-id', null, InputOption::VALUE_OPTIONAL, 'Date more than.', null),
            array('dry-run', null, InputOption::VALUE_NONE, 'Run in dry run mode, do not delete the data.'),
        );
    }

}
