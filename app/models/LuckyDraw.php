<?php
class LuckyDraw extends Eloquent
{
    /**
     * LuckyDraw Model
     *
     * @author Tian <tian@dominopos.com>
     */

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    protected $table = 'lucky_draws';

    protected $primaryKey = 'lucky_draw_id';

    public function mall()
    {
        return $this->belongsTo('Mall', 'mall_id', 'merchant_id');
    }

    public function creator()
    {
        return $this->belongsTo('User', 'created_by', 'user_id');
    }

    public function modifier()
    {
        return $this->belongsTo('User', 'modified_by', 'user_id');
    }

    public function winners()
    {
        return $this->hasMany('LuckyDrawWinner', 'lucky_draw_id', 'lucky_draw_id');
    }

    public function numbers()
    {
        return $this->hasMany('LuckyDrawNumber', 'lucky_draw_id', 'lucky_draw_id');
    }

    public function issuedNumbers()
    {
        return $this->hasMany('LuckyDrawNumber', 'lucky_draw_id', 'lucky_draw_id')
                    ->where(function($query) {
                        $query->whereNotNull('user_id');
                        $query->orWhere('user_id', '!=', 0);
                    });
    }

    public function campaign_status()
    {
        return $this->belongsTo('CampaignStatus', 'campaign_status_id', 'campaign_status_id');
    }

    /**
     * Simple authorization check for whom this object can be accessed.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @important You should only
     * @param \Illuminate\Database\Eloquent\Builder $builder
     * @return \Illuminate\Database\Eloquent\Builder $builder
     */
    public function scopeAllowedFor($builder, $user)
    {
        // Roles which are allowed to see all the lucky draws
        $grantedRoles = ['customer'];

        // Super admin allowed to see all entries
        $superAdmin = Config::get('orbit.security.superadmin');

        // Regular customer allowed to see all entries, strange?
        // why? because we are not planning to separate the API calls
        // all are using the same end point
        // @todo
        // If customer allowed to see, it is better to put it as 'guest' mode?
        if (empty($superAdmin))
        {
            $superAdmin = 'super admin';
        }
        $grantedRoles[] = $superadmin;

        // Transform all array into lowercase
        $grantedRoles = array_map('strtolower', $grantedRoles);
        $userRole = trim(strtolower($user->role->role_name));

        if (in_array($userRole, $grantedRoles)) {
            // do nothing return as is
            return $builder;
        }

        // If this is not granted roles means we need to do some further check
        $employeeRoles = ['mall admin', 'mall customer service'];

        // Does this user are employee? if yes then do some check on employees
        // table also to determine whether this employee are allowable to access or not
        if (in_array($userRole, $employeeRoles)) {
            $builder->where(function($query) use ($user) {
                $query->whereRaw("{$prefix}lucky_draws.mall_id in (select er.retailer_id from {$prefix}employees e
                                 join {$prefix}employee_retailer er on er.employee_id=e.employee_id and e.user_id=? and e.status != ?)", [$user->user_id, 'deleted']);
            });
        } else {
            // This should be mall owner or the mall group
            // Mall group should be able to see all lucky draws belongs to his mall group and
            // mall owner should be able to see only lucky draws on his mall
            $builder->where(function($query) {
                $query->whereRaw("{$prefix}lucky_draws.mall_id in (select m.merchant_id from {$prefix}merchants m
                                  where is_mall='yes' and m.status != 'deleted' and (m.user_id=? or m.parent_id in (
                                  select m2.merchant_id from {$prefix}merchants m2
                                  where m2.user_id=? and m.is_mall='yes' and m.status != ?))", [$user->user_id, $user->user_id, 'deleted']);
            });
        }

        return $builder;
    }

    /**
     * Lucky Draw has many uploaded media.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     */
    public function media()
    {
        return $this->hasMany('Media', 'object_id', 'lucky_draw_id')
                    ->where('object_name', 'lucky_draw');
    }

    /**
     * Join with lucky draw numbers.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     */
    public function scopeJoinLuckyDrawNumbers($query)
    {
        return $query->leftJoin('lucky_draw_numbers', function($join) {
                    $prefix = DB::getTablePrefix();
                    $join->on('lucky_draw_numbers.lucky_draw_id', '=', 'lucky_draws.lucky_draw_id');
                    $join->where('lucky_draw_numbers.status', '!=',
                              DB::raw("'deleted' and {$prefix}lucky_draw_numbers.user_id is not null"));
        });
    }

    /**
     * Join with mall.
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     */
    public function scopeJoinMerchant($query)
    {
        return $query->join('merchants', function($join) {
                    $prefix = DB::getTablePrefix();
                    $join->on('merchants.merchant_id', '=', 'lucky_draws.mall_id');
                    $join->on('merchants.status', '!=',
                              DB::raw("'deleted'"));
        });
    }

    /**
     * Lucky Draw strings can be translated to many languages.
     */
    public function translations()
    {
        return $this->hasMany('LuckyDrawTranslation', 'lucky_draw_id', 'lucky_draw_id')->excludeDeleted()->whereHas('language', function($has) {
            $has->where('merchant_languages.status', 'active');
        });
    }

    /**
     * Lucky Draw announcements
     */
    public function announcements()
    {
        return $this->hasMany('LuckyDrawAnnouncement', 'lucky_draw_id', 'lucky_draw_id');
    }

    /**
     * Lucky Draw prizes
     */
    public function prizes()
    {
        return $this->hasMany('LuckyDrawPrize', 'lucky_draw_id', 'lucky_draw_id');
    }
}
