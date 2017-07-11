<?php namespace Orbit\Queue;
/**
 * Process queue for calculate campaign spending
 * News, Promotions, and Coupon
 *
 * @author Shelgi Prasetyo <shelgi@dominopos.com>
 */
use NewsMerchant;
use CampaignDailySpending;
use PromotionRetailer;
use Mall;
use DB;
use Carbon\Carbon as Carbon;
use Config;
use Log;
use Orbit\Helper\Util\JobBurier;
use Exception;

class SpendingCalculation
{
    /**
     * Laravel main method to fire a job on a queue.
     *
     * @author Shelgi Prasetyo <shelgi@dominopos.com>
     * @param Job $job
     * @param array $data [user_id => NUM]
     */
    public function fire($job, $data)
    {
        try {
            $updateSpendingAllowed = Config::get('orbit.campaign_spending.update_spending_on_save', TRUE);
            if ($updateSpendingAllowed === FALSE) {
                $job->delete();

                return FALSE;
            }

            $prefix = DB::getTablePrefix();
            $campaign_id = $data['campaign_id'];
            $campaign_type = $data['campaign_type'];

            if ($campaign_type === 'coupon') {
                $getMall = PromotionRetailer::select('promotions.*', DB::raw("CASE WHEN {$prefix}promotion_retailer.object_type = 'mall' THEN {$prefix}promotion_retailer.retailer_id ELSE {$prefix}merchants.parent_id END AS per_mall_id"))
                                    ->join('merchants', 'merchants.merchant_id', '=', 'promotion_retailer.retailer_id')
                                    ->join('promotions', 'promotions.promotion_id', '=', 'promotion_retailer.promotion_id')
                                    ->where('promotion_retailer.promotion_id', $campaign_id)
                                    ->groupBy('per_mall_id')
                                    ->get();
            } else {
                $getMall = NewsMerchant::select('news.*', DB::raw("CASE WHEN {$prefix}news_merchant.object_type = 'mall' THEN {$prefix}news_merchant.merchant_id ELSE {$prefix}merchants.parent_id END AS per_mall_id"))
                                    ->join('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                                    ->join('news', 'news.news_id', '=', 'news_merchant.news_id')
                                    ->where('news_merchant.news_id', $campaign_id)
                                    ->groupBy('per_mall_id')
                                    ->get();
            }

            foreach ($getMall as $listMall) {
                // Begin database transaction
                DB::beginTransaction();

                $mall = $listMall['per_mall_id'];
                $begin_date = $listMall['begin_date'];
                $end_date = $listMall['end_date'];

                $timezone = Mall::leftJoin('timezones','timezones.timezone_id','=','merchants.timezone_id')
                            ->where('merchants.merchant_id','=', $mall)
                            ->first();

                $procResults = DB::statement("CALL prc_campaign_detailed_cost({$this->quote($campaign_id)}, {$this->quote($campaign_type)}, NULL, NULL, {$this->quote($mall)})");

                if ($procResults === false) {
                    // Do Nothing
                }

                $getspending = DB::table(DB::raw('tmp_campaign_cost_detail'))->first();

                $nowMall = Carbon::now($timezone['timezone_name']);
                $dateNowMall = $nowMall->toDateString();
                $begin = date('Y-m-d', strtotime($begin_date));
                $end = date('Y-m-d', strtotime($end_date));

                if (! empty($getspending)) {
                    // only calculate spending when update date between start and date of campaign
                    if ($dateNowMall >= $begin && $dateNowMall <= $end) {
                        $daily = CampaignDailySpending::where('date', '=', $getspending->date_in_utc)->where('campaign_id', '=', $campaign_id)->where('mall_id', '=', $mall)->first();

                        if ($daily['campaign_daily_spending_id']) {
                            $dailySpending = CampaignDailySpending::find($daily['campaign_daily_spending_id']);
                        } else {
                            $dailySpending = new CampaignDailySpending;
                        }

                        $dailySpending->date = $getspending->date_in_utc;
                        $dailySpending->campaign_type = $campaign_type;
                        $dailySpending->campaign_id = $campaign_id;
                        $dailySpending->mall_id = $mall;
                        $dailySpending->number_active_tenants = $getspending->campaign_number_tenant;
                        $dailySpending->base_price = $getspending->base_price;
                        $dailySpending->campaign_status = $getspending->campaign_status;
                        $dailySpending->total_spending = $getspending->daily_cost;
                        $dailySpending->save();

                        if ($dailySpending) {
                            \Log::info('*** Spending Calculation Queue for campaign_id : ' . $campaign_id . ' completed ***');
                            DB::commit();
                        } else {
                            \Log::error('*** Spending Calculation Queue for campaign_id : ' . $campaign_id . ' error ***');
                            DB::rollBack();
                        }
                    }
                }
            }

            $message = sprintf('[Job ID: `%s`] Spending Calculation; Status: Success;', $job->getJobId());
            Log::info($message);

            $job->delete();

            return [
                'status' => 'ok',
                'message' => $message
            ];
        } catch (Exception $e) {
            $message = sprintf('[Job ID: `%s`] Spending Calculation; Status: FAIL; Code: %s; Message: %s',
                    $job->getJobId(),
                    $e->getCode(),
                    $e->getMessage());
            Log::info($message);
        }

        // Bury the job for later inspection
        JobBurier::create($job, function($theJob) {
            // The queue driver does not support bury.
            $theJob->delete();
        })->bury();

        return [
            'status' => 'fail',
            'message' => $message
        ];
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }
}