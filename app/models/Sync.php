<?php

class Sync extends Eloquent
{
    protected $table = 'syncs';

    protected $primaryKey = 'sync_id';

    /**
     * Set sync status to done
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @return void
     */
    public function done()
    {
        $this->status = 'done';
        $this->save();
    }

    /**
     * Check if the sync is completed by comparing total_sync and finish_sync value
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @return boolean
     */
    public function isCompleted()
    {
        if ((int) $this->total_sync !== (int) $this->finish_sync) {
            return FALSE;
        }

        return TRUE;
    }

    /**
     * Get syncronizing elapsed time
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @return timestamp
     */
    public function getElapsedTime()
    {
        $elapsedTime = strtotime($this->updated_at) - strtotime($this->created_at);

        return $elapsedTime;
    }

    /**
     * Get process percentage
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @return float
     */
    public function getProcessPercentage()
    {
        $processing = ($this->finish_sync / $this->total_sync) * 100;

        return $processing;
    }

    /**
     * Get syncronize status
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @return stdclass
     */
    public function getSyncronizingStatus()
    {
        $statuses = new stdclass();
        $statuses->elapsed_time = $this->getElapsedTime();
        $statuses->process_percentage = $this->getProcessPercentage();

        return $statuses;
    }
}
