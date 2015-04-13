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
}
