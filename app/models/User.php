<?php

use Illuminate\Auth\UserTrait;
use Illuminate\Auth\UserInterface;

use Orbit\Helper\Notifications\Notifiable;
use Orbit\Helper\Activity\HasActivity;

class User extends Eloquent implements UserInterface
{
    use UserTrait;
    use ModelStatusTrait;
    use UserRoleTrait;

    use Notifiable;
    use HasActivity;

    const USER_ALREADY_ACTIVE_ERROR_CODE = 1401;

    protected $primaryKey = 'user_id';

    protected $table = 'users';

    protected $hidden = array('user_password', 'apikey', 'api_key');

    public function role()
    {
        return $this->belongsTo('Role', 'user_role_id', 'role_id');
    }

    /**
     * Get the "PMP Account" users only.
     *
     * @author Qosdil A. <qosdil@dominopos.com>
     */
    public function scopePmpAccounts($query)
    {
        $ids = CampaignAccount::lists('user_id');

        // "Campaign Owner" & "Campaign Employee" only
        $query->join('roles', 'users.user_role_id', '=', 'roles.role_id')->whereIn('role_name', ['Campaign Owner', 'Campaign Employee', 'Campaign Admin']);

        return $ids
            ? $query->whereIn('users.user_id', $ids)
            : $query;
    }

    /**
     * Get the "PMP Account" users attached to a specific mall.
     *
     * @author Qosdil A. <qosdil@dominopos.com>
     */
    public function scopeOfSpecificMallPmpAccounts($query, $mallId)
    {
        $merchantIds = Tenant::whereParentId($mallId)->lists('merchant_id');

        $userTenantArray = UserMerchant::whereObjectType('tenant')->whereIn('merchant_id', $merchantIds)->lists('user_id');

        return $userTenantArray
            ? $query->whereIn('users.user_id', $userTenantArray)
            : $query->whereUserId('');
    }

    public function userTenants()
    {
        return $this->hasMany('UserMerchant')->whereIn('object_type', ['mall', 'tenant']);
    }

    public function permissions()
    {
        return $this->belongsToMany('Permission', 'custom_permission', 'user_id', 'permission_id')->withPivot('allowed');
    }

    public function apikey()
    {
        return $this->hasOne('Apikey', 'user_id', 'user_id')->where('apikeys.status','=','active');
    }

    public function membershipNumbers()
    {
        return $this->hasMany('MembershipNumber', 'user_id', 'user_id');
    }

    public function acquirers()
    {
        return $this->hasMany('UserAcquisition', 'user_id', 'user_id');
    }

    public function modifier()
    {
        return $this->belongsTo('User', 'modified_by', 'user_id');
    }

    public function userdetail()
    {
        return $this->hasOne('UserDetail', 'user_id', 'user_id');
    }

    public function getFullName()
    {
        return strip_tags($this->user_firstname . ' ' . $this->user_lastname);
    }

    public function getUserFirstnameAttribute($value)
    {
        return strip_tags($value);
    }

    public function getUserLastnameAttribute($value)
    {
        return strip_tags($value);
    }

    /**
     * Called by ProfileHelper.php
     *
     * @param  [type] $value [description]
     * @return [type]        [description]
     */
    public function getNameAttribute($value)
    {
        return strip_tags($value);
    }

    /**
     * Called by ProfileHelper.php
     *
     * @param  [type] $value [description]
     * @return [type]        [description]
     */
    public function getUserNameAttribute($value)
    {
        return strip_tags($value);
    }

    /**
     * Called by UserCIAPIController.php
     *
     * @param  [type] $value [description]
     * @return [type]        [description]
     */
    public function getAboutAttribute($value)
    {
        return strip_tags($value);
    }

    /**
     * Called by UserCIAPIController.php
     *
     * @param  [type] $value [description]
     * @return [type]        [description]
     */
    public function getUserLocAttribute($value)
    {
        return strip_tags($value);
    }

    /**
     * Called by ProfileHelper.php
     *
     * @param  [type] $value [description]
     * @return [type]        [description]
     */
    public function getLocationAttribute($value)
    {
        return strip_tags($value);
    }

