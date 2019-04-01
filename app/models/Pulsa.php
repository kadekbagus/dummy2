<?php
class Pulsa extends Eloquent
{
    protected $table = 'pulsa';

    protected $primaryKey = 'pulsa_item_id';

    public function telcoOperator()
    {
        return $this->belongsTo('TelcoOperator', 'telco_operator_id', 'telco_operator_id');
    }
}