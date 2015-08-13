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
}
