<?php

class PostExport extends Eloquent
{
    protected $table = 'post_exports';

    protected $primaryKey = 'post_export_id';

    /**
     * Increment export finish_export value
     *
     * @author Shelgi Prasetyo <shelgi@dominopos.com>
     * @return export
     */
    public function incrementExportCounter()
    {
        $export = Export::where('export_id', $this->export_id)->first();

        if (! is_object($export)) {
            return FALSE;
        }

        $checkPre = PreExport::where('export_id', $this->export_id)
                            ->where('object_type', $this->object_type)
                            ->where('object_id', $this->object_id)
                            ->get();

        if (is_object($checkPre)) {
            return FALSE;
        }

        $export->finished_export = $export->finished_export + 1;
        $export->save();

        return $export;
    }
}
