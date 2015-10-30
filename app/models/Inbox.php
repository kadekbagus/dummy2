<?php
/**
 * Model for Inbox or alert.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
class Inbox extends Eloquent
{
    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    protected $table = 'inboxes';
    protected $primaryKey = 'inbox_id';

    /**
     * Get the latest one.
     */
    public function scopeLatestOne($query, $userId = NULL, $mallId = NULL)
    {
        $latest =  $query->orderBy('inboxes.created_at', 'asc')
                         ->where('is_read', 'N')
                         ->excludeDeleted();

        if ($userId !== NULL) {
            $latest->where('user_id', $userId);
        }

        if ($mallId !== NULL) {
            $latest->where('merchant_id', $mallId);
        }

        return $latest;
    }

    /**
     * Get the alert type inbox.
     */
    public function scopeAlert($query)
    {
        return $query->where('inbox_type', 'alert');
    }
}
