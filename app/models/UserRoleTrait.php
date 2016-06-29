<?php
/**
 * Traits for storing role method that used by User
 *
 * @author Ahmad Anshori <ahmad@dominopos.com>
 */
trait UserRoleTrait
{
    /**
     * Flag to incidate whether the prepareMerchant() has been called.
     *
     * @var boolean
     */
    protected $prepareMerchantCalled = FALSE;

    /**
     * Flag to incidate whether the prepareEmployeeRetailer() has been called.
     *
     * @var boolean
     */
    protected $prepareEmployeeRetailerCalled = FALSE;


    /**
     * Filter User by Consumer Role
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @return Illuminate\Database\Query\Builder
     */
    public function scopeConsumers($query)
    {
        return $query->whereHas('role', function($q){
            $q->where('role_name', '=', 'consumer');
        });
    }

    /**
     * Filter User by Merchant Role
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @return Illuminate\Database\Query\Builder
     */
    public function scopeMerchantOwners($query)
    {
        return $query->whereHas('role', function($q){
            $q->where('role_name', '=', 'merchant owner');
        });
    }

    /**
     * Query builder for joining consumer to user_details table, so it can be
     * filtered based on merchant_id or retailer_id (shop).
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return Illuminate\Database\Query\Builder
     */
    public function scopePrepareMerchant($query)
    {
        // Set the flag to TRUE, so it will not be called multiple times implicitly
        $this->prepareMerchantCalled = TRUE;

        return $query->select('users.*')
                     ->leftJoin('user_details', 'users.user_id', '=', 'user_details.user_id');
    }

    /**
     * Filter consumer based on merchant id.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return Illuminate\Database\Query\Builder
     */
    public function scopeMerchantIds($query, $ids)
    {
        if ($this->prepareMerchantCalled === FALSE) {
            $this->scopePrepareMerchant($query);
        }

        // If the ids not array try to split by comma
        // the input should be in format i.e. '1,2,3'
        if (! is_array($ids)) {
            $ids = explode(',', (string)$ids);
            $ids = array_map('trim', $ids);
        }

        if (! empty($ids)) {
            return $query->whereIn('user_details.merchant_id', $ids);
        }

        return $query;
    }

    /**
     * Filter consumer based on retailer id.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return Illuminate\Database\Query\Builder
     */
    public function scopeRetailerIds($query, $ids)
    {
        if ($this->prepareMerchantCalled === FALSE) {
            $this->scopePrepareMerchant($query);
        }

        // If the ids not array try to split by comma
        // the input should be in format i.e. '1,2,3'
        if (! is_array($ids)) {
            $ids = explode(',', (string)$ids);
            $ids = array_map('trim', $ids);
        }

        if (! empty($ids)) {
            return $query->whereIn('user_details.retailer_id', $ids);
        }

        return $query;
    }

    /**
     * Query builder for joining employee to to employee_retailer table,
     * so it can be filtered based on merchant_id or retailer_id (shop).
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return Illuminate\Database\Query\Builder
     */
    public function scopePrepareEmployeeRetailer($query)
    {
        // Set the flag to TRUE, so it will not be called multiple times implicitly
        $this->prepareEmployeeRetailerCalled = TRUE;

        return $query->select('users.*')
                     ->join('employees', 'employees.user_id', '=', 'users.user_id')
                     ->join('employee_retailer', 'employees.employee_id', '=', 'employee_retailer.employee_id');
    }

    /**
     * Filter employee based on retailer id.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return Illuminate\Database\Query\Builder
     */
    public function scopeEmployeeRetailerIds($query, $ids)
    {
        if ($this->prepareEmployeeRetailerCalled === FALSE) {
            $this->scopePrepareEmployeeRetailer($query);
        }

        // If the ids not array try to split by comma
        // the input should be in format i.e. '1,2,3'
        if (! is_array($ids)) {
            $ids = explode(',', (string)$ids);
            $ids = array_map('trim', $ids);
        }

        if (! empty($ids)) {
            return $query->whereIn('employee_retailer.retailer_id', $ids);
        }

        return $query;
    }

    /**
     * Filter employee based on merchant id.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return Illuminate\Database\Query\Builder
     */
    public function scopeEmployeeMerchantIds($query, $ids)
    {
        if ($this->prepareEmployeeRetailerCalled === FALSE) {
            $this->scopePrepareEmployeeRetailer($query);
        }

        // If the ids not array try to split by comma
        // the input should be in format i.e. '1,2,3'
        if (! is_array($ids)) {
            $ids = explode(',', (string)$ids);
            $ids = array_map('trim', $ids);
        }

        if (! empty($ids)) {
            return $query->join('merchants', function($join) {
                                $join->on('merchants.merchant_id', '=', 'employee_retailer.retailer_id');
                                $join->on('merchants.object_type', '=', DB::raw("'retailer'"));
                       })->whereIn('merchants.parent_id', $ids)
                         ->groupBy('users.user_id');
        }

        return $query;
    }

