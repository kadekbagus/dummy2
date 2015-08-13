<?php
/**
 * Trait providing some scope and default query for Mall related models.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
trait MallTrait
{
    /**
     * Scope for filtering field `is_mall`.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param Illuminate\Database\Query\Builder $query
     * @param string $flag - Flag for mall status 'yes' or 'no'
     * @return Illuminate\Database\Query\Builder
     */
    public function scopeIsMall($query, $flag='yes')
    {
        if ($flag === 'yes') {
            return $query->where('merchants.is_mall', $flag);
        }

        return $query->where(function($query) use ($flag) {
                    $query->where('merchants.is_mall', $flag);
                    $query->orWhereNull('merchants.is_mall');
        });
    }
}
