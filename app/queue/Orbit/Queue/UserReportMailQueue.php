<?php namespace Orbit\Queue;
/**
 * Process queue for sending user email after registration. This email
 * contains activation link.
 *
 */
use Mail;
use Config;
use DB;
use Language;
use Lang;
use App;
use Log;
use Orbit\Helper\Util\JobBurier;
use Exception;

class UserReportMailQueue
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

            if (! empty($data['language'])) {
                App::setLocale($data['language']);
            }

            Log::info($data);
            $dataView = $data;

            $mailViews = array(
                            'html' => 'emails.user-report-html',
                            'text' => 'emails.user-report-text'
                            );

            $this->sendUserReportEmail($mailViews, $dataView);

            $message = sprintf('[Job ID: `%s`] User Report Mail; Status: Success;', $job->getJobId());
            Log::info($message);

            $job->delete();

            return [
                'status' => 'ok',
                'message' => $message
            ];
        } catch (Exception $e) {
            $message = sprintf('[Job ID: `%s`] User Report Mail; Status: FAIL; Code: %s; Message: %s',
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
    protected function sendUserReportEmail($mailviews, $data)
    {
        Mail::send($mailviews, $data, function($message) use ($data)
        {
            $emailconf = Config::get('orbit.generic_email.sender');
            $from = $emailconf['email'];
            $name = $emailconf['name'];
            $email = $data['user_email'];
            $subject = 'Gotomalls User Monthly Report';

            $message->from($from, $name);
            $message->subject($subject);
            $message->to($email);
        });
    }

}