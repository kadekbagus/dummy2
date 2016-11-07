<?php

class PreSync extends Eloquent
{
    protected $table = 'pre_syncs';

    protected $primaryKey = 'pre_sync_id';

    /**
     * Move PreSync to PostSync
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @return PostSync
     */
    public function moveToPostSync()
    {
        $postSync = new PostSync();
        $postSync->sync_id = $this->sync_id;
        $postSync->object_id = $this->object_id;
        $postSync->object_type = $this->object_type;
        $postSync->pre_sync_created_at = $this->created_at;
        $postSync->save();

        $this->delete(TRUE);

        return $postSync;
    }
}
