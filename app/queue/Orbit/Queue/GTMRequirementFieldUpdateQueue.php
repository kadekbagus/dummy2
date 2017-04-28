<?php namespace Orbit\Queue;
/**
 * Process queue for updating third party requirement field
 *
 * @author kadek <kadek@dominopos.com>
 */
use Config;
use DB;
use Coupon;
use Media;
use User;
use PromotionRetailer;
use Orbit\Helper\Util\JobBurier;
use Exception;
use Log;
use Orbit\FakeJob;
use Mall;

class GTMRequirementFieldUpdateQueue
{
    /**
     * Laravel main method to fire a job on a queue.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param Job $job
     * @param array $data [user_id => NUM]
     */
    public function fire($job, $data)
    {
      try {
        $prefix = DB::getTablePrefix();
        // check required field
          switch ($data['from']) {
              case 'base_store' :
                  $base_store_id = $data['id'];
                  $completed = false;
                  $media = Media::where('media_name_id', '=', 'base_store_image_grab')
                                 ->where('metadata', '=', 'order-0')
                                 ->where('object_id', '=', $base_store_id)
                                 ->count();

                  $completed = $media <= 0 ? false : true;

                  $coupons = Coupon::select('promotions.promotion_id')
                                  ->leftJoin('promotion_retailer', 'promotion_retailer.promotion_id', '=', 'promotions.promotion_id')
                                  ->where('promotion_retailer.retailer_id', '=', $base_store_id)
                                  ->where('promotions.is_3rd_party_promotion', '=', 'Y')
                                  ->get();

                  foreach ($coupons as $coupon) {
                      $_coupon = Coupon::where('promotion_id', '=', $coupon->promotion_id)->first();
                      if (!$completed) {
                          $_coupon->is_3rd_party_field_complete = 'N';
                      } else {
                          $_coupon->is_3rd_party_field_complete = $this->setComplete($coupon->promotion_id, 'base_store');
                      }
                      $_coupon->save();
                  }
                  break;

              case 'pmp_admin_portal' :
                  $user_id = $data['id'];
                  $completed = false;
                  $user = User::select( DB::raw("CASE
                                  WHEN ({$prefix}campaign_account.phone IS NULL OR {$prefix}campaign_account.phone = '') THEN 'not_complete'
                                  WHEN ({$prefix}user_details.country_id IS NULL OR {$prefix}user_details.country_id = '') THEN 'not_complete'
                                  WHEN ({$prefix}campaign_account.mobile_default_language IS NULL OR {$prefix}campaign_account.mobile_default_language = '') THEN 'not_complete'
                                  ELSE 'complete'
                              END AS status
                                  ")
                              )
                        ->leftJoin('user_details', 'user_details.user_id', '=', 'users.user_id')
                        ->leftJoin('campaign_account', 'campaign_account.user_id', '=', 'users.user_id')
                        ->where('users.user_id', '=', $user_id)
                        ->first();

                  $completed = $user->status == 'complete' ? true : false;

                  $coupons = Coupon::select('promotions.promotion_id')
                                  ->where('promotions.created_by', '=', $user_id)
                                  ->where('promotions.is_3rd_party_promotion', '=', 'Y')
                                  ->get();

                  foreach ($coupons as $coupon) {
                      $_coupon = Coupon::where('promotion_id', '=', $coupon->promotion_id)->first();
                      if (!$completed) {
                          $_coupon->is_3rd_party_field_complete = 'N';
                      } else {
                          $_coupon->is_3rd_party_field_complete = $this->setComplete($coupon->promotion_id, 'pmp_admin_portal');
                      }
                      $_coupon->save();
                  }
                  break;
              case 'mall_admin_portal' :
                  $merchant_id = $data['id'];
                  $completed = false;
                  $mall = Mall::select(DB::raw("
                              CASE
                                WHEN ({$prefix}merchants.postal_code IS NULL OR {$prefix}merchants.postal_code = '') THEN 'not_complete'
                                WHEN ({$prefix}merchants.address_line1 IS NULL OR {$prefix}merchants.address_line1 = '') THEN 'not_complete'
                                WHEN ({$prefix}merchants.country IS NULL OR {$prefix}merchants.country = '') THEN 'not_complete'
                                WHEN ({$prefix}merchants.city IS NULL OR {$prefix}merchants.city = '') THEN 'not_complete'
                                WHEN ({$prefix}merchants.province IS NULL OR {$prefix}merchants.province = '') THEN 'not_complete'
                                WHEN (X({$prefix}merchant_geofences.position) IS NULL OR X({$prefix}merchant_geofences.position) = '') THEN 'not_complete'
                                WHEN (Y({$prefix}merchant_geofences.position) IS NULL OR Y({$prefix}merchant_geofences.position) = '') THEN 'not_complete'
                                ELSE 'complete'
                              END AS status
                          "))
                    ->leftJoin('merchant_geofences', 'merchant_geofences.merchant_id', '=', 'merchants.merchant_id')
                    ->where('merchants.merchant_id', '=', $merchant_id)
                    ->first();

                  $completed = $mall->status == 'complete' ? true : false;

                  $coupons = Coupon::select('promotions.promotion_id')
                                  ->leftJoin('promotion_retailer', 'promotion_retailer.promotion_id', '=', 'promotions.promotion_id')
                                  ->leftJoin('merchants', 'merchants.merchant_id', '=', 'promotion_retailer.retailer_id')
                                  ->where('merchants.parent_id', '=', $merchant_id)
                                  ->orWhere('merchants.merchant_id', '=', $merchant_id)
                                  ->where('promotions.is_3rd_party_promotion', '=', 'Y')
                                  ->groupBy('promotions.promotion_id')
                                  ->get();

                  foreach ($coupons as $coupon) {
                      $_coupon = Coupon::where('promotion_id', '=', $coupon->promotion_id)->first();
                      if (!$completed) {
                          $_coupon->is_3rd_party_field_complete = 'N';
                      } else {
                          $_coupon->is_3rd_party_field_complete = $this->setComplete($coupon->promotion_id, 'mall_admin_portal');
                      }
                      $_coupon->save();
                  }
                break;
          }

            // Safely delete the object
            $job->delete();

            $message = sprintf('GTM requirement field update queue from %s success', $data['from']);
            Log::info($message);
        } catch (Exception $e) {
            $message = sprintf('error GTM requirement field update queue, message: %s', $e);
            Log::info($message);
            return $message;
        }

        // Bury the job for later inspection
        JobBurier::create($job, function($theJob) {
            // The queue driver does not support bury.
            $theJob->delete();
        })->bury();
    }

    public function setComplete($promotion_id=null, $type) {
      $prefix = DB::getTablePrefix();
      $all_complete = 'N';


      // check pmp account
      if ($type != 'pmp_admin_portal') {
        $user = User::select( DB::raw("CASE
                                  WHEN ({$prefix}campaign_account.phone IS NULL OR {$prefix}campaign_account.phone = '') THEN 'not_complete'
                                  WHEN ({$prefix}user_details.country_id IS NULL OR {$prefix}user_details.country_id = '') THEN 'not_complete'
                                  WHEN ({$prefix}campaign_account.mobile_default_language IS NULL OR {$prefix}campaign_account.mobile_default_language = '') THEN 'not_complete'
                                  ELSE 'complete'
                              END AS status
                                  ")
                              )
                        ->leftJoin('user_details', 'user_details.user_id', '=', 'users.user_id')
                        ->leftJoin('campaign_account', 'campaign_account.user_id', '=', 'users.user_id')
                        ->join('promotions', 'promotions.created_by', '=', 'users.user_id')
                        ->where('promotions.promotion_id', '=', $promotion_id)
                        ->first();

        $all_complete = $user->status == 'complete' ? 'Y' : 'N';
      }

      // check store
      if ($type != 'base_store') {
        $media = PromotionRetailer::leftJoin('media', 'media.object_id', '=', 'promotion_retailer.retailer_id')
                                  ->where('media_name_id', '=', 'base_store_image_grab')
                                  ->where('metadata', '=', 'order-0')
                                  ->where('promotion_retailer.promotion_id', $promotion_id)
                                  ->count();

        $all_complete = $media <= 0 ? 'N' : 'Y';
      }

      // check mall
      if ($type != 'mall_admin_portal') {
        $mall_id = PromotionRetailer::leftJoin('merchants', 'merchants.merchant_id', '=', 'promotion_retailer.retailer_id')
                                    ->where('promotion_retailer.promotion_id', $promotion_id)
                                    ->lists('parent_id');

        $mall = Mall::select(DB::raw("sum(
                            CASE
                              WHEN ({$prefix}merchants.postal_code IS NULL OR {$prefix}merchants.postal_code = '') THEN 1
                              WHEN ({$prefix}merchants.address_line1 IS NULL OR {$prefix}merchants.address_line1 = '') THEN 1
                              WHEN ({$prefix}merchants.country IS NULL OR {$prefix}merchants.country = '') THEN 1
                              WHEN ({$prefix}merchants.city IS NULL OR {$prefix}merchants.city = '') THEN 1
                              WHEN ({$prefix}merchants.province IS NULL OR {$prefix}merchants.province = '') THEN 1
                              WHEN (X({$prefix}merchant_geofences.position) IS NULL OR X({$prefix}merchant_geofences.position) = '') THEN 1
                              WHEN (Y({$prefix}merchant_geofences.position) IS NULL OR Y({$prefix}merchant_geofences.position) = '') THEN 1
                              ELSE 0
                            END) AS status
                        "))
                  ->leftJoin('merchant_geofences', 'merchant_geofences.merchant_id', '=', 'merchants.merchant_id')
                  ->whereIn('merchants.merchant_id', $mall_id)
                  ->first();

        $all_complete = $mall->status > 0 ? 'N' : 'Y';
      }

      return $all_complete;
    }
}