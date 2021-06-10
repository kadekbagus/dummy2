<?php namespace Orbit\Helper\Activity;

/**
 * Example concrete pub Activity implementation.
 *
 * @author Budi <budi@gotomalls.com>
 */
class ExamplePubActivity extends PubActivity
{
    public function __construct($exampleObject, $additionalData = [])
    {
        parent::__construct(null, $exampleObject, $additionalData);
    }

    /**
     * Optional method that return array of additional data
     * that will be merged before recording the activity.
     */
    protected function getAdditionalActivityData()
    {
        return [
            'notes' => 'Notes of Example Pub Activity.',
            'activityNameLong' => 'This is Example pub Activity',
            'moduleName' => 'Example Module',
        ];
    }
}
