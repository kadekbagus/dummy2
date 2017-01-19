<?php
/**
 * @author Rio Astamal <me@rioastamal.net>
 * @desc Soft delete the user based on email address
 */
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class DeleteUser extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'user:delete';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete user from table users and apikeys.';

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
        $userId = $this->option('userid');
        $email = $this->option('email');

        $user = User::with('apikey')
                    ->excludeDeleted()->where('user_email', $email);

        if (! empty($userId)) {
            $user->where('user_id', $userId);
        }

        $listOfUsers = $user->get();

        if ($listOfUsers->count() === 0) {
            $this->error('Email or user Id not found.');
            exit(1);
        }

        $this->info(sprintf('Found %s user(s)', $listOfUsers->count()));
        foreach ($listOfUsers as $usr) {
            $this->info(sprintf('Performing action on user %s', $usr->user_email));
            $this->info('    Deleting API...');
            $usr->apikey->delete();
            $this->info('    Deleting User...');
            $usr->delete();
            $this->info('    Deleting Session...');
            DB::table('sessions')->where('session_data', 'like', '%' . $usr->user_email . '%')->delete();
        }
        $this->info('done.');
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
            array('userid', null, InputOption::VALUE_REQUIRED, 'User ID of the user.', null),
            array('email', null, InputOption::VALUE_OPTIONAL, 'Email address of the user.', null),
        );
    }

}
