<?php namespace Orbit\Helper\Activity;

use Activity;
use Exception;

/**
 * Activity Helper Class.
 *
 * A class that will simplify how controllers record activities. Simplifying means
 * making controllers as clean as possible by moving other common routines into
 * their own classes.
 *
 * Most of the time, we would write code like this on the controllers:
 * Activity::mobileci()
 *          ->setActivityType('transaction')
 *          ->setUser($payment_update->user)
 *          ->setActivityName('transaction_status')
 *          ->setActivityNameLong('Transaction is Failed')
 *          ->setModuleName('Midtrans Transaction')
 *          ->setObject($payment_update)
 *          ->setNotes('Transaction is failed from Midtrans/Customer.')
 *          ->setLocation($mall)
 *          ->responseFailed()
 *          ->save();
 *
 * By utilizing this helper, we could do:
 * $user->activity(new TransactionFailedActivity($payment_update, ['location' => $mall]));
 *
 * @author Budi <budi@gotomalls.com>
 */
class OrbitActivity
{
    protected $activityModel;

    protected $scope = 'mobileCI';

    protected $responseSuccess = true;

    protected $activityData = [];

    protected $activityMapFunction = [
        'location' => 'setLocation',
        'object' => 'setObject',
        'objectDisplayName' => 'setObjectDisplayName',
        'moduleName' => 'setModuleName',
        'notes' => 'setNotes',
        'activityType' => 'setActivityType',
        'activityName' => 'setActivityName',
        'activityNameLong' => 'setActivityNameLong',
        'subject' => 'setUser',
        'currentUrl' => 'setCurrentUrl',
    ];

    function __construct($subject = null, $object = null, $additionalData = [])
    {
        $this->activityModel = Activity::{$this->scope}();

        if (! empty($subject)) {
            $this->activityModel->setUser($subject);
        }

        if (! empty($object)) {
            $this->activityModel->setObject($object);
        }

        if (! empty($additionalData)) {
            $this->mergeActivityData($additionalData);
        }
    }

    /**
     * Set the subject/user of the activity.
     *
     * @param [type] $subject [description]
     * @return  self
     */
    public function setSubject($subject = null)
    {
        if (empty($subject)) {
            throw new Exception('Empty subject!');
        }

        $this->activityModel->setUser($subject);

        return $this;
    }

    /**
     * Set the object of the activity.
     *
     * @param [type] $object [description]
     * @return  self
     */
    public function setObject($object = null)
    {
        if (empty($object)) {
            throw new Exception("Empty object");
        }

        $this->activityModel->setObject($object);

        return $this;
    }

    /**
     * Record the Activity!
     *
     * @return void
     */
    public function record()
    {
        // Add additional data if needed.
        $this->mergeActivityData($this->getAdditionalActivityData());

        foreach($this->activityData as $activityKey => $activityData) {
            $this->activityModel->{$this->activityMapFunction[$activityKey]}($activityData);
        }

        if (! $this->responseSuccess) {
            $this->activityModel->responseFailed();
        }

        // Save the activity
        $this->activityModel->save();
    }

    /**
     * Return an array of additional activity data that will be merged before saving.
     * Meant to be overriden by child classes.
     *
     * @return array
     */
    protected function getAdditionalActivityData()
    {
        return [];
    }

    /**
     * Merge given data to activity data.
     *
     * @return void
     */
    private function mergeActivityData($data = [])
    {
        $this->activityData = array_merge($this->activityData, $data);
    }
}
