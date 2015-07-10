<?php namespace Orbit\Queue\Notifier;
/**
 * Process queue for notifying that there is event login happens.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use User;
use Mail;
use Config;

class UserLoginNotifier
{
    /**
     * Laravel main method to fire a job on a queue.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param Job $job
     * @param array $data [user_id => NUM]
     */
    public function fire($job, $data)
    {
        $userId = $data['user_id'];
        $user = User::excludeDeleted()->find($userId);

        // @To do
        // Implement external system notifier.
        // Please use this lightweight library for doing HTTP POST
        // https://github.com/svyatov/CurlWrapper
        // If the response was ok then delete the job
        //    $job->delete();
    }
}