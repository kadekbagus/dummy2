<?php

class Export extends Eloquent
{
    protected $table = 'exports';

    protected $primaryKey = 'export_id';

    /**
     * Set export status to done
     *
     * @author Shelgi Prasetyo <shegli@dominopos.com>
     * @return void
     */
    public function done()
    {
        $this->status = 'done';
        $this->save();
    }

    /**
     * Check if the export is completed by comparing total_export and finish_export value
     *
     * @author Shelgi Prasetyo <shegli@dominopos.com>
     * @return boolean
     */
    public function isCompleted()
    {
        if ((int) $this->total_export !== (int) $this->finish_export) {
            return FALSE;
        }

        return TRUE;
    }
}
