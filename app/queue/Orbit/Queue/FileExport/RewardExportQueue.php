<?php namespace Orbit\Queue\FileExport;
/**
 * Process queue for export reward to scv
 *
 * @author Firmansyah <firmansyah@dominopos.com>
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

class RewardExportQueue
{
    protected $debug = FALSE;

    /**
     * Laravel main method to fire a job on a queue.
     *
     * @author Firmansyah <firmansyah@dominopos.com>
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
            $listAllExportData = $exportData;

            // check pre export, if total row in pre_export table is more than equal in $totalExport no need to export, because we still have same data that already in exporting process
            $preExport = PreExport::leftJoin('exports', 'exports.export_id', '=', 'pre_exports.export_id')
                                    ->where('exports.export_type', 'coupon')
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
            $newExport->export_type = 'coupon';
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
                        "object_type"         => 'coupon',
                        "file_path"           => $process . '_' . $preExportId . '.csv',
                        "export_process_type" => $process,
                        "created_at"          => date("Y-m-d H:i:s"),
                        "updated_at"          => date("Y-m-d H:i:s")
                    ];

                    $message = sprintf('*** File Export, pre_export -- export_id: %s, object_type: %s; object_id: %s, export_process_type: %s, user_email: %s ***',
                                    $exportId,
                                    'coupon',
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

            // send pre export email
            $exportDate = date('d-m-y H:i:s', strtotime($newExport->created_at));

            $exportDataView['subject']     = Config::get('orbit.export.email.brand.pre_export_subject');
            $exportDataView['userEmail']   = $user->user_email;
            $exportDataView['exportDate']  = $exportDate;
            $exportDataView['exportId']    = $exportId;
            $exportDataView['totalExport'] = $totalExport;
            $exportDataView['merchants']   = BaseMerchant::whereIn('base_merchant_id', $listAllExportData)->lists('name');

            $preExportMailViews = array(
                                'html' => 'emails.file-export.pre-brand-export-html',
                                'text' => 'emails.file-export.pre-brand-export-text'
            );

            $this->sendMail($preExportMailViews, $exportDataView);



            $_POST['coupons_ids'] = $exportData;
            $_POST['export_id'] = $exportId;

            // export Reward Information
            $rewardInformation = RewardInformationReportPrinterController::postPrintRewardInformation();
            // export Reward Messaging Report
            $rewardMessaging = RewardMessagingReportPrinterController::postPrintRewardMessaging();
            // export Reward POI Message
            $rewardPOIMessage = RewardPOIMessageReportPrinterController::postPrintRewardPOIMessage();
            // export Reward POI Report
            $rewardPOI = RewardPOIReportPrinterController::postPrintRewardPOI();
            // export Reward Unique Redemtion Code
            $rewardUniqueRedemtionCode = RewardUniqueRedemtionCodeReportPrinterController::getPrintRewardUniqueRedemtionCode();

        } catch (InvalidArgsException $e) {
            \Log::error('*** Reward export error, messge: ' . $e->getMessage() . '***');
            DB::rollBack();
        } catch (QueryException $e) {
            \Log::error('*** Reward export error, messge: ' . $e->getMessage() . '***');
            DB::rollBack();
        } catch (Exception $e) {
            \Log::error('*** Reward export error, messge: ' . $e->getMessage() . '***');
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