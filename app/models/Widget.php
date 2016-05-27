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
        return $this->hasMany('WidgetRetailer', 'widget_id', 'widget_id');
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
        return $this->hasMany('Media', 'object_id', 'widget_id')
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


    /**
     * A widget may have many translations.
     */
    public function translations()
    {
        return $this->hasMany('WidgetTranslation', 'widget_id', 'widget_id')->excludeDeleted()->whereHas('language', function($has) {
            $has->where('merchant_languages.status', 'active');
        });

    }

    /**
     * Add Filter widget based on user who request it.
     * @author kadek <kadek@dominopos.com>
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @param  User $user Instance of object user
     */
    public function scopeAllowedForUser($builder, $user)
    {
         // Roles which are allowed to see all the lucky coupons
        $grantedRoles = ['customer'];

        // Super admin allowed to see all entries
        $superAdmin = Config::get('orbit.security.superadmin');

        if (empty($superAdmin))
        {
            $superAdmin = array('super admin');
        }
        $superAdmin = $superAdmin[0];

        array_push($grantedRoles, $superAdmin);

        // Transform all array into lowercase
        $grantedRoles = array_map('strtolower', $grantedRoles);
        $userRole = trim(strtolower($user->role->role_name));

        if (in_array($userRole, $grantedRoles))
        {   
            // do nothing return as is
            return $builder;
        }

        // If this is not granted roles means we need to do some further check
        $employeeRoles = ['mall admin', 'mall customer service'];

        // table prefix
        $prefix = DB::getTablePrefix();

         // Does this user are employee? if yes then do some check on employees
        // table also to determine whether this employee are allowable to access or not
        if (in_array($userRole, $employeeRoles)) {
            $builder->where(function($query) use ($user, $prefix) {
            $query->whereRaw("{$prefix}widgets.merchant_id in (select er.retailer_id from {$prefix}employees e
                join {$prefix}employee_retailer er on er.employee_id=e.employee_id and e.user_id=? and e.status != ?)", [$user->user_id, "deleted"]);
            });
        } else {
        // This should be mall owner or the mall group
        // Mall group should be able to see all widgets belongs to his mall group and
        // mall owner should be able to see only widgets on his mall
        $builder->where(function($query) use ($user, $prefix) {
            $query->whereRaw("{$prefix}widgets.merchant_id in (select m.merchant_id from {$prefix}merchants m
            where m.object_type='mall' and m.status != 'deleted' and (m.user_id=? or m.parent_id in (
                select m2.merchant_id from {$prefix}merchants m2
                where m2.user_id=? and m2.object_type='mall_group' and m2.status != ?)))", [$user->user_id, $user->user_id, "deleted"]);
            });
        }

        return $builder;
    }

}
