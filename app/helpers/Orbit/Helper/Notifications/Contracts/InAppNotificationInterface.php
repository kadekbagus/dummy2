<?php namespace Orbit\Helper\Notifications\Contracts;

/**
 * Contract for notification via app/web.
 */
interface InAppNotificationInterface 
{
    /**
     * Get in app data.
     * 
     * @return array
     */
    protected function getInAppData();

    /**
     * We need $job and $data because this method 
     * will act as a custom Queue handler.
     *
     * @todo  rename toWeb() to toApp()
     * 
     * @param  Illuminate\Queue\Job $job  [description]
     * @param  array $data [description]
     * @return void
     */
    public function toWeb($job, $data);

}
