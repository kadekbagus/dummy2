<?php

use Carbon\Carbon;
use Illuminate\Console\Command;
use Orbit\Helper\Sepulsa\API\VoucherList;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class SepulsaActiveVoucherReportCommand extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'sepulsa:active-vouchers-report';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Report for Sepulsa\' active vouchers against our active campaigns.';

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
        $config = Config::get('orbit.partners_api.sepulsa');
        $prefix = DB::getTablePrefix();
        $coupons = Coupon::select('promotion_name', 'token')
                        ->join('coupon_sepulsa', 'promotions.promotion_id', '=', 'coupon_sepulsa.promotion_id')
                        ->whereHas('campaign_status', function($status) {
                            $status->whereIn('campaign_status_name', ['ongoing']);
                        })
                        ->where('begin_date', '<=', Carbon::now())
                        ->where('end_date', '>=', Carbon::now())
                        ->get();

        $couponList = [];
        if ($coupons->count() > 0) {
            $response = VoucherList::create($config)->getList('', 100, [], 1);

            $newVouchers = [];
            if (isset($response->result->data) && ! empty($response->result->data)) {
                $sepulsaVouchers = $response->result->data;

                $number = 0;
                $couponList = [];
                $availableInDB = [];
                foreach($coupons as $coupon) {
                    $coupon->in_db = true;
                    $coupon->in_sepulsa = false;
                    foreach ($sepulsaVouchers as $voucher) {
                        if ($coupon->token === $voucher->token) {
                            $coupon->in_sepulsa = true;
                            $availableInDB[$voucher->token] = $voucher->token;
                            break;
                        }
                    }

                    $couponList[] = $coupon;
                }

                foreach($sepulsaVouchers as $voucher) {
                    $voucher->promotion_name = $voucher->title;
                    $voucher->in_db = false;
                    $voucher->in_sepulsa = true;

                    if (in_array($voucher->token, $availableInDB)) {
                        $voucher->in_db = true;
                    }

                    $couponList[] = $voucher;
                }
            }
        }

        $this->sendMail($couponList);
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [];
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return array(
            array('email-to', null, InputOption::VALUE_OPTIONAL, 'Send output to email(s) separated by comma.', 'developer@dominopos.com'),
        );
    }

    /**
     * Fake response
     *
     * @param boolean $dryRun
     */
    protected function sendMail($coupons)
    {
        $template = [
            'html' => 'emails.sepulsa-active-vouchers.html'
        ];

        Mail::send($template, compact('coupons'), function($mail) {
            $from = 'mailer@dominopos.com';
            $emails = explode(',', $this->option('email-to'));

            $mail->from($from, 'Gotomalls Robot');
            $mail->subject('Sepulsa Active Vouchers Report');
            $mail->to($emails);
        });

        $this->info('Mail Sent.');
    }
}
