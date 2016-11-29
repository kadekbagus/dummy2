<?php

class PostSync extends Eloquent
{
    protected $table = 'post_syncs';

    protected $primaryKey = 'post_sync_id';

    /**
     * Increment Sync finish_sync value
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @return Sync
     */
    public function incrementSyncCounter()
    {
        $sync = Sync::where('sync_id', $this->sync_id)->first();

        if (! is_object($sync)) {
            return FALSE;
        }

        $sync->finish_sync = $sync->finish_sync + 1;
        $sync->save();

        return $sync;
    }
}
