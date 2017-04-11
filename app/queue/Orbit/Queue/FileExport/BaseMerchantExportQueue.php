<?php namespace Orbit\Queue\FileExport;
/**
 * Process queue for store synchronization
 * in merchant database manager app
 *
 * @author Shelgi Prasetyo <shelgi@dominopos.com>
 */
use PreExport;
use PostExport;
use Export;
use BaseMerchant;
use Config;
use DB;
use Carbon\Carbon as Carbon;
use Orbit\Database\ObjectID;
use User;
use Event;
use Helper\EloquentRecordCounter as RecordCounter;
use Queue;
use Orbit\FakeJob;
use Report\BrandInformationPrinterController;
use Report\BrandMessagePrinterController;

class BaseMerchantExportQueue
{
    protected $debug = FALSE;

    /**
     * Laravel main method to fire a job on a queue.
     *
     * @author Shelgi Prasetyo <shelgi@dominopos.com>
     * @param Job $job
     * @param array $data [sync_type => store, sync_data=>array(), user]
     */
    public function fire($job, $data)
    {
        try {
            $prefix = DB::getTablePrefix();
            $exportData = $data['export_data'];
            $userId = $data['user'];
            // $chunk = Config::get('orbit.mdm.synchronization.chunk', 50);

            $user = User::where('user_id', $userId)->firstOrFail();
            $processType = ['brand_information', 'brand_message'];
            $totalExport = count($exportData);

            // check pre export, if total row in pre_export table is more than equal in $totalExport no need to export, because we still have same data that already in exporting process
            $preExport = PreExport::leftJoin('exports', 'exports.export_id', '=', 'pre_exports.export_id')
                                    ->where('exports.export_type', 'merchant')
                                    ->where('exports.status', 'processing')
                                    ->whereIn('pre_exports.object_id', $exportData)
                                    ->groupBy('pre_exports.object_id')
                                    ->lists('pre_exports.object_id');

            $preExportCount = count($preExport);
            if ($preExportCount >= count($exportData)) {
                return FALSE;
            }

            DB::beginTransaction();

            $newExport = new Export;
            $newExport->user_id = $userId;
            $newExport->export_type = 'merchant';
            $newExport->total_export = count($exportData);
            $newExport->finished_export = 0;
            $newExport->status = 'processing';
            $newExport->save();

            DB::commit();

            $exportId = $newExport->export_id;

            DB::beginTransaction();

            // unset merchant_id and put into skippedMerchants if that merchant_id already exported by another process
            $skippedMerchants = array();
            foreach ($preExport as $pe) {
                if (($key = array_search($pe, $exportData)) !== false) {
                    $skippedMerchants[] = $exportData[$key];
                    unset($exportData[$key]);
                }
            }

            // insert table pre_exports
            $preExportData = array();
            foreach ($exportData as $pre) {
                foreach ($processType as $process) {
                    $preExportId = ObjectID::make();
                    $preExportData[] = [
                        "pre_export_id"       => $preExportId,
                        "export_id"           => $exportId,
                        "object_id"           => $pre,
                        "object_type"         => 'merchant',
                        "file_path"           => $process . '_' . $preExportId . '.csv',
                        "export_process_type" => $process,
                        "created_at"          => date("Y-m-d H:i:s"),
                        "updated_at"          => date("Y-m-d H:i:s")
                    ];

                    $message = sprintf('*** File Export, pre_export -- export_id: %s, object_type: %s; object_id: %s, export_process_type: %s, user_email: %s ***',
                                    $exportId,
                                    'merchant',
                                    $pre,
                                    $process,
                                    $user->user_email);
                    $this->debug($message . "\n");
                    \Log::info($message);
                }
            }

            if (! empty($preExportData)) {
                DB::table('pre_exports')->insert($preExportData);
            }

            DB::commit();

            $_GET['base_merchant_ids'] = $exportData;
            $_GET['export_id'] = $exportId;

            // export Brand Information
            $brandInformation = BrandInformationPrinterController::getBrandInformationPrintView();
            // export Brand Message
            $brandMessage = BrandMessagePrinterController::getBrandMessagePrintView();



        } catch (InvalidArgsException $e) {
            \Log::error('*** Store synchronization error, messge: ' . $e->getMessage() . '***');
            DB::rollBack();
        } catch (QueryException $e) {
            \Log::error('*** Store synchronization error, messge: ' . $e->getMessage() . '***');
            DB::rollBack();
        } catch (Exception $e) {
            \Log::error('*** Store synchronization error, messge: ' . $e->getMessage() . '***');
            DB::rollBack();
        }

        // Don't care if the job success or not we will provide user
        // another link to resend the activation
        $job->delete();
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }

    protected function updateMedia($type, $data, $store_id) {
        $path = public_path();

        $baseConfig = Config::get('orbit.upload.base_store');
        $retailerConfig = Config::get('orbit.upload.retailer');
        $images = array();

        foreach ($data as $dt) {
            $filename = $dt->file_name;
            switch ($type) {
                case 'logo':
                    $filename = $store_id . '-' . $dt->file_name;
                    $nameid = "retailer_logo";
                    break;

                case 'picture':
                    $nameid = "retailer_image";
                    break;

                case 'map':
                    $nameid = "retailer_map";
                    break;

                default:
                    $nameid = "";
                    break;
            }

            $sourceMediaPath = $path . DS . $baseConfig[$type]['path'] . DS . $dt->file_name;
            $destMediaPath = $path . DS . $retailerConfig[$type]['path'] . DS . $filename;
            $this->debug(sprintf("Starting to copy from: %s to %s\n", $sourceMediaPath, $destMediaPath));
            if (! @copy($sourceMediaPath, $destMediaPath)) {
                $this->debug("Failed to copy\n");
            }

            if ($dt->object_name === 'base_merchant') {
                $name_long = str_replace('base_merchant_', 'retailer_', $dt->media_name_long);
            } else {
                $name_long = str_replace('base_store_', 'retailer_', $dt->media_name_long);
                $name_long = str_replace('base_', 'retailer_', $name_long);
            }

            $newMedia = new Media;
            $newMedia->media_name_id = $nameid;
            $newMedia->media_name_long = $name_long;
            $newMedia->object_id = $store_id;
            $newMedia->object_name = 'retailer';
            $newMedia->file_name = $filename;
            $newMedia->file_extension = $dt->file_extension;
            $newMedia->file_size = $dt->file_size;
            $newMedia->mime_type = $dt->mime_type;
            $newMedia->path = $retailerConfig[$type]['path'] . DS . $filename;
            $newMedia->realpath = $destMediaPath;
            $newMedia->metadata = $dt->metadata;
            $newMedia->modified_by = $dt->modified_by;
            $newMedia->created_at = $dt->created_at;
            $newMedia->updated_at = $dt->updated_at;
            $newMedia->save();

            $images[] = $newMedia->realpath;
        }

        return $images;
    }

    protected function debug($message = '')
    {
        if ($this->debug) {
            echo $message;
        }
    }

    public function setDebug($value)
    {
        $this->debug = $value;
    }
}