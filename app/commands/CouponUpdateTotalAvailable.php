<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class CouponUpdateTotalAvailable extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'coupon:update-total-available';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Artisan command to update "available" column in orb_promotions table, total available is based on issued_coupon table';

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
            $input = ! empty($this->option('id')) ? $this->option('id') : file_get_contents("php://stdin");
            $input = trim($input);

            if (empty($input)) {
                throw new Exception("Input needed.", 1);
            }

            $coupon = Coupon::find($input);
            if (! is_object($coupon)) {
                throw new Exception("Coupon ID is not found.", 1);
            }

            $availableCoupons = IssuedCoupon::totalAvailable($input);

            $coupon->available = $availableCoupons;
            if (! $this->option('dry-run')) {
                $coupon->save();
            }

            $message = sprintf('Update total available coupon; Status: OK; Coupon ID: %s; Coupon Name: %s',
                                $input,
                                $coupon->promotion_name);
            $this->info($message);

            return [
                'status' => 'ok',
                'message' => $message
            ];

        } catch (Exception $e) {
            $message = sprintf('Update total available coupon; Status: FAIL; Coupon ID: %s; Coupon Name: %s',
                                $input,
                                $coupon->promotion_name);
            $this->info($message);
        }

        return [
            'status' => 'fail',
            'message' => $message
        ];
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
            array('id', null, InputOption::VALUE_OPTIONAL, 'Coupon id to sync.', null),
            array('dry-run', null, InputOption::VALUE_NONE, 'Run in dry-run mode, no data will be update.', null),
        );
    }

}
