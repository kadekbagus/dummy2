<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class CouponCheckReserved extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'coupon:check-reserved';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description.';

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

            $data = [
                'coupon_id' => $input
            ];

            $couponId =  $data['coupon_id'];

            // Check detail coupon
            $coupon = Coupon::where('promotion_id', $couponId)->first();

            if (empty($coupon)) {
                throw new Exception("Coupon not found", 1);
            }

            $nowDate = date('Y-m-d H:i:s');
            $limitTime = Config::get('orbit.coupon_reserved_limit_time');
            $couponReserved = IssuedCoupon::where('promotion_id', $couponId)
                                            ->where('transaction_id', NULL)
                                            ->where('status', IssuedCoupon::STATUS_RESERVED)
                                            ->where(DB::raw("addtime(issued_date, '0 0:$limitTime:00.00')"), "<=",  $nowDate)
                                            ->get();

            $totalCouponCanceled = count($couponReserved);

            // DRY RUN
            if ($this->option('dry-run')) {
                $this->info("FOUND $totalCouponCanceled coupon canceled, in coupon id = $coupon->promotion_id, name = $coupon->promotion_name");
                die();
            }

            if ($totalCouponCanceled > 0) {

                \DB::beginTransaction();

                // Canceled coupon based on promotion_type
                foreach ($couponReserved as $key => $val) {
                    if ($coupon->promotion_type === 'sepulsa') {

                        $couponCanceled = IssuedCoupon::where('issued_coupon_id', $val->issued_coupon_id)->delete(true);

                    } elseif ($coupon->promotion_type === 'hot_deals') {

                        $couponCanceled = IssuedCoupon::where('issued_coupon_id', $val->issued_coupon_id)->first();
                        $couponCanceled->user_id = NULL;
                        $couponCanceled->user_email = NULL;
                        $couponCanceled->issued_date = NULL;
                        $couponCanceled->status = 'available';
                        $couponCanceled->save();

                    }
                }

                // Update available coupon
                $couponAvailable = $coupon->available + $totalCouponCanceled;
                $coupon->available = $couponAvailable;
                $coupon->setUpdatedAt($coupon->freshTimestamp());
                $coupon->save();

                // Commit the changes
                DB::commit();

                // Re sync the coupon data to make sure deleted when coupon sold out
                if ($couponAvailable > 0) {
                    // Re sync the coupon data
                    Queue::push('Orbit\\Queue\\Elasticsearch\\ESCouponUpdateQueue', [
                        'coupon_id' => $couponId
                    ]);
                } elseif ($couponAvailable == 0) {
                    // Delete the coupon and also suggestion
                    Queue::push('Orbit\\Queue\\Elasticsearch\\ESCouponDeleteQueue', [
                        'coupon_id' => $couponId
                    ]);

                    Queue::push('Orbit\\Queue\\Elasticsearch\\ESCouponSuggestionDeleteQueue', [
                        'coupon_id' => $couponId
                    ]);

                }

                $this->info('Artisan CheckReservedCoupon Runnning : Coupon unpay canceled, coupon_id = ' . $couponId . ', total canceled coupon = '. $totalCouponCanceled);

            } else {
                $this->info('Artisan CheckReservedCoupon Runnning : No coupon canceled, coupon_id = ' . $couponId);
            }

        } catch (Exception $e) {
            $this->error('Line #' . $e->getLine() . ': ' . $e->getMessage());

            // Rollback the changes
            DB::rollBack();
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
            array('id', null, InputOption::VALUE_OPTIONAL, 'Coupon id.', null),
            array('dry-run', null, InputOption::VALUE_NONE, 'Run in dry-run mode, will be check total cancel reserved coupon.', null),
        );
    }

}
