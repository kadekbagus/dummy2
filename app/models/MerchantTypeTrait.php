<?php
/**
 * Trait for booting the default query scope of `merchants`.`object_type`.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
trait MerchantTypeTrait
{
    public static function bootMerchantTypeTrait()
    {
        // Which parameter should be passed to the MerchantRetailerScope
        // based on the class name
        $class = get_called_class();
        switch ($class)
        {
            case 'Retailer':
                static::addGlobalScope(new MerchantRetailerScope('retailer'));
                break;

            default:
                static::addGlobalScope(new MerchantRetailerScope);
        }
    }

    /**
     * Get the fully qualified "object_type" column.
     *
     * @return string
     */
    public function getQualifiedObjectTypeColumn()
    {
        return $this->getTable() . '.' . static::OBJECT_TYPE;
    }

    /**
     * Force the object type to be a 'merchant' for merchant class and 'retailer'
     * for retailer class
     *
     * @author Rio Astamal <me@rioastamal.net>
     */
    public function save(array $options=array())
    {
        $class = get_called_class();
        switch ($class)
        {
            case 'Retailer':
                $this->setAttribute(static::OBJECT_TYPE, 'retailer');
                break;

            default:
                $this->setAttribute(static::OBJECT_TYPE, 'merchant');
        }

        return parent::save( $options );
    }

    /**
     * Get particular settings for this object.
     *
     * @author Rio Astamal <me@rioastamal.net>
     */
    public function settings()
    {
        return $this->hasMany('Setting', 'object_id', 'merchant_id')
                    ->where('object_type', 'merchant')
                    ->where('setting_name', '!=', 'master_password');
    }

    /**
     * Get particular news for merchants (retailers)
     */
    public function news()
    {
        $prefix = DB::getTablePrefix();

        return $this->belongsToMany('News', 'news_merchant', 'merchant_id', 'news_id')
            ->withPivot('object_type')
            ->where('news.object_type', 'news')
            ->where('news_merchant.object_type', 'retailer')
            ->where('news.status', 'active')
            ->whereRaw("NOW() between {$prefix}news.begin_date and {$prefix}news.end_date")
            ->orderBy('news.sticky_order', 'desc')
            ->orderBy('news.created_at', 'desc');
    }

    /**
     * Get particular news for merchants (retailers)
     */
    public function newsPromotions()
    {
        $prefix = DB::getTablePrefix();

        return $this->belongsToMany('News', 'news_merchant', 'merchant_id', 'news_id')
            ->withPivot('object_type')
            ->where('news.object_type', 'promotion')
            ->where('news_merchant.object_type', 'retailer')
            ->where('news.status', 'active')
            ->whereRaw("NOW() between {$prefix}news.begin_date and {$prefix}news.end_date")
            ->orderBy('news.sticky_order', 'desc')
            ->orderBy('news.created_at', 'desc');
    }
}
