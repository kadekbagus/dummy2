<?php

/**
 * Variant
 */
class Variant extends Eloquent
{
    protected $table = 'variants';

    protected $primaryKey = 'variant_id';

    protected $fillable = ['variant_name'];

    public function options()
    {
        return $this->hasMany(VariantOption::class);
    }
}
