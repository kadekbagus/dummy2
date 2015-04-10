<?php
/**
 * Class to represent the personal interests table.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
class PersonalInterest extends Eloquent
{
    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    protected $table = 'personal_interests';
    protected $primaryKey = 'personal_interest_id';

    /**
     * Scope to filter personal interest based on user ids
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param \Illuminate\Database\Eloquent\Builder  $builder
     * @param array $userIds - List of user ids
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeUserIds($builder, $userIds)
    {
        return $builder->select('personal_interests.*')
                       ->join('user_personal_interest',
                              'user_personal_interest.personal_interest_id',
                              '=',
                              'personal_interests.personal_interest_id')
                       ->whereIn('user_personal_interest.user_id', $userIds);
    }
}
