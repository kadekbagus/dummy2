<?php

/**
 * Check issued sepulsa vouchers number from their API
 * against our issued number from the DB
 * @author Ahmad <Ahmad@dominopos.com>
 */

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Orbit\Helper\Sepulsa\API\VoucherList;
use Carbon\Carbon;

class SepulsaRedemptionStatCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'sepulsa:redeem-stat';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to check sepulsa redeem stat vs our DB stat.';

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
        $list = CouponSepulsa::select(
                    'promotion_name',
                    'token',
                    'promotions.maximum_issued_coupon as gtm_available_count',
                    DB::raw('case when sum(count_i) is null
                            then 0
                            else sum(count_i)
                        end as gtm_issued_count'),
                    DB::raw('case when sum(count_r) is null
                            then 0
                            else sum(count_r)
                        end as gtm_redeemed_count')
                )
            ->leftJoin('promotions', 'promotions.promotion_id', '=', 'coupon_sepulsa.promotion_id')
            ->leftJoin(
                    DB::raw("(
                        select promotion_id, count(issued_coupon_id) as count_i from orb_issued_coupons ic1
                        where ic1.status = 'issued'
                        group by promotion_id
                    ) as ic_issued"), DB::raw('ic_issued.promotion_id'), '=', 'promotions.promotion_id'
                )
            ->leftJoin(
                    DB::raw("(
                        select promotion_id, count(issued_coupon_id) as count_r from orb_issued_coupons ic2
                        where ic2.status = 'redeemed'
                        group by promotion_id
                    ) as ic_redeemed"), DB::raw('ic_redeemed.promotion_id'), '=', 'promotions.promotion_id'
                )
            ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'promotions.campaign_status_id')
            ->where('campaign_status.campaign_status_name', 'ongoing')
            ->where('promotions.begin_date', '<=', Carbon::now())
            ->where('promotions.end_date', '>=', Carbon::now())
            ->groupBy('token')
            ->get();

        if (! empty($list)) {
            $response = VoucherList::create($config)->getList('', count($list), [], $page=1);

            if (isset($response->result->data) && ! empty($response->result->data)) {
                foreach($list as $item) {
                    $item->sepulsa_available_count = 'N/A';
                    $item->sepulsa_issued_count = 'N/A';
                    $item->sepulsa_redeemed_count = 'N/A';

                    foreach ($response->result->data as $record) {
                        if ($item->token === $record->token) {
                            $item->sepulsa_available_count = $record->reserve_stock;
                            $item->sepulsa_issued_count = $record->redeem_stock;
                            $item->sepulsa_redeemed_count = $record->redeem_quantity;
                            break;
                        }
                    }
                }
            }
        }

        $this->sendMail($list);
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
            array('email-to', null, InputOption::VALUE_OPTIONAL, 'Send output to email(s) separated by comma.', null),
            array('get-current-config', null, InputOption::VALUE_NONE, 'Return currently used sepulsa config.', null),
        );
    }

    /**
     * Fake response
     *
     * @param boolean $dryRun
     */
    protected function sendMail($data)
    {
        $data = [
            'data' => $data->toArray()
        ];
        Mail::send('emails.sepulsa-stat.html', $data, function($message) {
            $from = 'no-reply@gotomalls.com';
            $emails = explode(',', $this->option('email-to'));

            $message->from($from, 'Gotomalls Robot');
            $message->subject('Sepulsa vs GTM Stats');
            $message->to($emails);
        });

        $this->info('Mail Sent.');
    }
}
