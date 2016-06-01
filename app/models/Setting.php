<?php
/**
 * Model for represeting the settings table.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
class Setting extends Eloquent
{
    protected $table = 'settings';
    protected $primaryKey = 'setting_id';

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    /**
     * Get master password from particular mall.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param $merchantId - The merchant ID
     * @return Setting
     */
    public static function getMasterPasswordFor($merchantId)
    {
        return Setting::where('setting_name', 'master_password')
                      ->where('object_id', $merchantId)
                      ->where('object_type', 'merchant')
                      ->first();
    }

    /**
     * Search particular setting_name from a collection.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param Collection|array The collection of settings
     * @param string $name The setting name
     * @return mixed
     */
    public static function getFromList($collection, $name)
    {
        $return = [];

        foreach ($collection as $item) {
            if ((string)$item->setting_name === $name) {
                // insert the item
                $return[] = $item->setting_value;
                break;
            }
        }

        if (empty($return)) {
            throw new Exception ('Setting ' . $name . ' not found.');
        }

        return $return;
    }

    /**
     * A category may have many translations.
     * @author Irianto Pratama <irianto@dominopos.com>
     */
    public function translations()
    {
        return $this->hasMany('SettingTranslation', 'setting_id', 'setting_id')->excludeDeleted()->whereHas('language', function($has) {
            $has->where('merchant_languages.status', 'active');
        });
    }

    /**
     * Get the mall by domain name.
     *
     * @author Rio Astamal <rio@dominopos.com>
     * @param string $domainName
     */
    public static function getMallByDomain($domainName)
    {
        $settingName = sprintf('dom:%s', $domainName);

        $setting = static::where('setting_name', $settingName)->first();
        if (is_object($setting)) {
            Log::info( sprintf('-- SETTING MODEL -- Mall ID from setting: %s', $setting->setting_value) );

            if (! is_object($setting)) {
                Log::info( sprintf('-- SETTING MODEL -- Setting not found: %s', $setting->setting_value) );
                return NULL;
            }

            $mall = Mall::where('merchant_id', $setting->setting_value)->first();
            if (! is_object($mall)) {
                Log::info( sprintf('-- SETTING MODEL -- Can not find mall with ID: %s', $setting->setting_value) );
                return NULL;
            }

            Log::info( sprintf('-- SETTING MODEL -- Mall found with name: %s', $mall->name) );

            return $mall;
        }

        return null;
    }
}
