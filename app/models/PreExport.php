<?php

class PreExport extends Eloquent
{
    protected $table = 'pre_exports';

    protected $primaryKey = 'pre_export_id';

    /**
     * Move PreExport to PostExport
     *
     * @author Shelgi <shelgi@dominopos.com>
     * @return PostExport
     */
    public function moveToPostExport()
    {
        $postExport = new PostExport();
        $postExport->export_id = $this->export_id;
        $postExport->object_id = $this->object_id;
        $postExport->object_type = $this->object_type;
        $postExport->pre_export_created_at = $this->created_at;
        $postExport->file_path = $this->file_path;
        $postExport->export_process_type = $this->export_process_type;
        $postExport->save();

        $this->delete(TRUE);

        return $postExport;
    }
}
