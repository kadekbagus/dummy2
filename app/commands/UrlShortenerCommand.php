<?php
/**
 * Command to shorten coupon urls
 *
 * @author Ahmad <ahmad@dominopos.com>
 * @author Rio Astamal <rio@dominopos.com>
 */
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Orbit\Helper\Net\BitlyShortener;
use Orbit\Helper\Security\Encrypter;

class UrlShortenerCommand extends Command
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'shorten:coupon-url';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Print comma seperated shortened url of coupon canvas url';

    /**
     * Accepted mode
     *
     * @var array
     */
    protected $accepted_mode = ['bitly'];

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
        $mode = $this->option('mode');
        $couponId = $this->option('coupon-id');
        $verbose = $this->option('verbose');
        $forceUpdate = $this->option('force-update');

        $coupon = Coupon::excludeDeleted()->where('promotion_id', $couponId)->first();

        // check for coupon existance
        if (! is_object($coupon)) {
            $this->error('Coupon not found.');
            return;
        }

        // check for accepted mode
        if (! in_array($mode, $this->accepted_mode)) {
            $this->error('Not supported shortening mode.');
            return;
        }

        // get redeem url format from config
        $redeemUrl = Config::get('orbit.coupon.sms_direct_redemption_url');

        // get list of coupon codes for the provided coupon id
        $arrayOfCouponCodes = IssuedCoupon::select('issued_coupon_code')
                                        ->where('promotion_id', $couponId)
                                        ->get()
                                        ->lists('issued_coupon_code');

        // check if coupon has any issued coupon
        if (empty($arrayOfCouponCodes)) {
            $this->error('This coupon does not have coupon codes.');
            return;
        }

        $arrayOfCouponRedeemUrl = array();

        $encryptionKey = Config::get('orbit.security.encryption_key');
        $encryptionDriver = Config::get('orbit.security.encryption_driver');
        $encrypter = new Encrypter($encryptionKey, $encryptionDriver);

        foreach($arrayOfCouponCodes as $couponCode) {
            $hashedCid = rawurlencode($encrypter->encrypt($couponCode));
            $hashedPid = rawurlencode($encrypter->encrypt($couponId));

            $couponRedeemUrlObj = new \stdClass();
            $couponRedeemUrlObj->redeemUrl = sprintf($redeemUrl, $hashedCid, $hashedPid);
            $couponRedeemUrlObj->couponCode = $couponCode;

            $arrayOfCouponRedeemUrl[] = $couponRedeemUrlObj;
        }

        // end result array
        $shortUrls = array();

        // repeat until all request successful, make sure the internet connection is good
        while(! empty($arrayOfCouponRedeemUrl)) {
            foreach($arrayOfCouponRedeemUrl as $key => $redeemUrl) {

                // fetch url from table if any
                $shortUrlFromTable = IssuedCoupon::excludeDeleted()
                    ->where('issued_coupon_code', $redeemUrl->couponCode)
                    ->where('promotion_id', $couponId)
                    ->first()->url;

                if (! empty($shortUrlFromTable) && ! $forceUpdate) {
                    if ($verbose) {
                        $this->info(sprintf('Taking url of coupon code: %s from table', $arrayOfCouponCodes[$key]));
                    }
                    if ($verbose) {
                        $this->info(sprintf('Coupon code: %s, Short url: %s', $arrayOfCouponCodes[$key], $shortUrlFromTable));
                    }
                    $shortUrls[] = $shortUrlFromTable;
                    // remove successful one
                    unset($arrayOfCouponRedeemUrl[$key]);
                } else {
                    // call shortener service if url not found in table or when forceupdate flag is true
                    if ($mode === 'bitly') {
                        if ($verbose) {
                            $this->info(sprintf('Shortening coupon code: %s', $arrayOfCouponCodes[$key]));
                        }

                        // set bitly parameters
                        $bitlyConfig = array(
                            'access_token' => Config::get('orbit.social_login.bitly.generic_access_token'),
                            'domain' => 'bit.ly',
                            'longUrl' => $redeemUrl->redeemUrl
                        );
                        // call bitly
                        $shortUrl = BitlyShortener::create($bitlyConfig)->bitlyGet('shorten');

                        // add .5 second delay for good measure
                        usleep(500000);
                        // check for response
                        if ($shortUrl['status_code'] === 200) {
                            if ($verbose) {
                                $this->info(sprintf('    Done. Response status: %s, Short url: %s', $shortUrl['status_code'], $shortUrl['data']['url']));
                            }

                            $shortUrls[] = $shortUrl['data']['url'];
                            // save url to issued coupon
                            $issuedCoupon = DB::table('issued_coupons')
                                ->where('issued_coupon_code', $redeemUrl->couponCode)
                                ->where('promotion_id', $couponId)
                                ->update(['url' => $shortUrl['data']['url']]);

                            // remove successful one
                            unset($arrayOfCouponRedeemUrl[$key]);
                        }
                    }
                }
            }
        }

        if (! $verbose) {
            // display results
            $this->info(implode("\n", $shortUrls));
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
        return array(
                array('coupon-id', 0, InputOption::VALUE_REQUIRED, 'Coupon ID (required)'),
                array('mode', 0, InputOption::VALUE_REQUIRED, 'Shortener service e.g: bitly (required). Default set to bitly.', 'bitly'),
                array('force-update', 0, InputOption::VALUE_NONE, 'Force update saved url in table with new one'),
            );
    }
}
