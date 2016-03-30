<?php
use OrbitRelation\HasManyThrough;

class Mall extends Eloquent
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
     * Use Trait MallTypeTrait so we only displaying records with value
     * `object_type` = 'mall'
     */
    use MallTypeTrait;

    /**
     * Trait related with Geolocation
     */
    use MerchantGeolocTrait;

    /**
     * Column name which determine the type of Mall or Tenant.
     */
    const OBJECT_TYPE = 'object_type';

    const ORID_INCREMENT = 111111;

    protected $primaryKey = 'merchant_id';

    protected $table = 'merchants';

    public function user()
    {
        return $this->belongsTo('User', 'user_id', 'user_id');
    }

    public function timezone()
    {
        return $this->belongsTo('Timezone', 'timezone_id', 'timezone_id');
    }

    public function tenants()
    {
        return $this->hasMany('Tenant', 'parent_id', 'merchant_id')->excludeDeleted();
    }

    public function taxes()
    {
        return $this->hasMany('MerchantTax', 'merchant_id', 'merchant_id')->excludeDeleted();
    }

    public function parent()
    {
        return $this->belongsTo('MallGroup', 'parent_id', 'merchant_id');
    }

    public function children()
    {
        return $this->tenants();
    }

    public function settings()
    {
        return $this->hasMany('Setting', 'object_id', 'merchant_id')->where('object_type', 'merchant');
    }

    /**
     * Merchant belongs to and has many category.
     */
    public function categories()
    {
        return $this->belongsToMany('Category', 'category_merchant', 'merchant_id', 'category_id');
    }

    /**
     * Eagler load the count query. It is not very optimized but it works for now
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @credit http://laravel.io/forum/05-03-2014-eloquent-get-count-relation
     * @return int
     */
    public function tenantsNumber()
    {
        // Basically we query Retailer which the parent_id are the same as the
        // current one.
        return $this->hasOne('Tenant', 'parent_id', 'merchant_id')
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
    public function getTenantCountAttribute()
    {
        return $this->tenantsNumber ? $this->tenantsNumber->count : 0;
    }

    public function getPhoneCodeArea($separator='|#|')
    {
        $phone = explode($separator, $this->phone);

        if (isset($phone[0])) {
            return $phone[0];
        }

        return NULL;
    }

    /**
     * Merchant has many languages for translations.
     */
    public function languages()
    {
        return $this->hasMany('MerchantLanguage', 'merchant_id', 'merchant_id')->excludeDeleted();
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
                    ->where('object_name', 'mall');
    }

    /**
     * Mall has many uploaded media with original type.
     *
     * @author Tian <tian@dominopos.com>
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function mediaOrig()
    {
        return $this->hasMany('Media', 'object_id', 'merchant_id')
                    ->where('object_name', 'mall')
                    ->where('media_name_long', 'like', '%_orig')
                    ->orderBy('metadata', 'asc');
    }

    /**
     * Merchant has many uploaded logo.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function mediaLogo()
    {
        return $this->media()->where('media_name_id', 'mall_logo');
    }

    /**
     * Mall has many uploaded logo.
     *
     * @author Tian <tian@dominopos.com>
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function mediaLogoOrig()
    {
        return $this->mediaOrig()->where('media_name_id', 'mall_logo');
    }

    /**
     * Merchant has many uploaded background.
     *
     * @author Irianto <irianto@dominopos.com>
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function mediaBackground()
    {
        return $this->media()->where('media_name_id', 'retailer_background');
    }

    /**
     * Mall has many uploaded background.
     *
     * @author Irianto <irianto@dominopos.com>
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function mediaBackgroundOrig()
    {
        return $this->mediaOrig()->where('media_name_id', 'retailer_background');
    }

    /**
     * Merchant has one uploaded icon.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function mediaIcon()
    {
        return $this->hasOne('Media', 'object_id', 'merchant_id')
                    ->where('object_name', 'mall')
                    ->where('media_name_id', 'mall_icon');
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
    public function getLogo2Attribute($value)
    {
        $domain = Request::getHost();
        // Prevent directory travelsal
        $domain = str_replace('..', '', $domain);

        // Load the file if exists
        $relativePath = sprintf('/mobile-ci/images/%s/logo.png', $domain);
        $footerImage = public_path() . '/' . $relativePath;

        if (file_exists($footerImage)) {
            return $relativePath;
        }

        $mallMediaLogo = $this->mediaLogoOrig()->first();
        if (is_object($mallMediaLogo)) {
            return $mallMediaLogo->path;
        } else {
            return '/mobile-ci/images/default-logo.png';
        }
    }

    /**
     * Accessor for default big logo
     * @author Rio Astamal <me@rioastamal.net>
     * @return string
     */
    public function getBigLogoAttribute($value)
    {
        if (is_null($value)) {
            $domain = Request::getHost();
            // Prevent directory travelsal
            $domain = str_replace('..', '', $domain);

            // Load the file if exists
            $relativePath = sprintf('/mobile-ci/images/%s/big-logo.png', $domain);
            $footerImage = public_path() . '/' . $relativePath;

            if (file_exists($footerImage)) {
                return $relativePath;
            }

            $mallMediaLogo = $this->mediaLogoOrig()->first();
            if (is_object($mallMediaLogo)) {
                return $mallMediaLogo->path;
            } else {
                return '/mobile-ci/images/default-logo.png';
            }
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

    public function merchantSocialMedia() {
        return $this->hasMany('MerchantSocialMedia', 'merchant_id', 'merchant_id');
    }
}
