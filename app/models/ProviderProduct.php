<?php

/**
 * Provider Product Model.
 */
class DigitalProduct extends Eloquent
{
    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    protected $table = 'provider_products';

    protected $primaryKey = 'provider_product_id';
}
