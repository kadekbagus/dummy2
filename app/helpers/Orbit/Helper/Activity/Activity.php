<?php namespace Orbit\Helper\Activity;

use Activity as ActivityModel;

/**
 * Activity Helper Class.
 *
 * A class that will simplify how controllers record activities.
 *
 * Most of the time, we would write code like this on the controllers:
 * Activity::mobileci()
 *          ->setActivityType('transaction')
 *          ->setUser($payment_update->user)
 *          ->setActivityName('transaction_status');setActivityNameLong('Transaction is Failed')
 *          ->setModuleName('Midtrans Transaction')
 *          ->setObject($payment_update)
 *          ->setNotes('Transaction is failed from Midtrans/Customer.')
 *          ->setLocation($mall)
 *          ->responseFailed()
 *          ->save();
 *
 * By utilizing this helper, we could do:
 * $user->activity(new TransactionFailedActivity(null, $payment_update, ['location' => $mall]));
 *
 * And of course, we also will be able to do this:
 * (new TransactionFailedActivity($user, $payment_update, ['location' => $mall]))->record();
 *
 * @author Budi <budi@dominopos.com>
 */
abstract class Activity
{
    protected $activityModel;

    protected $isMobileCI = true;

    protected $object = null;

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
    ];

    function __construct($subject = null, $object = null, $additionalData = [])
    {
        if ($this->isMobileCI) {
            $this->activityModel = ActivityModel::mobileci();
        }
        else {
            $this->activityModel = ActivityModel::csportal();
        }

        if (! empty($subject)) {
            $this->activityModel->setUser($subject);
        }

        if (! empty($object)) {
            $this->activityModel->setObject($object);
        }

        if (! empty($additionalData)) {
            $this->mergeAdditionalData($additionalData);
        }
    }

    /**
     * Set the subject/user of the activity.
     *
     * @param [type] $subject [description]
     */
    public function setSubject($subject = null)
    {
        if (! empty($subject)) {
           $this->subject = $subject;
           $this->activityModel->setUser($subject);

           return $this;
        }

        throw new Exception('Empty subject!');
    }

    /**
     * Set the object of the activity.
     *
     * @param [type] $object [description]
     */
    public function setObject($object = null)
    {
        $this->object = $object;
        $this->activityModel->setObject($object);
        return $this;
    }

    /**
     * Merge additional activity data. Different with hook addAdditionalData(),
     * this method is public so can be called directly from an object instance.
     *
     * @param array $activityData [description]
     */
    public function mergeAdditionalData($additionalData = [])
    {
        if (! empty($additionalData)) {
            $this->activityData = array_merge($this->activityData, $additionalData);
        }

        return $this;
    }

    /**
     * Record the Activity!
     *
     * @param  array  $activityData [description]
     * @return [type]               [description]
     */
    public function record($activityData = [])
    {
        // Add additional data if needed.
        $this->mergeAdditionalData($this->addAdditionalData());

        // Build activity data.
        $this->buildActivityData();

        // Save it.
        $this->saveActivity();
    }

    /**
     * A hook that let child classes add custom activity data
     * specific for their needs. By default does nothing,
     * because it meant to be overriden when needed.
     *
     * @return [type] [description]
     */
    protected function addAdditionalData()
    {
        return [];
    }

    /**
     * Build activity data based on activityData property.
     *
     * @return [type] [description]
     */
    protected function buildActivityData()
    {
        foreach($this->activityData as $activityKey => $activityData) {
            $this->activityModel->{$this->activityMapFunction[$activityKey]}($activityData);
        }

        if (! $this->responseSuccess) {
            $this->activityModel->responseFailed();
        }
    }

    /**
     * Save the Activity.
     *
     * @return [type] [description]
     */
    protected function saveActivity()
    {
        $this->activityModel->save();
    }
}
