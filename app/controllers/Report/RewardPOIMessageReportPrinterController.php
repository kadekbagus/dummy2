<?php namespace Report;

use Report\DataPrinterController;
use Config;
use DB;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Orbit\Text as OrbitText;
use Response;
use Coupon;
use PreExport;
use PostExport;
use Export;

class RewardPOIMessageReportPrinterController
{
    /*
        Field :
        ---------------------
        POI Name
        Locale
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

    public function postPrintRewardPOIMessage()
    {
        try {
            $couponIds = OrbitInput::post('coupon_ids');
            $exportId = OrbitInput::post('export_id');
            $exportType = 'reward_poi_message';
            $chunk = Config::get('orbit.export.chunk', 50);

            $prefix = DB::getTablePrefix();

            $export = Coupon::select('promotions.promotion_id', 'pre_exports.file_path')
                                ->leftJoin('pre_exports', function ($q){
                                        $q->on('pre_exports.object_id', '=', 'promotions.promotion_id')
                                          ->on('pre_exports.object_type', '=', DB::raw("'coupon'"));
                                })
                                ->where('pre_exports.export_id', $exportId)
                                ->where('pre_exports.export_process_type', $exportType)
                                ->whereIn('promotions.promotion_id', $couponIds)
                                ->groupBy('promotions.promotion_id');

            $export->chunk($chunk, function($_export) use ($couponIds, $exportId, $exportType, $prefix) {
                foreach ($_export as $dtExport) {
                    $dir = Config::get('orbit.export.output_dir', '');
                    $filePath = $dtExport->file_path;

                    if (! file_exists($dir)) {
                        mkdir($dir, 0777);
                    }

                    $couponData = Coupon::select('campaign_account.mobile_default_language as locale', 'merchants.name as poi_name')
                                        ->join('campaign_account', 'campaign_account.user_id', '=', 'promotions.created_by')
                                        ->leftJoin('promotion_retailer', 'promotion_retailer.promotion_id', '=', 'promotions.promotion_id')
                                        ->leftJoin('merchants', 'merchants.merchant_id', '=', 'promotion_retailer.retailer_id')
                                        ->where('promotions.promotion_id', $dtExport->promotion_id)
                                        ->get();

                    $content = array();
                    foreach ($couponData as $coupon) {
                        $content[] = array(
                                        $coupon->poi_name,
                                        $coupon->locale,
                                        '',
                                        '',
                                        '',
                                        '',
                                        '',
                                        '',
                                        '',
                                        '',
                                        '',
                                        ''
                                    );
                        DB::beginTransaction();

                        $checkPre = PreExport::where('export_id',$exportId)
                                            ->where('object_type', 'coupon')
                                            ->where('object_id', $dtExport->promotion_id);

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

                    $csv_handler = fopen($dir . $filePath, 'w');

                    foreach ($content as $fields) {
                        fputcsv($csv_handler, $fields);
                    }

                    fclose($csv_handler);
                }
            });

            return ['status' => 'ok'];

        } catch (InvalidArgsException $e) {
            \Log::error('*** Reward POI Message export file error, messge: ' . $e->getMessage() . '***');
            DB::rollBack();

            return [
                'status' => 'fail',
                'message' => $e->getMessage()
            ];
        } catch (QueryException $e) {
            \Log::error('*** Reward POI Message export file error, messge: ' . $e->getMessage() . '***');
            DB::rollBack();

            return [
                'status' => 'fail',
                'message' => $e->getMessage()
            ];
        } catch (Exception $e) {
            \Log::error('*** Reward POI Message export file error, messge: ' . $e->getMessage() . '***');
            DB::rollBack();

            return [
                'status' => 'fail',
                'message' => $e->getMessage()
            ];
        }
    }

}