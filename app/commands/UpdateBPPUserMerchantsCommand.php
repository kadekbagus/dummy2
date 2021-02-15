<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class UpdateBPPUserMerchantsCommand extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'user-bpp:update-merchants';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to create bppuser-merchant relation records.';

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
            $selectedUsers = explode(',', trim($this->option('user-ids')));

            DB::beginTransaction();

            $bppUser = BppUser::with([
                    'stores' => function($query) {
                        $query->select('merchants.merchant_id');
                    }
                ])
                ->where('user_type', 'store');

            if (! empty($selectedUsers)) {
                $bppUser->whereIn('bpp_user_id', $selectedUsers)
                    ->orWhereIn('email', $selectedUsers);
            }

            $bppUser->chunk(10, function($users) {
                foreach($users as $user) {
                    if ($user->stores->isEmpty()) {
                        $user->stores()->sync([$user->merchant_id]);
                        $this->info( sprintf('BppUser-Merchant relation created for user %s (%s)', $user->name, $user->email) );
                    }
                    else {
                        $this->error( sprintf('BppUser-Merchant relation already created for user %s (%s)', $user->name, $user->email) );
                    }
                }
            });

            DB::commit();

        } catch (Exception $e) {
            DB::rollback();
            $this->error('Line #' . $e->getLine() . ': ' . $e->getMessage());
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
        return [
            ['user-ids', null, InputOption::VALUE_OPTIONAL, 'List of user id/emails, separated by comma.']
        ];
    }

}
