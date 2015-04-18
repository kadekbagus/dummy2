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

    public function mall()
    {
        return $this->belongsTo('Retailer', 'merchant_id', 'merchant_id')->isMall();
    }

    public function retailers()
    {
        return $this->belongsToMany('Retailer', 'event_retailer', 'event_id', 'retailer_id')
            ->withPivot('object_type')
            ->where('merchants.is_mall', 'no')
            ->where('event_retailer.object_type', 'retailer');
    }

    public function retailerCategories()
    {
        return $this->belongsToMany('Category', 'event_retailer', 'event_id', 'retailer_id')
            ->withPivot('object_type')
            ->where('event_retailer.object_type', 'retailer_category');
    }

    public function promotions()
    {
        return $this->belongsToMany('News', 'event_retailer', 'event_id', 'retailer_id')
            ->withPivot('object_type')
            ->where('news.object_type', 'promotion')
            ->where('event_retailer.object_type', 'promotion');
    }

    public function news()
    {
        return $this->belongsToMany('News', 'event_retailer', 'event_id', 'retailer_id')
            ->withPivot('object_type')
            ->where('news.object_type', 'news')
            ->where('event_retailer.object_type', 'news');
    }

    public function creator()
    {
        return $this->belongsTo('User', 'created_by', 'user_id');
    }

    public function modifier()
    {
        return $this->belongsTo('User', 'modified_by', 'user_id');
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