    /**
     * Filter employee based on employee_id_char.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return Illuminate\Database\Query\Builder
     */
    public function scopeEmployeeIdChars($query, $ids)
    {
        if ($this->prepareEmployeeRetailerCalled === FALSE) {
            $this->scopePrepareEmployeeRetailer($query);
        }

        // If the ids not array try to split by comma
        // the input should be in format i.e. '1,2,3'
        if (! is_array($ids)) {
            $ids = explode(',', (string)$ids);
            $ids = array_map('trim', $ids);
        }

        if (! empty($ids)) {
            return $query->whereIn('employees.employee_id_char', $ids);
        }

        return $query;
    }

    /**
     * Filter employee based on employee_id_char pattern.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return Illuminate\Database\Query\Builder
     */
    public function scopeEmployeeIdCharLike($query, $pattern)
    {
        if ($this->prepareEmployeeRetailerCalled === FALSE) {
            $this->scopePrepareEmployeeRetailer($query);
        }

        return $query->where('emplolyees.employee_id_char', 'like', "%$pattern%");
    }

    /**
     * Filter employee based on their membership_number
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return Illuminate\Database\Query\Builder
     */
    public function scopeMembershipOnly($query)
    {
        $prefix = DB::getTablePrefix();
        return $query->where(function($query) use ($prefix) {
                            $query->whereNotNull('users.membership_number');
                            $query->orWhereRaw("LENGTH({$prefix}membership_number) > 0");
        });
    }

    /**
     * Super admin check.
     *
     * @Todo: Prevent query.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return boolean
     */
    public function isSuperAdmin()
    {
        $superAdmin = 'super admin';

        return strtolower($this->role->role_name) === $superAdmin;
    }

    /**
     * Mall group check.
     *
     * @return boolean
     */
    public function isMallGroup()
    {
        $role = 'mall group';

        return strtolower($this->role->role_name) === $role;
    }

    /**
     * Mall owner check.
     *
     * @return boolean
     */
    public function isMallOwner()
    {
        $role = 'mall owner';

        return strtolower($this->role->role_name) === $role;
    }

    /**
     * Mall admin check.
     *
     * @return boolean
     */
    public function isMallAdmin()
    {
        $role = 'mall admin';

        return strtolower($this->role->role_name) === $role;
    }

    /**
     * Mall customer service check.
     *
     * @return boolean
     */
    public function isMallCS()
    {
        $role = 'mall customer service';

        return strtolower($this->role->role_name) === $role;
    }

    /**
     * Consumer check.
     *
     * @return boolean
     */
    public function isConsumer()
    {
        $role = 'consumer';

        return strtolower($this->role->role_name) === $role;
    }

    /**
     * Campaign Owner check.
     *
     * @return boolean
     */
    public function isCampaignOwner()
    {
        $role = 'campaign owner';

        return strtolower($this->role->role_name) === $role;
    }

    /**
     * Campaign Employee check.
     *
     * @return boolean
     */
    public function isCampaignEmployee()
    {
        $role = 'campaign employee';

        return strtolower($this->role->role_name) === $role;
    }

    /**
     * Campaign Admin check.
     *
     * @return boolean
     */
    public function isCampaignAdmin()
    {
        $role = 'campaign admin';

        return strtolower($this->role->role_name) === $role;
    }

    /**
     * Guest check.
     *
     * @return boolean
     */
    public function isGuest()
    {
        $role = 'guest';

        return strtolower($this->role->role_name) === $role;
    }

    /**
     * Super admin check.
     *
     * @Todo: Prevent query.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @Param string $rolename
     * @return boolean
     */
    public function isRoleName($rolename)
    {
        $rolename = strtolower($rolename);

        return strtolower($this->role->role_name) === $rolename;
    }

    /**
     * Get list of retailer ids owned by this user. This is f*cking wrong,
     * normally I hate doing loop on query.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return array
     */
    public function getMyRetailerIds()
    {
        $merchants = $this->merchants;

        $merchantIds = [];
        foreach ($merchants as $merchant) {
            $merchantIds[] = $merchant->merchant_id;
        }

        if (empty($merchantIds)) {
            return [];
        }

        $retailerIds = DB::table('merchants')->whereIn('parent_id', $merchantIds)
                       ->lists('merchant_id');

        return $retailerIds;
    }

    /**
     * Get list of merchant ids owned by this user. This is f*cking wrong,
     * normally I hate doing loop on query.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return array
     */
    public function getMyMerchantIds()
    {
        $merchants = $this->merchants;

        $merchantIds = [];
        foreach ($merchants as $merchant) {
            $merchantIds[] = $merchant->merchant_id;
        }

        if (empty($merchantIds)) {
            return [];
        }

        return $merchantIds;
    }
}
