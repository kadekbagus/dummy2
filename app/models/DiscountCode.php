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
