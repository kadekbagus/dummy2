<?php namespace Orbit\Helper\Notifications;

/**
 * Notifiable trait that indicate the using object/model can be notified.
 * 
 * @author Budi <budi@dominopos.com>
 */
trait Notifiable {

    /**
     * Notify the notifiable object.
     * 
     * @param  [type] $notificationClass [description]
     * @return [type]                    [description]
     */
    public function notify($notificationClass)
    {
        // $this refer to the object/model that will be notified.
        $notificationClass->setNotifiable($this)->send();
    }
}