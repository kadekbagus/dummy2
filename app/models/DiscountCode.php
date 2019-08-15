<?php

class DiscountCode extends Eloquent
{
    protected $table = 'discount_codes';
    protected $primaryKey = 'discount_code_id';

    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    public function scopeReserved($query)
    {
        return $query->where('status', 'reserved');
    }

    public function scopeIssued($query)
    {
        return $query->where('status', 'issued');
    }

    public function scopeIssuedOrWaitingPayment($query)
    {
        return $query->where('status', 'issued')
            ->orWhere(function($qry) {
                $qry->where('status', 'reserved')
                    ->whereNotNull('payment_transaction_id');
            });
    }

    /**
     * scope for condition when user reserved promo code
     * but not yet click checkout
     */
    public function scopeReservedNotWaitingPayment($query)
    {
        return $query->where('status', 'reserved')
            ->whereNull('payment_transaction_id');
    }

    /**
     * scope for condition when user reserved promo code
     * and click checkout
     */
    public function scopeReservedAndWaitingPayment($query)
    {
        return $query->where('status', 'reserved')
            ->whereNotNull('payment_transaction_id');
    }

    public function users()
    {
        return $this->belongsTo(User::class);
    }

    public function discount()
    {
        return $this->belongsTo(Discount::class);
    }

    public function makeAvailable()
    {
        $this->user_id = null;
        $this->payment_transaction_id = null;
        $this->status = 'available';
        $this->save();
    }

    public function payment()
    {
        return $this->belongsTo(PaymentTransaction::class, 'payment_transaction_id');
    }
}
