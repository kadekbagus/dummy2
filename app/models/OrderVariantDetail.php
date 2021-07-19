<?php

/**
 * Order Detail model.
 *
 * @author Budi <budi@gotomalls.com>
 */
class OrderVariantDetail extends Eloquent
{
    protected $table = 'order_variant_details';

    protected $primaryKey = 'order_variant_detail_id';

    protected $guarded = [];
}
