<?php namespace Orbit\Helper\Activity;

/**
 * A trait that allow an object/instance to record an activity.
 *
 * @author Budi <budi@gotomalls.com>
 */
trait HasActivity {

    /**
     * A method that helps current instance to record
     * activity.
     *
     * @param  [type] $activityClass  [description]
     * @param  array  $additionalData [description]
     * @return [type]                 [description]
     */
    public function record($activityClass, $additionalData = [])
    {
        $activityClass->setSubject($this)->record();
    }
}
