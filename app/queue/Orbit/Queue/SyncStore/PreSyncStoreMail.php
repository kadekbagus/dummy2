<?php namespace Orbit\Queue\SyncStore;
/**
 * Process queue for sending user email after registration. This email
 * contains activation link.
 *
 */
use Sync;
use Mail;
use Config;
use DB;
use Log;
use Orbit\Helper\Util\JobBurier;
use Exception;

class PreSyncStoreMail
{
    /**
     * Laravel main method to fire a job on a queue.
     *
     * @author kadek <kadek@dominopos.com>
     * @param Job $job
     * @param array $data [user_id => NUM]
     */
    public function fire($job, $data)
    {
        try {
            $prefix = DB::getTablePrefix();

            $syncData = Sync::select('users.user_email', 'syncs.total_sync', 'syncs.created_at as sync_start_date')
                            ->leftJoin('users', 'users.user_id', '=', 'syncs.user_id')
                            ->where('sync_id', '=', $data['sync_id'])
                            ->first();

            // generate the subject based on config
            $subjectConfig = Config::get('orbit.sync.store.email.pre_sync.subject');
            $subject = str_replace('{{SYNC_ID}}', $data['sync_id'], $subjectConfig);
            $subject = str_replace('{{USER_EMAIL}}', $syncData->user_email, $subject);

            $date = strtotime($syncData->sync_start_date);
            $syncStartDate = date('d-m-y H:i:s', $date);

            // data send to the mail view
            $dataView['subject'] = $subject;
            $dataView['userEmail'] = $syncData->user_email;
            $dataView['syncId'] = $data['sync_id'];
            $dataView['syncDate'] = $syncStartDate;
            $dataView['totalSync'] = $syncData->total_sync;

            $mailViews = array(
                        'html' => 'emails.sync-store.pre-sync-store-html',
                        'text' => 'emails.sync-store.pre-sync-store-text'
            );

            $this->sendPreSyncStoreEmail($mailViews, $dataView);

            $message = sprintf('[Job ID: `%s`] Pre Sync Store Mail; Status: Success;', $job->getJobId());
            Log::info($message);

            $job->delete();

            return [
                'status' => 'ok',
                'message' => $message
            ];
        } catch (Exception $e) {
            $message = sprintf('[Job ID: `%s`] Pre Sync Store Mail; Status: FAIL; Code: %s; Message: %s',
                    $job->getJobId(),
                    $e->getCode(),
                    $e->getMessage());
            Log::info($message);
        }

        // Bury the job for later inspection
        JobBurier::create($job, function($theJob) {
            // The queue driver does not support bury.
            $theJob->delete();
        })->bury();

        return [
            'status' => 'fail',
            'message' => $message
        ];
    }

    /**
     * Common routine for sending email.
     *
     * @param array $data
     * @return void
     */
    protected function sendPreSyncStoreEmail($mailviews, $data)
    {

        Mail::send($mailviews, $data, function($message) use ($data)
        {
            $emailConf = Config::get('orbit.generic_email.sender');
            $from = $emailConf['email'];
            $name = $emailConf['name'];

            $email = Config::get('orbit.sync.store.email.to');

            $subject = $data['subject'];

            $message->from($from, $name);
            $message->subject($subject);
            $message->to($email);
        });
    }

}