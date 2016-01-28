<?php
/**
 * Model for table `widget_group_names`.
 *
 * @author Rio Astamal <rio@dominopos.com>
 */
class WidgetGroupName extends Eloquent
{
    protected $primaryKey = 'widget_group_name_id';
    protected $table = 'widget_group_names';

    /**
     * Has many for table widget clicks
     */
    public function campaignPopupViews()
    {
        return $this->hasMany('WidgetClick', 'widget_group_name_id', 'widget_group_name_id');
    }
}