    /** This enables $user->full_name. */
    public function getFullNameAttribute()
    {
        return $this->user_firstname.' '.$this->user_lastname;
    }

    public function getUserCreatedAtAttribute()
    {
        return self::find($this->user_id)->created_at;
    }

    public function merchants()
    {
        return $this->hasMany('Merchant', 'user_id', 'user_id');
    }

    public function lastVisitedShop()
    {
        return $this->belongsTo('Retailer', 'user_details', 'user_id', 'last_visit_shop_id');
    }

    public function interests()
    {
        return $this->belongsToMany('PersonalInterest', 'user_personal_interest', 'user_id', 'personal_interest_id')
                    ->where('object_type', 'interest');
    }

    public function interestsShop()
    {
        return $this->belongsToMany('PersonalInterest', 'user_personal_interest', 'user_id', 'personal_interest_id')
                    ->where('object_type', 'interest');
    }

    public function categories()
    {
        return $this->belongsToMany('Category', 'user_personal_interest', 'user_id', 'personal_interest_id')
                    ->where('object_type', 'category');
    }

    public function banks()
    {
        return $this->belongsToMany('Object', 'object_relation', 'secondary_object_id', 'main_object_id')
                    ->where('objects.object_type', 'bank')
                    ->where('main_object_type', 'bank')
                    ->where('secondary_object_type', 'user');
    }

    public function media()
    {
        return $this->hasMany('Media', 'object_id', 'user_id')
                    ->where('object_name', 'user');
    }

    public function profilePicture()
    {
        return $this->media()->where('media_name_id', 'user_profile_picture');
    }

    public function campaignAccount()
    {
        return $this->belongsTo('CampaignAccount', 'user_id', 'user_id');
    }

    public function userMerchant()
    {
        return $this->hasMany('UserMerchant', 'user_id', 'user_id');
    }

    public function userMall()
    {
        return $this->userMerchant()->where('object_type', '=', 'mall');
    }

    public function userTenant()
    {
        return $this->userMerchant()->where('object_type', '=', 'tenant');
    }

    public function settings()
    {
        return $this->hasMany('Setting', 'object_id', 'user_id')->where('object_type', 'user');
    }

    public function guest()
    {
        return $this->belongsToMany('Guest', 'user_guest', 'user_id', 'guest_id');
    }
    /**
     * A user could be also mapped to an employee
     */
    public function employee()
    {
        return $this->hasOne('Employee', 'user_id', 'user_id');
    }

    public function userVerificationNumber()
    {
        return $this->hasOne('UserVerificationNumber', 'user_id', 'user_id');
    }

    public function userMerchantReview()
    {
        return $this->hasOne('UserMerchantReview');
    }

    public function purchases()
    {
        return $this->hasMany('PaymentTransaction');
    }

    public function discountCodes()
    {
        return $this->hasMany(DiscountCode::class);
    }

    /**
     * Tells Laravel the name of our password field so Laravel does not uses
     * its default `password` field. Our field name is `user_password`.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return string
     */
    public function getAuthPassword()
    {
        return $this->user_password;
    }

    /**
     * Method to create api keys for current user.
     *
     * @param string $apiKeyId API key ID (only specified on box, to match the one on cloud).
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return ApiKey Object
     */
    public function createAPiKey($apiKeyId = null)
    {
        $apikey = new Apikey();
        if (isset($apiKeyId)) {
            $apikey->apikey_id = $apiKeyId;
        }
        $apikey->api_key = Apikey::genApiKey($this);
        $apikey->api_secret_key = Apikey::genSecretKey($this);
        $apikey->status = 'active';
        $apikey->user_id = $this->user_id;
        $apikey = $this->apikey()->save($apikey);

        return $apikey;
    }

    /**
     * Generate guest user for mobile ci
     *
     * @author Ahmad <ahmad@dominopos.com>
     *
     */
    public function generateGuestUser() {
        $guest_identifier = '';
        $guest_ip = $_SERVER['REMOTE_ADDR'];
        $guest_time = time();
        $guest_browser = $_SERVER['HTTP_USER_AGENT'];
        $guest_identifier = sha1($guest_time . $guest_ip . $guest_browser);
        $guest_email_string = 'guest_' . $guest_identifier . '_' . $guest_time . '@myorbit.com';

        $user_role_id = Role::where('role_name', 'Guest')->first()->role_id;

        $guest_user = new User;
        $guest_user->user_email = $guest_email_string;
        $guest_user->user_password = '';
        $guest_user->username = 'guest_' . $guest_identifier;
        $guest_user->user_role_id = $user_role_id;
        $guest_user->user_ip = $guest_ip;
        $guest_user->save();

        return $guest_user;
    }

