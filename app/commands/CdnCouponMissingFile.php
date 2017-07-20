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
        $date = $this->option('more-than');

        if (DateTime::createFromFormat('Y-m-d H:i:s', $date) == false) {
           throw new Exception('Format date is invalid, format date must be Y-m-d H:i:s ie (2017-12-20 16:55:28)');
        }

        $coupons = Media::select('promotions.promotion_id')
                    ->join('coupon_translations', 'coupon_translations.coupon_translation_id', '=', 'media.object_id')
                    ->join('promotions', 'promotions.promotion_id', '=', 'coupon_translations.promotion_id')
                    ->where('media.object_name', '=', 'coupon_translation')
                    ->where('promotions.is_coupon', '=', 'Y')
                    ->whereNull('media.cdn_url')
                    ->whereNotNull('media.path')
                    ->where('media.created_at', '>=', $date)
                    ->groupBy('promotions.promotion_id')
                    ->get();

        if (count($coupons)) {
            foreach($coupons as $coupon) {
                printf("%s,%s\n", $coupon->promotion_id, 'coupon');
            }
        } else {
            $this->info('no missing cdn found');
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
            array('more-than', null, InputOption::VALUE_OPTIONAL, 'Date more than.', null),
        );
    }

}
