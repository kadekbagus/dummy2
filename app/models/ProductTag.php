<?php

class ProductTag extends Eloquent
{
    use ModelStatusTrait;

    protected $primaryKey = 'product_tag_id';

    protected $table = 'product_tags';
}