<?php namespace Report;

use Report\DataPrinterController;
use Config;
use DB;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Orbit\Text as OrbitText;
use Response;
use Coupon;

class RewardInformationReportPrinterController extends DataPrinterController
{
    public function postPrintRewardInformation()
    {

        /*
            Field :
            ---------------------
            SKU
            name
            Support Email
            Support Phone
            Category
            Tag
            Total Inventory
            Reward Value
            Reward Value Currency
            Country
            City
            Offer Start Date
            Offer End Date
            Validity Start Date
            Validity End Date
            Offer Type
            Voucher Value
            Discount Percentage
            Deal List Price
            Deal Original Price
            Redemption Method
            Header Image URL
            Image 1 URL
        */

        try {
            $couponIds = OrbitInput::post('coupon_ids');
            $exportId = OrbitInput::post('export_id');
            $exportType = 'reward_information';
            $chunk = Config::get('orbit.export.chunk', 50);

            $usingCdn = Config::get('orbit.cdn.enable_cdn', FALSE);
            $defaultUrlPrefix = Config::get('orbit.cdn.providers.default.url_prefix', '');
            $urlPrefix = ($defaultUrlPrefix != '') ? $defaultUrlPrefix . '/' : '';

            $prefix = DB::getTablePrefix();

            $image = "CONCAT('{$urlPrefix}', path)";
            if ($usingCdn) {
                $image = "CASE WHEN cdn_url IS NULL THEN CONCAT('{$urlPrefix}', path) ELSE cdn_url END";
            }

            $export = Coupon::select(
                    'promotions.promotion_id as sku',
                    DB::raw("default_translation.promotion_name as name"),
                    'users.user_email',
                    'campaign_account.phone',
                    DB::raw("
                        (SELECT GROUP_CONCAT(DISTINCT ok.keyword) as test
                        FROM {$prefix}promotions op
                        LEFT JOIN {$prefix}keyword_object ork ON ork.object_id = op.promotion_id AND ork.object_type = 'coupon'
                        INNER JOIN {$prefix}keywords ok ON ok.keyword_id = ork.keyword_id
                        where promotion_id = {$prefix}promotions.promotion_id
                        ) as keywords
                    "),
                    DB::raw('"category"'),
                    'promotions.maximum_issued_coupon as total_inventory',
                    'promotions.promotion_value as reward_value',
                    'promotions.currency',
                    'countries.name as country',
                    // City
                    DB::raw("
                        (SELECT
                        group_concat(DISTINCT ogc.grab_city_name) as grab_city_name
                        FROM {$prefix}promotion_retailer opt
                        LEFT JOIN {$prefix}merchants om ON om.merchant_id = opt.retailer_id
                        LEFT JOIN {$prefix}merchants oms ON oms.merchant_id = om.parent_id
                        LEFT JOIN {$prefix}vendor_gtm_cities ovgc ON ovgc.gtm_city = (CASE WHEN om.object_type = 'mall' THEN om.city ELSE oms.city END) AND vendor_type = 'grab'
                        INNER JOIN {$prefix}grab_cities ogc ON ogc.grab_city_external_id = ovgc.vendor_city
                        where promotion_id = {$prefix}promotions.promotion_id
                        ) as grab_city_name
                    "),
                    'promotions.begin_date',
                    'promotions.end_date',
                    'promotions.begin_date',
                    'promotions.coupon_validity_in_date',
                    'promotions.offer_type',
                    'promotions.offer_value as voucher_value',
                    'promotions.offer_value as discount_percentage',
                    'promotions.offer_value as deal_list_price',
                    'promotions.original_price',
                    'promotions.redemption_method',
                    // Header Image URL
                    DB::raw("
                            (SELECT {$image}
                            FROM {$prefix}media m
                            WHERE m.media_name_long = 'coupon_header_grab_translation_image_orig'
                            AND m.object_id = default_translation.coupon_translation_id) AS header_original_media_path
                    "),
                    // Image 1 URL
                    DB::raw("
                            (SELECT {$image}
                            FROM {$prefix}media m
                            WHERE m.media_name_long = 'coupon_image_grab_translation_image_orig'
                            AND m.object_id = default_translation.coupon_translation_id) AS image_original_media_path
                    "),
                    DB::raw("
                        (SELECT
                            ot.timezone_name
                        FROM {$prefix}promotion_retailer opt
                            LEFT JOIN {$prefix}merchants om ON om.merchant_id = opt.retailer_id
                            LEFT JOIN {$prefix}merchants oms ON oms.merchant_id = om.parent_id
                            LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                        WHERE opt.promotion_id = {$prefix}promotions.promotion_id
                        ORDER BY CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name) ASC
                        LIMIT 1
                        ) as timezone
                    ")
                )
                // Get campaign account
                ->join('campaign_account', 'campaign_account.user_id', '=', 'promotions.created_by')
                // Get defaulrt language
                ->join('languages', 'languages.name', '=', 'campaign_account.mobile_default_language')
                // Get email and phone
                ->join('users', 'users.user_id', '=', 'campaign_account.user_id')
                // Get country base on pmp user
                ->join('user_details', 'users.user_id', '=', 'campaign_account.user_id')
                ->join('countries', 'user_details.country_id' , '=' , 'countries.country_id')
                // get default content language
                ->leftJoin('coupon_translations', 'coupon_translations.promotion_id', '=', 'promotions.promotion_id')
                ->leftJoin('coupon_translations as default_translation', function ($q) {
                    $q->on(DB::raw('default_translation.promotion_id'), '=', 'promotions.promotion_id')
                      ->on(DB::raw('default_translation.merchant_language_id'), '=', 'languages.language_id');
                })
                ->whereIn('promotions.promotion_id', $couponIds)
                ->groupBy('promotions.promotion_id');

            $export->chunk($chunk, function($_export) use ($couponIds, $exportId, $exportType) {
                foreach ($_export as $dtExport) {
                    $dir = Config::get('orbit.export.output_dir', '');
                    $filePath = $dtExport->file_path;

                    if (! file_exists($dir)) {
                        mkdir($dir, 0777);
                    }

                    // Get offset timezone
                    $timezoneArea = new \DateTimeZone($dtExport->timezone);
                    $myDateTime = new \DateTime('now', $timezoneArea);
                    $offsetTimezone = substr($myDateTime->format('r'), -5); // Wed, 12 Apr 2017 21:52:51 +0700

                    $content = array(
                                    array(
                                        $dtExport->sku,
                                        $dtExport->name,
                                        $dtExport->user_email,
                                        $dtExport->phone,
                                        $dtExport->keyword,
                                        $dtExport->category,
                                        $dtExport->total_inventory,
                                        $dtExport->reward_value,
                                        $dtExport->currency,
                                        $dtExport->country,
                                        $dtExport->grab_city_name,
                                        $dtExport->begin_date . $offsetTimezone,
                                        $dtExport->end_date . $offsetTimezone,
                                        $dtExport->begin_date . $offsetTimezone,
                                        $dtExport->coupon_validity_in_date . $offsetTimezone,
                                        $dtExport->offer_type,
                                        $dtExport->voucher_value,
                                        $dtExport->discount_percentage,
                                        $dtExport->deal_list_price,
                                        $dtExport->original_price,
                                        $dtExport->redemption,
                                        $dtExport->header_original_media_path,
                                        $dtExport->image_original_media_path
                                    ),
                            );

                    $csv_handler = fopen($dir . $filePath, 'w');

                    foreach ($content as $fields) {
                        fputcsv($csv_handler, $fields);
                    }

                    fclose($csv_handler);

                    DB::beginTransaction();

                    $checkPre = PreExport::where('export_id',$exportId)
                                        ->where('object_type', 'coupon')
                                        ->where('object_id', $dtExport->sku);

                    $preExport = clone $checkPre;
                    $preExport = $preExport->where('export_process_type', $exportType)->first();
                    $postExport = $preExport->moveToPostExport();

                    $checkPre = $checkPre->count();

                    if ($checkPre === 0) {
                        $export = Export::where('export_id', $exportId)->first();
                        $totalFinished = $export->finished_export + 1;

                        $export->finished_export = $totalFinished;

                        if ((int) $export->total_export === (int) $totalFinished) {
                            $export->status = 'done';
                        }

                        $export->save();
                    }

                    DB::commit();
                }
            });

            return ['status' => 'ok'];

        } catch (InvalidArgsException $e) {
            \Log::error('*** Reward information export file error, messge: ' . $e->getMessage() . '***');
            DB::rollBack();

            return [
                'status' => 'fail',
                'message' => $e->getMessage()
            ];
        } catch (QueryException $e) {
            \Log::error('*** Reward information export file error, messge: ' . $e->getMessage() . '***');
            DB::rollBack();

            return [
                'status' => 'fail',
                'message' => $e->getMessage()
            ];
        } catch (Exception $e) {
            \Log::error('*** Reward information export file error, messge: ' . $e->getMessage() . '***');
            DB::rollBack();

            return [
                'status' => 'fail',
                'message' => $e->getMessage()
            ];
        }
    }

}