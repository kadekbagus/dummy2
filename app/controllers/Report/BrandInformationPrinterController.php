<?php namespace Report;

use Report\DataPrinterController;
use Config;
use DB;
use PDO;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Orbit\Text as OrbitText;
use Mall;
use Carbon\Carbon as Carbon;
use MallAPIController;
use Setting;
use Response;
use BaseMerchant;
use PreExport;
use PostExport;
use Export;

class BrandInformationPrinterController extends DataPrinterController
{
    public function getBrandInformationPrintView()
    {
        try {
            $baseMerchantIds = OrbitInput::get('base_merchant_ids');
            $exportId = OrbitInput::get('export_id');
            $exportType = 'brand_information';
            $chunk = Config::get('orbit.export.chunk', 50);

            $usingCdn = Config::get('orbit.cdn.enable_cdn', FALSE);
            $defaultUrlPrefix = Config::get('orbit.cdn.providers.default.url_prefix', '');
            $urlPrefix = ($defaultUrlPrefix != '') ? $defaultUrlPrefix . '/' : '';

            $image = "CONCAT('{$urlPrefix}', path)";
            if ($usingCdn) {
                $image = "CASE WHEN cdn_url IS NULL THEN CONCAT('{$urlPrefix}', path) ELSE cdn_url END";
            }

            $export = BaseMerchant::select('base_merchants.base_merchant_id', 'base_merchants.name', 'base_merchants.phone', 'base_merchants.email', 'pre_exports.file_path', DB::raw("{$image} AS icon_url")
                                )
                                ->leftJoin('pre_exports', function ($q){
                                    $q->on('pre_exports.object_id', '=', 'base_merchants.base_merchant_id')
                                      ->on('pre_exports.object_type', '=', DB::raw("'merchant'"));
                                })
                                ->leftJoin('media', function ($q){
                                    $q->on('media.object_id', '=', 'base_merchants.base_merchant_id')
                                      ->on('media.media_name_id', '=', DB::raw("'base_merchant_logo_grab'"));
                                })
                                ->where('pre_exports.export_id', $exportId)
                                ->where('pre_exports.export_process_type', $exportType)
                                ->where('media.media_name_long', 'like', '%_orig')
                                ->whereIn('base_merchants.base_merchant_id', $baseMerchantIds)
                                ->groupBy('base_merchants.base_merchant_id');

            $export->chunk($chunk, function($_export) use ($baseMerchantIds, $exportId, $exportType) {
                foreach ($_export as $dtExport) {
                    $dir = Config::get('orbit.export.output_dir', '');
                    $filePath = $dtExport->file_path;

                    if (! file_exists($dir)) {
                        mkdir($dir, 0777);
                    }

                    $content = array(
                                array($dtExport->name, $dtExport->icon_url, $dtExport->phone, $dtExport->email),
                            );

                    $csv_handler = fopen($dir . $filePath, 'w');

                    foreach ($content as $fields) {
                        fputcsv($csv_handler, $fields);
                    }

                    fclose($csv_handler);

                    DB::beginTransaction();

                    $checkPre = PreExport::where('export_id',$exportId)
                                        ->where('object_type', 'merchant')
                                        ->where('object_id', $dtExport->base_merchant_id);

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
        } catch (InvalidArgsException $e) {
            \Log::error('*** Brand Information export file error, messge: ' . $e->getMessage() . '***');
            DB::rollBack();
        } catch (QueryException $e) {
            \Log::error('*** Brand Information export file error, messge: ' . $e->getMessage() . '***');
            DB::rollBack();
        } catch (Exception $e) {
            \Log::error('*** Brand Information export file error, messge: ' . $e->getMessage() . '***');
            DB::rollBack();
        }
    }
}
