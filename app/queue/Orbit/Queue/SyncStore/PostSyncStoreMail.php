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

class PostSyncStoreMail
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
        $prefix = DB::getTablePrefix();

        $syncData = Sync::select('users.user_email',
                                'syncs.total_sync',
                                'syncs.finish_sync',
                                'syncs.created_at as sync_start_date',
                                'syncs.updated_at as sync_end_date')
                        ->leftJoin('users', 'users.user_id', '=', 'syncs.user_id')
                        ->where('sync_id', '=', $data['sync_id'])
                        ->first();

        // generate the subject based on config
        $subjectConfig = Config::get('orbit.sync.store.email.post_sync.subject');
        $subject = str_replace('{{SYNC_ID}}', $data['sync_id'], $subjectConfig);
        $subject = str_replace('{{USER_EMAIL}}', $syncData->user_email, $subject);

        $date = strtotime($syncData->sync_start_date);
        $syncStartDate = date('d-m-y H:i:s', $date);

        $date = strtotime($syncData->sync_end_date);
        $syncEndDate = date('d-m-y H:i:s', $date);

        // data send to the mail view
        $dataView['subject'] = $subject;
        $dataView['userEmail'] = $syncData->user_email;
        $dataView['syncId'] = $data['sync_id'];
        $dataView['syncStartDate'] = $syncStartDate;
        $dataView['syncEndDate'] = $syncEndDate;
        $dataView['totalSync'] = $syncData->total_sync;
        $dataView['finishSync'] = $syncData->finish_sync;

        $mailViews = array(
                    'html' => 'emails.sync-store.post-sync-store-html',
                    'text' => 'emails.sync-store.post-sync-store-text'
        );

        $this->sendPreSyncStoreEmail($mailViews, $dataView);

        // Don't care if the job success or not we will provide user
        // another link to resend the activation
        $job->delete();
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
            $emailConf = Config::get('orbit.generic_email.sender.sender');
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