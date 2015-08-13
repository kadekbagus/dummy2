<?php

/**
 * A language associated with a merchant.
 *
 * @property int $merchant_language_id
 * @property int $merchant_id
 * @property int $language_id
 * @property Merchant $merchant
 * @property Language $language
 */
class MerchantLanguage extends Eloquent
{
    use ModelStatusTrait;

    protected $primaryKey = 'merchant_language_id';

    protected $table = 'merchant_languages';

    public function merchant()
    {
        return $this->hasOne('Merchant', 'merchant_id', 'merchant_id');
    }

    public function language()
    {
        return $this->hasOne('Language', 'language_id', 'language_id');
    }

    /**
     * Add Filter merchant language based on user who request it.
     *
     * Filters merchant.user_id = requesting_user.user_id
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @param  User $user requesting user
     *
     * @return \Illuminate\Database\Eloquent\Builder  $builder
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

        $builder = $builder->whereHas('merchant', function ($q) use ($user) {
            $q->where('user_id', '=', $user->user_id);
        });

        return $builder;
    }


}
