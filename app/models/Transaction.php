<?php
class Transaction extends Eloquent
{
    /**
    * Transaction model
    *
    * @author kadek <kadek@dominopos.com>
    */

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    protected $table = 'transactions';

    protected $primaryKey = 'transaction_id';

    public function details()
    {
        return $this->hasMany('TransactionDetail', 'transaction_id', 'transaction_id');
    }

    public function cashier()
    {
        return $this->belongsTo('User', 'cashier_id', 'user_id');
    }

    public function user()
    {
        return $this->belongsTo('User', 'customer_id', 'user_id');
    }

    public function merchant()
    {
        return $this->belongsTo('Merchant', 'merchant_id', 'merchant_id');
    }

    public function retailer()
    {
        return $this->belongsTo('Retailer', 'retailer_id', 'merchant_id');
    }

    public function detailpromotion()
    {
        return $this->hasMany('TransactionDetailPromotion', 'transaction_id', 'transaction_id');
    }

    public function detailcoupon()
    {
        return $this->hasMany('TransactionDetailCoupon', 'transaction_id', 'transaction_id');
    }

    public function detailtax()
    {
        return $this->hasMany('TransactionDetailTax', 'transaction_id', 'transaction_id');
    }

    /**
     * Scope to join with TransactionDetails.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return Builder
     */
    public function scopeTransactionDetailsJoin($builder)
    {
        return $builder->select('transactions.*')
                       ->join('transaction_details', 'transaction_details.transaction_id', '=', 'transactions.transaction_id')
                       ->groupBy('transactions.transaction_id');
    }
}