    /**
     * Get user mall ids.
     *
     * This function is based on ActivityAPIController->getLocationIdsForUser()
     *
     * If user is super admin then if not specified then return [1].
     *   If specified then return specified mall.
     * If user is mall group then if not specified then all malls in group.
     *   If specified then must be mall in group.
     * If user is mall then return linked mall id.
     * If user is mall admin then return linked mall id.
     * Invalid $mallIDs and empty data will return [].
     * @param User $user
     * @return mixed[] list of IDs
     */
    public function getUserMallIds($mallIds = null)
    {
        if ($this->isSuperAdmin()) {
            if (empty($mallIds)) {
                return [1];
            } else {
                $malls = Mall::excludeDeleted()
                             ->whereIn('merchant_id', (array)$mallIds);

                return $malls->lists('merchant_id');
            }
        } elseif ($this->isMallGroup()) {
            $mallGroup = MallGroup::excludeDeleted()->where('user_id', '=', $this->user_id)->first();
            $malls = Mall::excludeDeleted()
                         ->where('parent_id', '=', $mallGroup->merchant_id);

            if (! empty($mallIds)) {
                $malls->whereIn('merchant_id', (array)$mallIds);
            }

            return $malls->lists('merchant_id');
        } elseif ($this->isMallOwner()) {
            $mall = Mall::excludeDeleted()
                        ->where('user_id', '=', $this->user_id);

            if (! empty($mallIds)) {
                $mall->whereIn('merchant_id', (array)$mallIds);
            }

            $mall = $mall->first();

            if (empty($mall)) {
                return [];
            } else {
                return [$mall->merchant_id];
            }
        } elseif ($this->isMallAdmin() || $this->isMallCS()) {
            $mall = $this->employee->retailers();

            if (! empty($mallIds)) {
                $mall->whereIn('retailer_id', (array)$mallIds);
            }

            $mall = $mall->first();

            if (empty($mall)) {
                return [];
            } else {
                return [$mall->merchant_id];
            }
        } elseif ($this->isCampaignOwner() || $this->isCampaignEmployee()) {
            $mall = $this->employee->retailers();

            if (! empty($mallIds)) {
                $mall->whereIn('retailer_id', (array)$mallIds);
            }

            $mall = $mall->first();

            if (empty($mall)) {
                return [];
            } else {
                return [$mall->merchant_id];
            }
        } elseif ($this->isConsumer()) {
            $malls = Mall::excludeDeleted()
                         ->join('user_acquisitions', 'user_acquisitions.acquirer_id', '=', 'merchants.merchant_id')
                         ->where('user_acquisitions.user_id', '=', $this->user_id);

            if (! empty($mallIds)) {
                $malls->whereIn('user_acquisitions.acquirer_id', (array)$mallIds);
            }

            return $malls->lists('merchant_id');
        }
    }

    /**
     * Get user membership numbers
     */
    public function getMembershipNumbers($membershipCard = null)
    {
        $membershipNumbers = MembershipNumber::select('membership_numbers.*', 'memberships.merchant_id', 'memberships.membership_name')
                                             ->excludeDeleted('membership_numbers')
                                             ->join('memberships', 'membership_numbers.membership_id', '=', 'memberships.membership_id')
                                             ->where('membership_numbers.user_id', $this->user_id);

        if (! empty($membershipCard)) {
            $membershipNumbers->where('memberships.membership_id', $membershipCard->membership_id);
        }

        return $membershipNumbers->get();
    }

    public function roleIs($roles = [])
    {
        return empty($roles)
            || in_array(strtolower($this->role->role_name), $roles);
    }

    public function statusIs($status = [])
    {
        return empty($status) || in_array($this->status, $status);
    }
}
