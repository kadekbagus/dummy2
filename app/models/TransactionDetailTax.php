<?php
class TransactionDetailTax extends Eloquent
{
    /**
    * Transaction Detail Tax model
    *
    * @author kadek <kadek@dominopos.com>
    */

    protected $table = 'transaction_detail_taxes';

    protected $primaryKey = 'transaction_detail_tax_id';

    public function transaction()
    {
        return $this->belongsTo('Transaction', 'transaction_id', 'transaction_id');
    }

    public function details()
    {
        return $this->belongsTo('TransactionDetail', 'transaction_detail_id', 'transaction_detail_id');
    }

}