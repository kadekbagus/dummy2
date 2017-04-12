<?php namespace Report;

use Report\DataPrinterController;
use Config;
use DB;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Orbit\Text as OrbitText;
use Response;
use Coupon;
use Str;

class RewardMessagingReportPrinterController
{

    /*
        Field :
        ---------------------
        SKU
        Locale
        Highlight
        Promotional Link URL 1
        Promotional Link URL 1 Title
        Term Detail
    */

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
            $exportType = 'reward_messaging';
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

            $export = Coupon::select('promotions.promotion_id as sku',
                                    'campaign_account.mobile_default_language as locale',
                                    'coupon_translations.short_description',
                                    'coupon_translations.promotion_name',
                                    'coupon_translations.description',
                                    'pre_exports.file_path'
                                    )
                            ->join('campaign_account', 'campaign_account.user_id', '=', 'promotions.created_by')
                            ->join('languages', 'languages.name', '=', 'campaign_account.mobile_default_language')
                            ->leftJoin('coupon_translations', function ($q) {
                                $q->on('coupon_translations.promotion_id', '=', 'promotions.promotion_id')
                                  ->on('coupon_translations.merchant_language_id', '=', 'languages.language_id');
                            })
                            ->leftJoin('pre_exports', function ($q){
                                    $q->on('pre_exports.object_id', '=', 'promotions.promotion_id')
                                      ->on('pre_exports.object_type', '=', DB::raw("'coupon'"));
                            })
                            ->where('pre_exports.export_id', $exportId)
                            ->where('pre_exports.export_process_type', $exportType)
                            ->whereIn('promotions.promotion_id', $couponIds);

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

                    $url = Config::get('app.url') . $hashbang . 'coupons/' . $dtExport->sku . '/' . Str::slug($dtExport->promotion_name);
                    $content = array(
                                    array(
                                        $dtExport->sku,
                                        $dtExport->locale,
                                        $dtExport->short_description,
                                        '',
                                        '',
                                        $url,
                                        $dtExport->promotion_name,
                                        '',
                                        '',
                                        $dtExport->description,
                                        '',
                                        '',
                                        '',
                                        '',
                                        '',
                                        '',
                                        ''
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