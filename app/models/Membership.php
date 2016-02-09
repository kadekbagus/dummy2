<?php

class Membership extends Eloquent
{

    /**
     * Membership Model
     *
     * @author Tian <tian@dominopos.com>
     */
    use ModelStatusTrait;

    protected $table = 'memberships';

    protected $primaryKey = 'membership_id';

    public function mall()
    {
        return $this->belongsTo('Mall', 'merchant_id', 'merchant_id');
    }

    public function creator()
    {
        return $this->belongsTo('User', 'created_by', 'user_id');
    }

    public function modifier()
    {
        return $this->belongsTo('User', 'modified_by', 'user_id');
    }

    /**
     * Membership has many uploaded media.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function media()
    {
        return $this->hasMany('Media', 'object_id', 'membership_id')
                    ->where('object_name', 'membership');
    }

}
