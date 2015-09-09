<?php namespace Orbit;
/**
 * Fake the Job class which used when we are going to use the fire() not on queue
 */
class FakeJob
{
    /**
     * Fake the delete() method
     *
     * @return void
     */
    public function delete()
    {
        //
    }

    /**
     * Fake the release method
     *
     * @param int $releaseTime
     * @return void
     */
    public function release($releaseTime)
    {
        //
    }

    /**
     * Fake the getJobID
     *
     * @return string
     */
    public function getJobId()
    {
        return 'FAKE';
    }
}
