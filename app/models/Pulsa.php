<?php
class Pulsa extends Eloquent
{
    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    protected $table = 'pulsa';

    protected $primaryKey = 'pulsa_item_id';

    public function telcoOperator()
    {
        return $this->belongsTo('TelcoOperator', 'telco_operator_id', 'telco_operator_id');
    }
}
