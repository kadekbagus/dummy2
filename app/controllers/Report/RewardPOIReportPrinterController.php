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

class RewardPOIReportPrinterController
{
    /**
     * Static method to instantiate the object.
     *
     * Field :
     * ---------------------
     * SKU
     * POI Name
     * Address
     * Address 2
     * City
     * State
     * Country
     * Postcode
     * Latitude
     * Longitude
     * Telephone
     * AllDay Operation
     * Image 1 URL
     * Image 2 URL
     * Image 3 URL
     *
     * @param string $contentType
     * @return ControllerAPI
     */
    public static function create()
    {
        return new static;
    }
    public function postPrintRewardPOI()
    {
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
                    DB::raw("
                        (
                            select GROUP_CONCAT(IF({$prefix}merchants.object_type = 'tenant', CONCAT({$prefix}merchants.name,' at ', pm.name), CONCAT('Mall at ',{$prefix}merchants.name)) separator ', ')
                            from {$prefix}merchants
                            inner join {$prefix}merchants pm on {$prefix}merchants.parent_id = pm.merchant_id
                            where {$prefix}merchants.merchant_id = {$prefix}promotion_retailer.retailer_id
                        ) as poi_name
                    "),
                    DB::raw("IF({$prefix}merchants.object_type = 'mall', {$prefix}merchants.address_line1, omp.address_line1) as address"),
                    DB::raw("IF({$prefix}merchants.object_type = 'mall', '', {$prefix}merchants.unit) as unit"),
                    DB::raw("IF({$prefix}merchants.object_type = 'mall', '', {$prefix}merchants.floor) as floor"),
                    DB::raw("IF({$prefix}merchants.object_type = 'mall', {$prefix}merchants.city, omp.city) as city"),
                    DB::raw("IF({$prefix}merchants.object_type = 'mall', {$prefix}merchants.province, omp.province) as province"),
                    'countries.name as country',
                    DB::raw("IF({$prefix}merchants.object_type = 'mall', {$prefix}merchants.postal_code, omp.postal_code) as postal_code"),
                    DB::raw("x({$prefix}merchant_geofences.position) as latitude"),
                    DB::raw("y({$prefix}merchant_geofences.position) as longitude"),
                    DB::raw("IF({$prefix}merchants.object_type = 'mall', {$prefix}merchants.phone, omp.phone) as phone"),
                    //Country
                    DB::raw("
                            (SELECT {$image}
                            FROM {$prefix}media m
                            WHERE m.object_id = {$prefix}promotion_retailer.retailer_id
                            AND m.media_name_long = 'base_store_image_grab_orig'
                            AND m.metadata = 'order-0') AS image_1
                    "),
                    DB::raw("
                            (SELECT {$image}
                            FROM {$prefix}media m
                            WHERE m.object_id = {$prefix}promotion_retailer.retailer_id
                            AND m.media_name_long = 'base_store_image_grab_orig'
                            AND m.metadata = 'order-1') AS image_2
                    "),
                    DB::raw("
                            (SELECT {$image}
                            FROM {$prefix}media m
                            WHERE m.object_id = {$prefix}promotion_retailer.retailer_id
                            AND m.media_name_long = 'base_store_image_grab_orig'
                            AND m.metadata = 'order-2') AS image_3
                    ")
                )
                // Get POI Name
                ->leftJoin('promotion_retailer', 'promotion_retailer.promotion_id', '=', 'promotions.promotion_id')
                ->join('merchants', 'merchants.merchant_id', '=', 'promotion_retailer.retailer_id')
                ->leftJoin('merchants as omp', function ($q) {
                    $q->on(DB::raw('omp.merchant_id'), '=', 'merchants.parent_id');
                })
                // Get countries
                ->join('countries', 'countries.country_id' , '=' , DB::raw("IF({$prefix}merchants.object_type = 'mall', {$prefix}merchants.country_id, omp.country_id)"))
                //get geofences
                ->leftJoin('merchant_geofences', 'merchant_geofences.merchant_id', '=', DB::raw("IF({$prefix}merchants.object_type = 'mall', {$prefix}merchants.merchant_id, {$prefix}merchants.parent_id)"))
                ->whereIn('promotions.promotion_id', $couponIds)
                ->groupBy('promotion_retailer.retailer_id');

            $export->chunk($chunk, function($_export) use ($couponIds, $exportId, $exportType) {
                foreach ($_export as $dtExport) {
                    $dir = Config::get('orbit.export.output_dir', '');
                    $filePath = $dtExport->file_path;

                    if (! file_exists($dir)) {
                        mkdir($dir, 0777);
                    }

                    // Content csv
                    $content = array(
                                    array(
                                        $dtExport->sku,
                                        $dtExport->poi_name,
                                        $dtExport->address,
                                        $dtExport->unit . ' / ' . $dtExport->floor,
                                        $dtExport->city,
                                        $dtExport->province,
                                        $dtExport->country,
                                        $dtExport->postal_code,
                                        $dtExport->latitude,
                                        $dtExport->longitude,
                                        $dtExport->phone,
                                        '',
                                        'true',
                                        '',
                                        '',
                                        '',
                                        '',
                                        '',
                                        '',
                                        '',
                                        '',
                                        '',
                                        '',
                                        '',
                                        '',
                                        '',
                                        '',
                                        '',
                                        '',
                                        $dtExport->image_1,
                                        $dtExport->image_2,
                                        $dtExport->image_3,
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
            \Log::error('*** Reward POI export file error, messge: ' . $e->getMessage() . '***');
            DB::rollBack();

            return [
                'status' => 'fail',
                'message' => $e->getMessage()
            ];
        } catch (QueryException $e) {
            \Log::error('*** Reward POI export file error, messge: ' . $e->getMessage() . '***');
            DB::rollBack();

            return [
                'status' => 'fail',
                'message' => $e->getMessage()
            ];
        } catch (Exception $e) {
            \Log::error('*** Reward POI export file error, messge: ' . $e->getMessage() . '***');
            DB::rollBack();

            return [
                'status' => 'fail',
                'message' => $e->getMessage()
            ];
        }
    }

}