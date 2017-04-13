<?php namespace Orbit\Queue\FileExport;
/**
 * Process queue for export reward to scv
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
use Coupon;
use Orbit\FakeJob;
use Report\RewardInformationReportPrinterController;
use Report\RewardMessagingReportPrinterController;
use Report\RewardPOIMessageReportPrinterController;
use Report\RewardPOIReportPrinterController;
use Report\RewardPostIntegrationReportPrinterController;
use Report\RewardUniqueRedemptionCodeReportPrinterController;
use Mail;
use Orbit\Helper\Util\JobBurier;

class RewardExportQueue
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
            // $processType = ['reward_information', 'reward_message', 'reward_poi_message', 'reward_poi', 'reward_unique_redemption_code', 'reward_post_integration'];
            $processType = ['reward_information', 'reward_message', 'reward_poi_message', 'reward_poi', 'reward_unique_redemption_code'];
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
            if ($preExportCount >= $totalExport) {
                // Bury the job for later inspection
                JobBurier::create($job, function($theJob) {
                    // The queue driver does not support bury.
                    $theJob->delete();
                })->bury();

                $message = sprintf('[Job ID: `%s`] Export Reward file csv; Status: fail; Message: All file still in progress;',
                                $job->getJobId());
                \Log::error($message);

                return [
                    'status' => 'fail',
                    'message' => $message
                ];
            }

            DB::beginTransaction();

            $newExport = new Export;
            $newExport->user_id = $userId;
            $newExport->export_type = 'coupon';
            $newExport->total_export = $totalExport;
            $newExport->finished_export = 0;
            $newExport->status = 'processing';
            $newExport->save();

            DB::commit();

            $exportId = $newExport->export_id;

            DB::beginTransaction();

            // unset merchant_id and put into skippedCoupons if that merchant_id already exported by another process
            $skippedCoupons = array();
            foreach ($preExport as $pe) {
                if (($key = array_search($pe, $exportData)) !== false) {
                    $skippedCoupons[] = $exportData[$key];
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

            $exportDataView['subject']     = Config::get('orbit.export.email.reward.pre_export_subject');
            $exportDataView['userEmail']   = $user->user_email;
            $exportDataView['exportDate']  = $exportDate;
            $exportDataView['exportId']    = $exportId;
            $exportDataView['totalExport'] = $totalExport;
            $exportDataView['coupons']     = Coupon::whereIn('promotion_id', $listAllExportData)->lists('promotion_name');

            $preExportMailViews = array(
                                'html' => 'emails.file-export.pre-reward-export-html',
                                'text' => 'emails.file-export.pre-reward-export-text'
            );

            $this->sendMail($preExportMailViews, $exportDataView);

            $_POST['coupon_ids'] = $exportData;
            $_POST['export_id'] = $exportId;

            // export Reward Information
            $rewardInformation = RewardInformationReportPrinterController::create()->postPrintRewardInformation();
            if ($rewardInformation['status'] === 'fail') {
                $this->failedJob($job, $exportId, $rewardInformation['message']);
            }

            // export Reward Messaging Report
            $rewardMessaging = RewardMessagingReportPrinterController::create()->postPrintRewardMessaging();
            if ($rewardMessaging['status'] === 'fail') {
                $this->failedJob($job, $exportId, $rewardMessaging['message']);
            }

            // export Reward POI Message
            $rewardPOIMessage = RewardPOIMessageReportPrinterController::create()->postPrintRewardPOIMessage();
            if ($rewardPOIMessage['status'] === 'fail') {
                $this->failedJob($job, $exportId, $rewardPOIMessage['message']);
            }

            // export Reward POI Report
            $rewardPOI = RewardPOIReportPrinterController::create()->postPrintRewardPOI();
            if ($rewardPOI['status'] === 'fail') {
                $this->failedJob($job, $exportId, $rewardPOI['message']);
            }

            // // export Reward Unique Redemtion Code
            $rewardUniqueRedemtionCode = RewardUniqueRedemptionCodeReportPrinterController::create()->postPrintRewardUniqueRedemptionCode();
            if ($rewardUniqueRedemtionCode['status'] === 'fail') {
                $this->failedJob($job, $exportId, $rewardUniqueRedemtionCode['message']);
            }

            // join and rename file before send as email attachmend
            // get file name based on merchant name from link to tenant
            $tenanList = PostExport::join('promotions', 'promotions.promotion_id', '=', 'post_exports.object_id')
                                     ->leftJoin('promotion_retailer', 'promotion_retailer.promotion_id', '=', 'promotions.promotion_id')
                                     ->leftJoin('merchants', 'merchants.merchant_id', '=', 'promotion_retailer.retailer_id')
                                     ->where('post_exports.export_id', $exportId)
                                     ->groupBy('merchants.name')
                                     ->orderBy('merchants.name', 'asc')
                                     ->lists('mrechants.name');

            $tenantName = implode('_', $tenanList);
            $name = preg_replace('/[^a-zA-Z0-9_]/', '', str_replace(" ", "_", $tenantName));

            $postExport = PostExport::select('post_exports.file_path', 'post_exports.export_process_type')
                                    ->join('promotions', 'promotions.promotion_id', '=', 'post_exports.object_id')
                                    ->where('post_exports.export_id', $exportId)
                                    ->where('post_exports.object_type', 'coupon');

            $groupFile = array();
            $postExport->chunk($chunk, function($_postExport) use (&$groupFile) {
                foreach ($_postExport as $pe) {
                    if ($pe->export_process_type === 'reward_information') {
                        $groupFile['reward_information'][] = $pe->file_path;
                    } elseif ($pe->export_process_type === 'reward_message') {
                        $groupFile['reward_message'][] = $pe->file_path;
                    } elseif ($pe->export_process_type === 'reward_poi_message') {
                        $groupFile['reward_poi_message'][] = $pe->file_path;
                    } elseif ($pe->export_process_type === 'reward_poi') {
                        $groupFile['reward_poi'][] = $pe->file_path;
                    } elseif ($pe->export_process_type === 'reward_unique_redemption_code') {
                        $groupFile['reward_unique_redemption_code'][] = $pe->file_path;
                    }
                }
            });

            // join file
            $exportFiles = array();
            $dir = Config::get('orbit.export.output_dir', '');
            foreach ($processType as $pt) {
                $newJoinFile = 'file_join_' . $pt . '_' . $exportId . '.csv';

                if (! empty($groupFile[$pt])) {
                    $afterJoin = $this->joinFiles($groupFile[$pt], $newJoinFile);

                    if (! $afterJoin) {
                        $this->failedJob($job, $exportId, 'Failed joining files: ' . $pt);
                    }

                    $fileName = '';
                    if ($pt === 'reward_information') {
                        $fileName = 'Gotomalls_' . $name . '_Reward.csv';
                    } elseif ($pt === 'reward_message') {
                        $fileName = 'Gotomalls_' . $name . '_Reward_Msg.csv';
                    } elseif ($pt === 'reward_poi_message') {
                        $fileName = 'Gotomalls_' . $name . '_Reward_POI_Msg.csv';
                    } elseif ($pt === 'reward_poi') {
                        $fileName = 'Gotomalls_' . $name . '_Reward_POI.csv';
                    } elseif ($pt === 'reward_unique_redemption_code') {
                        $fileName = 'Gotomalls_' . $name . '_Reward_Post_Integration.csv';
                    }

                    $exportFiles[] = array('file_path' => $dir . $newJoinFile, 'name' => $fileName);
                }
            }

            $exportDataView['subject']       = Config::get('orbit.export.email.reward.post_export_subject');
            $exportDataView['coupons']       = Coupon::whereIn('promotion_id', $exportData)->lists('promotion_name');
            $exportDataView['attachment']    = $exportFiles;
            $exportDataView['skippedCoupons'] = array();
            if (! empty($skippedCoupons)) {
                $exportDataView['skippedCoupons'] = Coupon::whereIn('promotion_id', $skippedCoupons)->lists('promotion_name');
            }

            $postExportMailViews = array(
                                'html' => 'emails.file-export.post-reward-export-html',
                                'text' => 'emails.file-export.post-reward-export-text'
            );

            $this->sendMail($postExportMailViews, $exportDataView);

            // Safely delete the object
            $job->delete();

            $message = sprintf('[Job ID: `%s`] Export reward file csv; Status: OK; Export ID: %s;',
                                $job->getJobId(),
                                $exportId);

            $this->debug($message . "\n");
            \Log::info($message);

            return [
                'status' => 'ok',
                'message' => $message
            ];

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

            $email = Config::get('orbit.export.email.reward.to');

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

        $message = sprintf('[Job ID: `%s`] Export reward file csv; Status: FAIL; Export ID: %s;',
                            $job->getJobId(),
                            $exportId);
        \Log::error($message);
    }

    protected function joinFiles(array $files, $result) {
        if (empty($files)) {
            return false;
        }

        if (!is_array($files)) {
            return false;
        }

        $dir = Config::get('orbit.export.output_dir', '');
        if (! file_exists($dir)) {
            mkdir($dir, 0777, true);
        }

        $newFile = fopen($dir . $result, "w");

        foreach($files as $file) {
            $oldFile = fopen($dir . $file, "r");

            while (!feof($oldFile)) {
                fwrite($newFile, fgets($oldFile));
            }

            fclose($oldFile);
            unset($oldFile);
        }

        fclose($newFile);
        unset($newFile);
        return true;
    }
}