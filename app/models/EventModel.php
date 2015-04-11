<?php
class EventModel extends Eloquent
{
    /**
     * Event Model
     *
     * @author Tian <tian@dominopos.com>
     */

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    protected $table = 'events';

    protected $primaryKey = 'event_id';

    public function merchant()
    {
        return $this->belongsTo('Merchant', 'merchant_id', 'merchant_id');
    }

    public function linkproduct()
    {
        return $this->belongsTo('Product', 'link_object_id1', 'product_id');
    }

    public function linkcategory1()
    {
        return $this->belongsTo('Category', 'link_object_id1', 'category_id');
    }

    public function linkcategory2()
    {
        return $this->belongsTo('Category', 'link_object_id2', 'category_id');
    }

    public function linkcategory3()
    {
        return $this->belongsTo('Category', 'link_object_id3', 'category_id');
    }

    public function linkcategory4()
    {
        return $this->belongsTo('Category', 'link_object_id4', 'category_id');
    }

    public function linkcategory5()
    {
        return $this->belongsTo('Category', 'link_object_id5', 'category_id');
    }

    public function linkpromotion()
    {
        return $this->belongsTo('Promotion', 'link_object_id1', 'promotion_id');
    }

    public function linkwidget()
    {
        return $this->belongsTo('Widget', 'link_object_id1', 'widget_id');
    }

    public function creator()
    {
        return $this->belongsTo('User', 'created_by', 'user_id');
    }

    public function modifier()
    {
        return $this->belongsTo('User', 'modified_by', 'user_id');
    }

    public function retailers()
    {
        return $this->belongsToMany('Retailer', 'event_retailer', 'event_id', 'retailer_id');
    }

    /**
     * Add Filter events based on user who request it.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @param  User $user Instance of object user
     */
    public function scopeAllowedForUser($builder, $user)
    {
        // Super admin allowed to see all entries
        $superAdmin = Config::get('orbit.security.superadmin');
        if (empty($superAdmin))
        {
            $superAdmin = array('super admin');
        }

        // Transform all array into lowercase
        $superAdmin = array_map('strtolower', $superAdmin);
        $userRole = trim(strtolower($user->role->role_name));
        if (in_array($userRole, $superAdmin))
        {
            // do nothing return as is
            return $builder;
        }

        // This will filter only events which belongs to merchant
        // The merchant owner has an ability to view all events
        $builder->where(function($query) use ($user)
        {
            $prefix = DB::getTablePrefix();
            $query->whereRaw("{$prefix}events.merchant_id in (select m2.merchant_id from {$prefix}merchants m2
                                where m2.user_id=? and m2.object_type='merchant')", array($user->user_id));
        });

        return $builder;
    }

    /**
     * Add Filter events based on user who request it. (used for view only,
     * mainly for merchant portal. Weird? yeah it's fucking weird. I'm in
     * hurry.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @param  User $user Instance of object user
     */
    public function scopeAllowedForViewOnly($builder, $user)
    {
        // Super admin allowed to see all entries
        $superAdmin = Config::get('orbit.security.superadmin');
        if (empty($superAdmin))
        {
            $superAdmin = array('super admin');
        }

        // Transform all array into lowercase
        $superAdmin = array_map('strtolower', $superAdmin);

        // Add also consumer, they will need it on customer portal
        $superAdmin[] = 'consumer';

        $userRole = trim(strtolower($user->role->role_name));
        if (in_array($userRole, $superAdmin))
        {
            // do nothing return as is
            return $builder;
        }

        // This will filter only events which belongs to merchant
        // The merchant owner has an ability to view all events
        $builder->where(function($query) use ($user)
        {
            $prefix = DB::getTablePrefix();
            $query->whereRaw("{$prefix}events.merchant_id in (select m2.merchant_id from {$prefix}merchants m2
                                where m2.user_id=? and m2.object_type='merchant')", array($user->user_id));
        });

        return $builder;
    }

    /**
     * Event has many uploaded media.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function media()
    {
        return $this->hasMany('Media', 'object_id', 'event_id')
                    ->where('object_name', 'event');
    }

    /**
     * Accessor for empty product image
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @param string $value - image path
     * @return string $value
     */
    public function getImageAttribute($value)
    {
        if (empty($value)) {
            return 'mobile-ci/images/default_product.png';
        }
        return ($value);
    }
}
