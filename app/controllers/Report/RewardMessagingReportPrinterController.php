<?php namespace Report;

use Report\DataPrinterController;
use Config;
use DB;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Orbit\Text as OrbitText;
use Response;
use Coupon;
use Str;
use PreExport;
use PostExport;
use Export;

class RewardMessagingReportPrinterController
{
    /**
     * Static method to instantiate the object.
     *
     * @param string $contentType
     * @return ControllerAPI
     */
    public static function create()
    {
        return new static;
    }

    public function postPrintRewardMessaging()
    {
        try {
            $couponIds = OrbitInput::post('coupon_ids');
            $exportId = OrbitInput::post('export_id');
            $exportType = 'reward_message';
            $chunk = Config::get('orbit.export.chunk', 50);
            $dir = Config::get('orbit.export.output_dir', '');

            $usingCdn = Config::get('orbit.cdn.enable_cdn', FALSE);
            $defaultUrlPrefix = Config::get('orbit.cdn.providers.default.url_prefix', '');
            $urlPrefix = ($defaultUrlPrefix != '') ? $defaultUrlPrefix . '/' : '';

            $prefix = DB::getTablePrefix();

            $image = "CONCAT('{$urlPrefix}', path)";
            if ($usingCdn) {
                $image = "CASE WHEN cdn_url IS NULL THEN CONCAT('{$urlPrefix}', path) ELSE cdn_url END";
            }

            $export = Coupon::select('promotions.promotion_id as sku', 'pre_exports.file_path')
                            ->leftJoin('pre_exports', function ($q){
                                    $q->on('pre_exports.object_id', '=', 'promotions.promotion_id')
                                      ->on('pre_exports.object_type', '=', DB::raw("'coupon'"));
                            })
                            ->where('pre_exports.export_id', $exportId)
                            ->where('pre_exports.export_process_type', $exportType)
                            ->whereIn('promotions.promotion_id', $couponIds)
                            ->orderBy('promotions.promotion_name', 'asc');

            $export->chunk($chunk, function($_export) use ($couponIds, $exportId, $exportType, $dir) {
                foreach ($_export as $dtExport) {
                    $filePath = $dtExport->file_path;

                    if (! file_exists($dir)) {
                        mkdir($dir, 0777, true);
                    }

                    $hashbang = '/';
                    if ((Config::get('sitemap.hashbang'))) {
                        $hashbang = '/#!/';
                    }

                    $prefix = DB::getTablePrefix();

                    $translation = Coupon::select('promotions.promotion_id as sku',
                                            'languages.name as locale',
                                            DB::raw("CASE WHEN ({$prefix}coupon_translations.short_description = '' OR {$prefix}coupon_translations.short_description IS NULL) THEN default_translation.short_description ELSE {$prefix}coupon_translations.short_description END as short_description"),
                                            DB::raw("CASE WHEN ({$prefix}coupon_translations.promotion_name = '' OR {$prefix}coupon_translations.promotion_name IS NULL) THEN default_translation.promotion_name ELSE {$prefix}coupon_translations.promotion_name END as promotion_name"),
                                            DB::raw("CASE WHEN ({$prefix}coupon_translations.description = '' OR {$prefix}coupon_translations.description IS NULL) THEN default_translation.description ELSE {$prefix}coupon_translations.description END as description")
                                            )
                                    ->join('campaign_account', 'campaign_account.user_id', '=', 'promotions.created_by')
                                    ->leftJoin('coupon_translations', 'coupon_translations.promotion_id', '=', 'promotions.promotion_id')
                                    ->leftJoin('languages', 'languages.language_id', '=', 'coupon_translations.merchant_language_id')
                                    ->leftJoin('languages as default_language', DB::raw("default_language.name"), '=', 'campaign_account.mobile_default_language')
                                    ->leftJoin('coupon_translations as default_translation', function ($q) {
                                        $q->on(DB::raw("default_translation.promotion_id"), '=', 'promotions.promotion_id')
                                          ->on(DB::raw("default_translation.merchant_language_id"), '=', DB::raw("default_language.language_id"));
                                    })
                                    ->where('promotions.promotion_id', $dtExport->sku)
                                    ->orderBy('promotions.promotion_name', 'asc')
                                    ->get();

                    $content = array();
                    foreach ($translation as $dtTranslation) {
                        $url = Config::get('app.url') . $hashbang . 'coupons/' . $dtExport->sku . '/' . Str::slug($dtTranslation->promotion_name);
                        $content[] = array(
                                    $dtTranslation->sku,
                                    $dtTranslation->locale,
                                    $dtTranslation->short_description,
                                    '',
                                    '',
                                    $url,
                                    $dtTranslation->promotion_name,
                                    '',
                                    '',
                                    $dtTranslation->description,
                                    '',
                                    '',
                                    '',
                                    '',
                                    '',
                                    '',
                                    ''
                                );
                    }

                    $csv_handler = fopen($dir . $filePath, 'w');
                    foreach ($content as $fields) {
                        fputcsv($csv_handler, $fields);
                    }
                    fclose($csv_handler);

                    DB::beginTransaction();

                    $checkPre = PreExport::where('export_id',$exportId)
                                        ->where('object_type', 'coupon')
                                        ->where('object_id', $dtExport->sku);

                    $_preExport = clone $checkPre;
                    $preExport = $_preExport->where('export_process_type', $exportType)->first();

                    if (is_object($preExport)) {
                        $postExport = $preExport->moveToPostExport();
                    }

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
            \Log::error('*** Brand messaging export file error, messge: ' . $e->getMessage() . '***');
            DB::rollBack();

            return [
                'status' => 'fail',
                'message' => $e->getMessage()
            ];
        } catch (QueryException $e) {
            \Log::error('*** Brand messaging export file error, messge: ' . $e->getMessage() . '***');
            DB::rollBack();

            return [
                'status' => 'fail',
                'message' => $e->getMessage()
            ];
        } catch (Exception $e) {
            \Log::error('*** Brand messaging export file error, messge: ' . $e->getMessage() . '***');
            DB::rollBack();

            return [
                'status' => 'fail',
                'message' => $e->getMessage()
            ];
        }
    }


}