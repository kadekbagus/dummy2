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

class BrandMessagePrinterController
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

    public function getBrandMessagePrintView()
    {
        try {
            $baseMerchantIds = OrbitInput::get('base_merchant_ids');
            $exportId = OrbitInput::get('export_id');
            $exportType = 'brand_message';
            $chunk = Config::get('orbit.export.chunk', 50);
            $dir = Config::get('orbit.export.output_dir', '');

            $export = BaseMerchant::select('base_merchants.base_merchant_id', 'pre_exports.file_path')
                                ->leftJoin('pre_exports', function ($q){
                                    $q->on('pre_exports.object_id', '=', 'base_merchants.base_merchant_id')
                                      ->on('pre_exports.object_type', '=', DB::raw("'merchant'"));
                                })
                                ->where('pre_exports.export_id', $exportId)
                                ->where('pre_exports.export_process_type', $exportType)
                                ->whereIn('base_merchants.base_merchant_id', $baseMerchantIds)
                                ->groupBy('base_merchants.base_merchant_id');

            $export->chunk($chunk, function($_export) use ($baseMerchantIds, $exportId, $exportType, $dir) {
                foreach ($_export as $dtExport) {
                    $filePath = $dtExport->file_path;

                    if (! file_exists($dir)) {
                        mkdir($dir, 0777, true);
                    }

                    $prefix = DB::getTablePrefix();

                    $translation = BaseMerchant::select('base_merchants.base_merchant_id',
                                                'base_merchants.name',
                                                'orb_languages.name as language',
                                                DB::raw("CASE WHEN ({$prefix}base_merchant_translations.description = '' OR {$prefix}base_merchant_translations.description IS NULL)
                                                            THEN default_translation.description
                                                            ELSE {$prefix}base_merchant_translations.description END as description"),
                                                'base_merchants.url')
                                            ->leftJoin('base_merchant_translations', 'base_merchant_translations.base_merchant_id', '=', 'base_merchants.base_merchant_id')
                                            ->leftJoin('languages', 'languages.language_id', '=', 'base_merchant_translations.language_id')
                                            ->leftJoin('languages as default_language', DB::raw("default_language.name"), '=', 'base_merchants.mobile_default_language')
                                            ->leftJoin('base_merchant_translations as default_translation', function ($q){
                                                $q->on(DB::raw("default_translation.language_id"), '=', DB::raw("default_language.language_id"))
                                                  ->on(DB::raw("default_translation.base_merchant_id"), '=', 'base_merchants.base_merchant_id');
                                            })
                                            ->where('languages.status', 'active')
                                            ->where('base_merchants.base_merchant_id', $dtExport->base_merchant_id)
                                            ->get();

                    $content = array();
                    foreach ($translation as $dtTranslation) {
                        $content[] = array($dtTranslation->name, $dtTranslation->language, $dtTranslation->description, $dtTranslation->url);
                    }

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

            return ['status' => 'ok'];

        } catch (InvalidArgsException $e) {
            \Log::error('*** Brand message export file error, messge: ' . $e->getMessage() . '***');
            DB::rollBack();

            return [
                'status' => 'fail',
                'message' => $e->getMessage()
            ];
        } catch (QueryException $e) {
            \Log::error('*** Brand message export file error, messge: ' . $e->getMessage() . '***');
            DB::rollBack();

            return [
                'status' => 'fail',
                'message' => $e->getMessage()
            ];
        } catch (Exception $e) {
            \Log::error('*** Brand message export file error, messge: ' . $e->getMessage() . '***');
            DB::rollBack();

            return [
                'status' => 'fail',
                'message' => $e->getMessage()
            ];
        }
    }
}
