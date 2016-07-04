<?php namespace Orbit\Helper\Util;
/**
 * If the Job driver support bury() command then try to run it, otherwise
 * use the provided callback.
 *
 * @author Rio Astamal <rio@dominopos.com>
 */
class JobBurier
{
    protected $callback = NULL;
    protected $job = NULL;

    /**
     * @param object $job The job object
     * @param callback $callback
     * @return void
     */
    public function __construct($job, $callback=NULL)
    {
        $this->job = $job;
        $this->callback = $callback;
    }

    /**
     * @param object $job The job object
     * @param callback $callback
     * @return JobBurier
     */
    public static function create($optionalConfig=[])
    {
        return new static($optionalConfig);
    }

    /**
     * Bury the job.
     *
     * @return mixed
     */
    public function bury()
    {
        if (method_exists($this->job, 'bury')) {
            return $this->job->bury();
        }

        if (is_callable($this->callback)) {
            return $this->$callback($this->job);
        }

        return $this->callback;
    }
}