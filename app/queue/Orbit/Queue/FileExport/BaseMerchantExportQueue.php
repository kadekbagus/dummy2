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
use Mail;
use Orbit\Helper\Util\JobBurier;

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
            $chunk = Config::get('orbit.export.chunk', 50);

            $user = User::where('user_id', $userId)->firstOrFail();
            $processType = ['brand_information', 'brand_message'];
            $totalExport = count($exportData);
            $listAllExportData = $exportData;

            // check pre export, if total row in pre_export table is more than equal in $totalExport no need to export, because we still have same data that already in exporting process
            $preExport = PreExport::leftJoin('exports', 'exports.export_id', '=', 'pre_exports.export_id')
                                    ->where('exports.export_type', 'merchant')
                                    ->where('exports.status', 'processing')
                                    ->whereIn('pre_exports.object_id', $exportData)
                                    ->groupBy('pre_exports.object_id')
                                    ->lists('pre_exports.object_id');

            $preExportCount = count($preExport);
            if ($preExportCount >= $totalExport) {
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

            // main process
            $_GET['base_merchant_ids'] = $exportData;
            $_GET['export_id'] = $exportId;

            // export Brand Information
            $brandInformation = BrandInformationPrinterController::getBrandInformationPrintView();
            if ($brandInformation['status'] === 'fail') {
                $this->failedJob($job, $exportId, $brandInformation['message']);
            }

            // export Brand Message
            $brandMessage = BrandMessagePrinterController::getBrandMessagePrintView();
            if ($brandMessage['status'] === 'fail') {
                $this->failedJob($job, $exportId, $brandMessage['message']);
            }

            // rename attachment file
            $postExport = PostExport::select('post_exports.file_path', 'post_exports.export_process_type', 'base_merchants.name')
                                    ->leftJoin('base_merchants', 'base_merchants.base_merchant_id', '=', 'post_exports.object_id')
                                    ->where('post_exports.export_id', $exportId)
                                    ->where('post_exports.object_type', 'merchant');
            $dir = Config::get('orbit.export.output_dir', '');

            $exportFiles = array();
            $postExport->chunk($chunk, function($_postExport) use ($exportId, $exportType, $dir, &$exportFiles) {
                foreach ($_postExport as $pe) {
                    $fileName = 'Gotomalls_' . str_replace(" ", "_", $pe->name) . '_Brand.csv';
                    if ($pe->export_process_type === 'brand_message') {
                        $fileName = 'Gotomalls_' . $pe->name . '_Brand_Msg.csv';
                    }
                    $exportFiles[] = array('file_path' => $dir . $pe->file_path, 'name' => $fileName);
                }
            });

            $exportDataView['subject']          = Config::get('orbit.export.email.brand.post_export_subject');
            $exportDataView['merchants']        = BaseMerchant::whereIn('base_merchant_id', $exportData)->lists('name');
            $exportDataView['attachment']       = $exportFiles;
            $exportDataView['skippedMerchants'] = array();
            if (! empty($skippedMerchants)) {
                $exportDataView['skippedMerchants'] = BaseMerchant::whereIn('base_merchant_id', $skippedMerchants)->lists('name');
            }

            $postExportMailViews = array(
                                'html' => 'emails.file-export.post-brand-export-html',
                                'text' => 'emails.file-export.post-brand-export-text'
            );

            $this->sendMail($postExportMailViews, $exportDataView);

            // Safely delete the object
            $job->delete();

            $message = sprintf('[Job ID: `%s`] Export Brand file csv; Status: OK; Export ID: %s;',
                                $job->getJobId(),
                                $exportId)

            $this->debug($message . "\n");
            \Log::info($message);

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

        // Bury the job for later inspection
        JobBurier::create($job, function($theJob) {
            // The queue driver does not support bury.
            $theJob->delete();
        })->bury();

       $message = sprintf('[Job ID: `%s`] Export Brand file csv; Status: FAIL; Export ID: %s;',
                            $job->getJobId(),
                            $exportId);
        \Log::error($message);
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }

    /**
     * Common routine for sending email.
     *
     * @param array $data
     * @return void
     */
    protected function sendMail($mailviews, $data)
    {

        Mail::send($mailviews, $data, function($message) use ($data)
        {
            $emailConf = Config::get('orbit.generic_email.sender');
            $from = $emailConf['email'];
            $name = $emailConf['name'];

            $email = Config::get('orbit.export.email.brand.to');

            $subject = $data['subject'];

            $message->from($from, $name);
            $message->subject($subject);
            $message->to($email);

            if (! empty($data['attachment'])) {
                foreach ($data['attachment'] as $file) {
                    $message->attach($file['file_path'], array('as' => $file['name']));
                }
            }
        });
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

    protected function failedJob($job, $exportId, $message) {
        // Bury the job for later inspection
        JobBurier::create($job, function($theJob) {
            // The queue driver does not support bury.
            $theJob->delete();
        })->bury();

        $message = sprintf('[Job ID: `%s`] Export Brand file csv; Status: FAIL; Export ID: %s;',
                            $job->getJobId(),
                            $exportId);
        \Log::error($message);
    }
}