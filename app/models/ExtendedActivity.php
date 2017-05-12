<?php
/**
 * Model for representing the extended_activities table.
 *
 * @author Ahmad <ahmad@dominopos.com>
 */
class ExtendedActivity extends Eloquent
{
    protected $primaryKey = 'extended_activity_id';
    protected $table = 'extended_activities';

    public function activity()
    {
        return $this->belongsTo('Activity', 'activity_id', 'activity_id');
    }
}
