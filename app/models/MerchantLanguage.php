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

    public function mall()
    {
        return $this->hasOne('Mall', 'merchant_id', 'merchant_id');
    }

    public function language()
    {
        return $this->hasOne('Language', 'language_id', 'language_id');
    }

    /**
     * Get merchant language id for particular language name and merchant id.
     *
     * @author Rio Astamal <rio@dominopos.com>
     * @param string $merchantId
     * @param string $languageName - Two char code of the language name e.g: 'en', 'id', 'es'
     * @return string
     */
    public static function getLanguageIdByMerchant($merchantId, $languageName)
    {
        $result = NULL;
        $lang = Language::where('name', $languageName)->first();
        $merchantLanguage = static::where('merchant_id', $merchantId)
                                    ->where('language_id', $lang->language_id)
                                    ->first();

        if (is_object($merchantLanguage)) {
            $result = $merchantLanguage->merchant_language_id;
        }

        return $result;
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

        $builder = $builder->where(function ($q) use ($user) {
            $q->whereHas('merchant', function ($q) use ($user) {
                $q->where('user_id', '=', $user->user_id);
            })->orWhereHas('mall', function ($q) use ($user) {
                $q->where('user_id', '=', $user->user_id);
            });
        });

        return $builder;
    }


}
