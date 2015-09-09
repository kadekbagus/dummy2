<?php
/**
 * Trait for booting the default query scope of `merchants`.`object_type`.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
trait MallTypeTrait
{
    public static function bootMallTypeTrait()
    {
        // Which parameter should be passed to the MerchantRetailerScope
        // based on the class name
        $class = get_called_class();
        switch ($class)
        {
            case 'Tenant':
                static::addGlobalScope(new MallTenantScope('tenant'));
                break;

            default:
                static::addGlobalScope(new MallTenantScope);
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
            case 'Tenant':
                $this->setAttribute(static::OBJECT_TYPE, 'tenant');
                break;

            default:
                $this->setAttribute(static::OBJECT_TYPE, 'mall');
        }

        return parent::save( $options );
    }
}
