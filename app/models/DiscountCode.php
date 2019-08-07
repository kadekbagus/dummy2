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
}
