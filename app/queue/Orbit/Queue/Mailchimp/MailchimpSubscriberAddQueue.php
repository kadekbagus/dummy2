<?php namespace Orbit\Queue\Mailchimp;
/**
 * Queue to add subscriber email to the Mailchimp's list.
 *
 * @author Rio Astamal <rio@dominopos.com>
 */
use Log;
use Activity;
use User;
use Config;
use Orbit\Mailchimp\MailchimpFactory;
use Orbit\Helper\Util\JobBurier;

class MailchimpSubscriberAddQueue
{
    /**
     * Laravel main method to fire a job on a queue.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param Job $job
     * @param array $data [
     *                      activity_id => NUM
     * ]
     * @return void
     * @todo Make this testable
     */
    public function fire($job, $data)
    {
        $activityId = $data['activity_id'];

        try {
            $activity = Activity::where('activity_id', $activityId)->firstOrFail();
            $user = User::where('user_id', $activity->user_id)->firstOrFail();

            $mailchimpDriver = Config::get('orbit.mailchimp.driver');
            $mailchimpConfig = Config::get('orbit.mailchimp.drivers.' . $mailchimpDriver);
            $listId = $mailchimpConfig['list_id'];
            Log::info('MAILCHIMP QUEUE -- Add Subscriber -- Using driver: ' . $mailchimpDriver);

            $mailchimp = MailchimpFactory::create($mailchimpConfig, $mailchimpDriver)->getInstance();
            $mailchimp->postMembers($listId, [
                'email' => $user->user_email,
                'first_name' => $user->user_firstname,
                'last_name' => $user->user_lastname
            ]);

            $message = 'Subscriber successfully added to the mailchimp';
            $message = sprintf('[Job ID: `%s`] MAILCHIMP QUEUE -- Add Subscriber: %s -- Status: OK -- Message: %s',
                                $job->getJobId(), $user->user_email, $message);

            $job->delete();

            Log::info($message);

            return ['status' => 'ok', 'message' => $message];
        } catch (CurlWrapperCurlException $e) {
            $message = sprintf('[Job ID: `%s`] MAILCHIMP QUEUE -- Add Subscriber: %s -- Status: FAIL -- Message: %s',
                                $job->getJobId(), $user->user_email, $e->getMessage());
        } catch (Exception $e) {
            $message = sprintf('[Job ID: `%s`] MAILCHIMP QUEUE -- Add Subscriber: %s -- Status: FAIL -- Message: %s',
                                $job->getJobId(), $user->user_email, $e->getMessage());
        }

        // Bury the job for later inspection
        JobBurier::create($job, function($theJob) {
            // The queue driver does not support bury.
            $theJob->delete();
        })->bury();

        Log::info($message);
        return ['status' => 'fail', 'message' => $message];
    }
}