<?php
/**
 * Default scope for modifying the default query builder value to provide
 * where `promotions`.`is_coupon`='Y'|'N'
 *
 * @author Tian <tian@dominopos.com>
 */
use Illuminate\Database\Eloquent\ScopeInterface;
use Illuminate\Database\Eloquent\Builder;

class PromotionCouponScope implements Illuminate\Database\Eloquent\ScopeInterface
{
    protected $objectType = 'promotion';

    public function __construct($objectType=NULL)
    {
        if (! is_null($objectType))
        {
            $this->objectType = $objectType;
        }
    }

    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @return void
     */
    public function apply(Builder $builder)
    {
        if ($this->objectType === 'promotion')
        {
            $builder->where('promotions.is_coupon', 'N');
        } elseif ($this->objectType === 'coupon')
        {
            $builder->where('promotions.is_coupon', 'Y');
        }

        // Add Unknown scope
        $this->addWithUnknown($builder);
    }

    /**
     * Remove the scope from the given Eloquent query builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @return void
     */
    public function remove(Builder $builder)
    {
        $column = $builder->getModel()->getQualifiedObjectTypeColumn();
        $query = $builder->getQuery();

        // Bug? The wheres is removed from the query builder but the binding is not
        // So we need to remove it so it does not appended to the query builder
        $bindings = $builder->getBindings();

        foreach ((array) $query->wheres as $key => $where)
        {
            // If the where clause is a soft delete date constraint, we will remove it from
            // the query and reset the keys on the wheres. This allows this developer to
            // include deleted model in a relationship result set that is lazy loaded.
            if ($where['type'] === 'Basic' && $where['column'] === $column)
            {
                unset($query->wheres[$key]);

                // Unset the related bindings
                unset($bindings[$key]);
            }

            $query->wheres = array_values($query->wheres);
        }

        // Re-assign
        $builder->setBindings( $bindings );
    }

    /**
     * Add the with-unknown extension to the builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @return void
     */
    protected function addWithUnknown(Builder $builder)
    {
        $builder->macro('withUnknown', function(Builder $builder)
        {
            $this->remove($builder);

            return $builder;
        });
    }
}
