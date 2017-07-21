<?php
/**
 * Command for showing missing cdn image file for coupon
 *
 * @author kadek <kadek@dominopos.com>
 */

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class CdnCouponMissingFile extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'cdn:coupon-missing-file';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find coupon id not have cdn file.';

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
        $now = date('Y-m-d H:i:s', strtotime($this->option('more-than')));    // no TZ calculation
        $prefix = DB::getTablePrefix();

        do {
            $coupons = DB::select("SELECT c.promotion_id
                FROM {$prefix}media m
                JOIN {$prefix}coupon_translations ct ON ct.coupon_translation_id = m.object_id
                JOIN {$prefix}promotions c ON c.promotion_id = ct.promotion_id
                WHERE m.object_name = 'coupon_translation' AND
                c.is_coupon = 'Y' AND
                (m.cdn_url IS NULL or m.cdn_url = '') AND
                m.path IS NOT NULL AND
                m.created_at > '{$now}'
                GROUP BY c.promotion_id
                LIMIT $skip, $take");

            $skip = $take + $skip;

            foreach ($coupons as $coupon) {
                $values = get_object_vars($coupon);
                printf("%s,%s\n", implode($values), 'coupon');
            }

        } while (! empty($coupons));
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
            array('more-than', null, InputOption::VALUE_OPTIONAL, 'Date more than.', null),
        );
    }

}
