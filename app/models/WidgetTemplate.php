<?php
/**
 * Model for table `widget_templates`.
 *
 * @author Ahmad Anshori <ahmad@dominopos.com>
 */
class WidgetTemplate extends Eloquent
{
    protected $primaryKey = 'widget_template_id';
    protected $table = 'widget_templates';

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;
}