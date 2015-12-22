<?php
/**
 * Trait for booting the default query scope of `promotions`.`is_coupon`.
 *
 * @author Tian <tian@dominopos.com>
 */
trait PromotionCouponTrait
{
    public static function bootPromotionCouponTrait()
    {
        // Which parameter should be passed to the PromotionCouponScope
        // based on the class name
        $class = get_called_class();
        switch ($class)
        {
            case 'Coupon':
                static::addGlobalScope(new PromotionCouponScope('coupon'));
                break;

            default:
                static::addGlobalScope(new PromotionCouponScope);
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
     * Force the object type to be a 'N' for promotion class and 'Y'
     * for coupon class
     *
     */
    public function save(array $options=array())
    {
        $class = get_called_class();
        switch ($class)
        {
            case 'Coupon':
                $this->setAttribute(static::OBJECT_TYPE, 'Y');
                break;

            default:
                $this->setAttribute(static::OBJECT_TYPE, 'N');
        }

        return parent::save( $options );
    }
}
