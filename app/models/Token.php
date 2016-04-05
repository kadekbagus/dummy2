<?php
class Token extends Eloquent
{
    /**
    * Token Model
    *
    * @author Tian <tian@dominopos.com>
    * @author Kadek <kadek@dominopos.com>
    */

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    protected $table = 'tokens';

    protected $primaryKey = 'token_id';

    public function user()
    {
        return $this->belongsTo('User', 'user_id', 'user_id');
    }

    // generate token
    public function generateToken($input)
    {
         $string = $input . str_random(32) . microtime(TRUE);

         return sha1($string);
    }

    // check token expiration
    public function scopeNotExpire($query)
    {
        return $query->where('expire', '>=', DB::raw('NOW()'));
    }

    /**
     * Scope for getting token which has name 'user_registration_mobile'
     */
    public function scopeRegistrationToken($builder)
    {
        return $builder->where(function($q) {
            $q->where('token_name', 'user_registration_mobile');
        });
    }
}
