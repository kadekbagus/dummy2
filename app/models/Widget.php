<?php
/**
 * Widget for represent the structure of Widget table.
 *
 * @author Rio Astamal <me@rioastamal.net?
 */
class Widget extends Eloquent
{
    protected $table = 'widgets';
    protected $primaryKey = 'widget_id';

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    /**
     * A widget belongs to a merchant.
     */
    public function merchant()
    {
        return $this->belongsTo('merchant', 'merchant_id', 'merchant_id');
    }

    /**
     * A widget belongs to many retailer
     */
    public function retailers()
    {
        return $this->belongsToMany('WidgetRetailer', 'widget_retailer', 'widget_id', 'retailer_id');
    }

    /**
     * A widget belongs to a creator
     */
    public function creator()
    {
        return $this->belongsTo('User', 'created_by', 'user_id');
    }

    /**
     * A widget belongs to a modifier
     */
    public function modifier()
    {
        return $this->belongsTo('User', 'modified_by', 'user_id');
    }

    /**
     * Widgets belongs to retailer ids.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param array $retailerIds
     */
    public function scopeRetailerIds($query, array $retailerIds)
    {
        return $query->select('widgets.*')
                     ->join('widget_retailer',
                           'widget_retailer.widget_id',
                           '=',
                           'widgets.widget_id'
                     )->whereIn('widget_retailer.retailer_id', $retailerIds);
    }

    /**
     * Widget has many media
     */
    public function media()
    {
        return $this->belongsTo('Media', 'widget_id', 'object_id')
                    ->where('object_name', 'widget');
    }

    /**
     * Widget has many home widget media
     */
    public function mediaHomeWidget()
    {
        return $this->media()->where('media_name_id', 'home_widget');
    }

    public function scopeJoinRetailer()
    {
        return $this->select('widgets.*')
                    ->join('widget_retailer', 'widget_retailer.widget_id', '=', 'widgets.widget_id')
                    ->groupBy('widgets.widget_id');
    }
}
