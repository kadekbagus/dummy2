<?php
use OrbitRelation\HasManyThrough;

class Merchant extends Eloquent
{
    /**
     * Merchant Model
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @author Kadek <kadek@dominopos.com>
     * @author Rio Astamal <me@rioastamal.net>
     */

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    /**
     * Use Trait MerchantTypeTrait so we only displaying records with value
     * `object_type` = 'merchant'
     */
    use MerchantTypeTrait;

    /**
     * Column name which determine the type of Merchant or Retailer.
     */
    const OBJECT_TYPE = 'object_type';

    const OMID_INCREMENT = 111111;

    protected $primaryKey = 'merchant_id';

    protected $table = 'merchants';

    public function user()
    {
        return $this->belongsTo('User', 'user_id', 'user_id');
    }

    public function retailers()
    {
        return $this->hasMany('Retailer', 'parent_id', 'merchant_id')->excludeDeleted();
    }

    public function taxes()
    {
        return $this->hasMany('MerchantTax', 'merchant_id', 'merchant_id')->excludeDeleted();
    }

    public function children()
    {
        return $this->retailers();
    }

    /**
     * Eagler load the count query. It is not very optimized but it works for now
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @credit http://laravel.io/forum/05-03-2014-eloquent-get-count-relation
     * @return int
     */
    public function retailersNumber()
    {
        // Basically we query Retailer which the parent_id are the same as the
        // current one.
        return $this->hasOne('Retailer', 'parent_id', 'merchant_id')
                    ->excludeDeleted()
                    ->selectRaw('parent_id, count(*) as count')
                    ->groupBy('parent_id');
    }

    /**
     * Shortcut to access the retailers count relation
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return int
     */
    public function getRetailersCountAttribute()
    {
        return $this->retailersNumber ? $this->retailersNumber->count : 0;
    }

    public function getPhoneCodeArea($separator='|#|')
    {
        $phone = explode($separator, $this->phone);

        if (isset($phone[0])) {
            return $phone[0];
        }

        return NULL;
    }

    public function getPhoneNumber($separator='|#|')
    {
        $phone = explode($separator, $this->phone);

        if (isset($phone[1])) {
            return $phone[1];
        }

        return NULL;
    }

    public function getFullPhoneNumber($separator='|#|', $concat=' ')
    {
        $phone = explode($separator, $this->phone);

        if (isset($phone[1])) {
            return $phone[0] . $concat . $phone[1];
        }

        return $phone[0];
    }

    /**
     * Contact person phone.
     */
    public function getContactPhoneCodeArea($separator='|#|')
    {
        $contact_person_phone = explode($separator, $this->contact_person_phone);

        if (isset($contact_person_phone[0])) {
            return $contact_person_phone[0];
        }

        return NULL;
    }

    public function getContactPhoneNumber($separator='|#|')
    {
        $contact_person_phone = explode($separator, $this->contact_person_phone);

        if (isset($contact_person_phone[1])) {
            return $contact_person_phone[1];
        }

        return NULL;
    }

    public function getContactFullPhoneNumber($separator='|#|', $concat=' ')
    {
        $contact_person_phone = explode($separator, $this->contact_person_phone);

        if (isset($contact_person_phone[1])) {
            return $contact_person_phone[0] . $concat . $contact_person_phone[1];
        }

        return $contact_person_phone[0];
    }

    /**
     * Contact person phone2.
     */
    public function getContact2PhoneCodeArea($separator='|#|')
    {
        $contact_person_phone2 = explode($separator, $this->contact_person_phone2);

        if (isset($contact_person_phone2[0])) {
            return $contact_person_phone2[0];
        }

        return NULL;
    }

    public function getContact2PhoneNumber($separator='|#|')
    {
        $contact_person_phone2 = explode($separator, $this->contact_person_phone2);

        if (isset($contact_person_phone2[1])) {
            return $contact_person_phone2[1];
        }

        return NULL;
    }

    public function getContact2FullPhoneNumber($separator='|#|', $concat=' ')
    {
        $contact_person_phone2 = explode($separator, $this->contact_person_phone2);

        if (isset($contact_person_phone2[1])) {
            return $contact_person_phone2[0] . $concat . $contact_person_phone2[1];
        }

        return $contact_person_phone2[0];
    }

    /**
     * Merchant has many user details
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function userDetails()
    {
        return $this->hasMany('UserDetail', 'merchant_id', 'merchant_id');
    }

    /**
     * Merchant has many users
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function consumers()
    {
        return $this->hasManyThrough('User', 'UserDetail', 'merchant_id', 'user_id', 'user_id');
    }

    /**
     * Merchant has many transactions
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function transactions()
    {
        return $this->hasMany('Transaction', 'merchant_id', 'merchant_id');
    }

    /**
     * Merchant has many uploaded media.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function media()
    {
        return $this->hasMany('Media', 'object_id', 'merchant_id')
                    ->where('object_name', 'merchant');
    }

    /**
     * Merchant has many uploaded logo.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function mediaLogo()
    {
        return $this->media()->where('media_name_id', 'merchant_logo');
    }

    /**
     * Add Filter merchant based on user who request it.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @param  User $user Instance of object user
     */
    public function scopeAllowedForUser($builder, $user)
    {
        // Super admin allowed to see all entries
        $superAdmin = Config::get('orbit.security.superadmin');
        if (empty($superAdmin))
        {
            $superAdmin = array('super admin');
        }

        // Transform all array into lowercase
        $superAdmin = array_map('strtolower', $superAdmin);
        $userRole = trim(strtolower($user->role->role_name));
        if (in_array($userRole, $superAdmin))
        {
            // do nothing return as is
            return $builder;
        }

        // This will filter only user which belongs to merchant
        $builder->where('merchants.user_id', $user->user_id);

        return $builder;
    }

    /**
     * Add Filter merchant based on transaction and users.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @param  User $user Instance of object user
     */
    public function scopeTransactionCustomerIds($builder, array $userIds)
    {
        return $builder->select('merchants.*')
                       ->join('transactions', 'transactions.merchant_id', '=', 'merchants.merchant_id')
                       ->where('transactions.status', 'paid')
                       ->whereIn('customer_id', $userIds)
                       ->groupBy('merchants.merchant_id');
    }


    /**
     * Accessor for default logo
     * @author Ahmad Anshori <ahmad@dominopos.com>
     *
     */
    public function getLogoAttribute($value)
    {
        if(is_null($value)){
            return '/mobile-ci/images/default-logo.png';
        } else {
            return $value;
        }
    }

    /**
     * Define a has-many-through relationship.
     *
     * @param  string  $related
     * @param  string  $through
     * @param  string|null  $firstKey
     * @param  string|null  $secondKey
     * @param  string|null  $parentKey
     * @return \Illuminate\Database\Eloquent\Relations\HasManyThrough
     */
    public function hasManyThrough($related, $through, $firstKey = null, $secondKey = null, $parentKey = null)
    {
        $through = new $through;

        $firstKey = $firstKey ?: $this->getForeignKey();

        $secondKey = $secondKey ?: $through->getForeignKey();

        return new HasManyThrough((new $related)->newQuery(), $this, $through, $firstKey, $secondKey, $parentKey);
    }

    /**
     * Method to get list of retailers Ids.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return array
     */
    public function getMyRetailerIds($status='active')
    {
        return DB::table('merchants')->where('object_type', 'retailer')
                                     ->where('status', '!=', 'deleted')
                                     ->where('parent_id', $this->merchant_id)
                                     ->lists('merchant_id');
    }

}
