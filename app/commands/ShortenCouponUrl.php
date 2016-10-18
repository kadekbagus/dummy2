<?php
/**
 * Command to shorten coupon urls
 * 
 * @author Ahmad <ahmad@dominopos.com>
 */
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Orbit\Helper\Net\BitlyShortener;
use Orbit\Helper\Security\Encrypter;
use \Config;
use \Coupon;
use \IssuedCoupon;

class ShortenCouponUrl extends Command
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
        $couponId = $this->option('couponid');
        $verbose = $this->option('v');

        $coupon = Coupon::where('promotion_id', $couponId)->first();

        if (! is_object($coupon)) {
            $this->error('Coupon not found.');
            die();
        }

        if (! in_array($mode, $this->accepted_mode)) {
            $this->error('Not supported shortening mode.');
            die();
        }

        $redeemUrl = Config::get('orbit.coupon.sms_direct_redemption_url');
        $arrayOfCouponCodes = IssuedCoupon::where('promotion_id', $couponId)
            ->get()->lists('issued_coupon_code');

        if (empty($arrayOfCouponCodes)) {
            $this->error('This coupon does not have coupon codes.');
            die();
        }
        
        $arrayOfCouponRedeemUrl = array();
        
        $encryptionKey = Config::get('orbit.security.encryption_key');
        $encryptionDriver = Config::get('orbit.security.encryption_driver');
        $encrypter = new Encrypter($encryptionKey, $encryptionDriver);

        foreach($arrayOfCouponCodes as $couponCode) {
            $hashedCid = rawurlencode($encrypter->encrypt($couponCode));
            $hashedPid = rawurlencode($encrypter->encrypt($couponId));
            $arrayOfCouponRedeemUrl[] = sprintf($redeemUrl, $hashedCid, $hashedPid);
        }

        $shortUrls = array();

        if ($mode === 'bitly') {
            foreach($arrayOfCouponRedeemUrl as $key => $redeemUrl) {
                if ($verbose === 'Y') {
                    $this->info(sprintf('Shortening coupon code: %s', $arrayOfCouponCodes[$key]));
                }
                $bitlyConfig = array(
                    'access_token' => Config::get('orbit.social_login.bitly.generic_access_token'),
                    'domain' => 'bit.ly',
                    'longUrl' => $redeemUrl
                );
                $shortUrl = BitlyShortener::create($bitlyConfig)->bitly_get('shorten');
                if ($verbose === 'Y') {
                    $this->info(sprintf('Shortening coupon code: %s, Response status: %s', $arrayOfCouponCodes[$key], $shortUrl['status_code']));
                }
                if ($shortUrl['status_code'] === 200) {
                    $shortUrls[] = $shortUrl['data']['url'];
                    if ($verbose === 'Y') {
                        $this->info(sprintf('Shortening coupon code: %s, Short url: %s', $arrayOfCouponCodes[$key], $shortUrl['data']['url']));
                    }
                }
            }
        }
        $this->info(implode("\n", $shortUrls));
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
                array('couponid', 0, InputOption::VALUE_REQUIRED, 'Coupon ID', 0),
                array('mode', 0, InputOption::VALUE_REQUIRED, 'Shortener service, eg: bitly', 0),
                array('v', 0, InputOption::VALUE_OPTIONAL, 'Display process (Y/N)', 'N'),
            );
    }
}
