<?php namespace Report;

use Report\DataPrinterController;
use Config;
use DB;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Orbit\Text as OrbitText;
use Response;
use Coupon;
use IssuedCoupon;
use PreExport;
use PostExport;
use Export;

class RewardUniqueRedemptionCodeReportPrinterController
{
    /**
     * Static method to instantiate the object.
     *
     * Field :
     * ---------------------
     * SKU
     * Code
     * @param string $contentType
     * @return ControllerAPI
     */
    public static function create()
    {
        return new static;
    }

    public function postPrintRewardUniqueRedemptionCode()
    {
        try {
            $couponIds = OrbitInput::post('coupon_ids');
            $exportId = OrbitInput::post('export_id');
            $exportType = 'reward_unique_redemption_code';
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

            $export->chunk($chunk, function($_export) use ($couponIds, $exportId, $exportType, $chunk, $take, $skip) {
                foreach ($_export as $dtExport) {
                    $dir = Config::get('orbit.export.output_dir', '');
                    $filePath = $dtExport->file_path;

                    if (! file_exists($dir)) {
                        mkdir($dir, 0777);
                    }

                    $content = array();

                    $take = 50;
                    $skip = 0;

                    do {
                        $couponData = IssuedCoupon::select(
                                'promotion_id as sku',
                                'issued_coupon_code as code'
                            )
                            ->where('promotion_id', $dtExport->promotion_id)
                            ->take($take)
                            ->skip($skip)
                            ->get();

                        $skip = $take + $skip;

                        foreach ($couponData as $coupon) {
                            $content[] = array(
                                            $coupon->sku,
                                            $coupon->code
                                        );

                            DB::beginTransaction();

                            $checkPre = PreExport::where('export_id', $exportId)
                                                ->where('object_type', 'coupon')
                                                ->where('object_id', $dtExport->promotion_id);

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
                    } while (! is_object($couponData));

                    $csv_handler = fopen($dir . $filePath, 'w');

                    foreach ($content as $fields) {
                        fputcsv($csv_handler, $fields);
                    }

                    fclose($csv_handler);
                }
            });

            return ['status' => 'ok'];

        } catch (InvalidArgsException $e) {
            \Log::error('*** Reward unique redemption code export file error, messge: ' . $e->getMessage() . '***');
            DB::rollBack();

            return [
                'status' => 'fail',
                'message' => $e->getMessage()
            ];
        } catch (QueryException $e) {
            \Log::error('*** Reward unique redemption code export file error, messge: ' . $e->getMessage() . '***');
            DB::rollBack();

            return [
                'status' => 'fail',
                'message' => $e->getMessage()
            ];
        } catch (Exception $e) {
            \Log::error('*** Reward unique redemption code export file error, messge: ' . $e->getMessage() . '***');
            DB::rollBack();

            return [
                'status' => 'fail',
                'message' => $e->getMessage()
            ];
        }
    }
}