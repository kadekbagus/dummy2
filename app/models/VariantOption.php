<?php

/**
 * Variant
 */
class VariantOption extends Eloquent
{
    protected $table = 'variant_options';

    protected $primaryKey = 'variant_option_id';

    protected $fillable = ['variant_option_id', 'variant_id', 'value'];
}
