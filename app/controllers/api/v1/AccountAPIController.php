<?php

use Carbon\Carbon;
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use OrbitShop\API\v1\OrbitShopAPI;

/**
 * The PMP Account controller
 *
 * @author Qosdil A. <qosdil@dominopos.com>
 */
class AccountAPIController extends ControllerAPI
{
    protected $pmpAccountModifiyRoles = ['super admin', 'mall admin', 'mall owner', 'campaign owner', 'campaign admin', 'campaign employee'];

    /**
     * The main method
     *
     * @author Qosdil A. <qosdil@dominopos.com>
     * @author Shelgi <shelgi@dominopos.com>
     * @todo Validation.
     */
    public function getAccount()
    {
        try {
            // Require authentication
            $this->checkAuth();

            // Try to check access control list, does this user allowed to
            // perform this action
            $apiUser = $this->api->user;

            // @Todo: Use ACL authentication instead
            $apiRole = $apiUser->role;
            $validRoles = $this->pmpAccountModifiyRoles;
            if (! in_array( strtolower($apiRole->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            $this->prepareData();

            $this->data->returned_records = count($this->data->records);

            $this->response->data = $this->data;
        } catch (Exception $e) {
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        return $this->render(200);
    }

    public function getAvailableTenantsSelection()
    {
        try {
            // Require authentication
            $this->checkAuth();

            // Try to check access control list, does this user allowed to
            // perform this action
            $apiUser = $this->api->user;

            // @Todo: Use ACL authentication instead
            $apiRole = $apiUser->role;
            $validRoles = $this->pmpAccountModifiyRoles;
            if (! in_array( strtolower($apiRole->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            $availableMerchantIds = UserMerchant::whereIn('object_type', ['mall', 'tenant'])->lists('merchant_id');

            // Retrieve from "merchants" table
            $tenants = CampaignLocation::whereNotIn('merchant_id', $availableMerchantIds)
                                    ->whereIn('object_type', ['mall', 'tenant'])
                                    ->orderBy('name')
                                    ->get();

            $selection = [];
            foreach ($tenants as $tenant) {
                $selection[] = [
                    'id'     => $tenant->merchant_id,
                    'name'   => $tenant->tenant_at_mall,
                    'status' => $tenant->status,
                ];
            }

            $this->response->data = ['row_count' => count($selection), 'available_tenants' => $selection];
        } catch (Exception $e) {
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        return $this->render(200);
    }

    protected function getTenantAtMallArray($tenantIds)
    {
        if ( ! $tenantIds) {
            return [];
        }

        $tenantArray = [];
        foreach (CampaignLocation::whereIn('merchant_id', $tenantIds)->orderBy('name')->get() as $row) {
            $tenantArray[] = ['id' => $row->merchant_id, 'name' => $row->tenant_at_mall, 'status' => $row->status];
        }

        return $tenantArray;
    }

    /**
     * Handle creation and update
     *
     * @author Qosdil A. <qosdil@dominopos.com>
     */
    public function postCreateUpdate()
    {
        try {
            $httpCode = 200;

            // Require authentication
            $this->checkAuth();

            // Try to check access control list, does this user allowed to
            // perform this action
            $apiUser = $this->api->user;

            // @Todo: Use ACL authentication instead
            $apiRole = $apiUser->role;
            $validRoles = $this->pmpAccountModifiyRoles;
            if (! in_array( strtolower($apiRole->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            $this->registerCustomValidation();

            // Do validation
            if (!$this->validate()) {
                return $this->render($this->errorCode);
            }

            // users.user_id
            $this->id = Input::get('id');

            // Save to users table
            $user = ($this->id) ? User::find($this->id) : new User;
            $user->user_firstname = Input::get('user_firstname');
            $user->user_lastname = Input::get('user_lastname');
            $user->user_email = Input::get('user_email');
            $user->username = Input::get('user_email');
            $user->status = Input::get('status');

            if (Input::get('user_password')) {
                $user->user_password = Hash::make(Input::get('user_password'));
            }

            if ( ! $this->id) {
                // Get role ID of "Campaign Owner"
                $roleId = Role::whereRoleName('Campaign Owner')->first()->role_id;

                $user->user_role_id = $roleId;
            }

            $user->save();

            // Save to user_details table (1 to 1)
            $userDetail = ($this->id) ? UserDetail::whereUserId($user->user_id)->first() : new UserDetail;
            $userDetail->user_id = $user->user_id;
            $userDetail->company_name = Input::get('company_name');
            $userDetail->address_line1 = Input::get('address_line1');
            $userDetail->city = Input::get('city');
            $userDetail->province = Input::get('province');
            $userDetail->postal_code = Input::get('postal_code');
            $userDetail->country_id = Input::get('country_id');
            $userDetail->save();

            // Save to campaign_account table (1 to 1)
            $campaignAccount = ($this->id) ? CampaignAccount::whereUserId($user->user_id)->first() : new CampaignAccount;
            $campaignAccount->user_id = $user->user_id;
            $campaignAccount->account_name = Input::get('account_name');
            $campaignAccount->position = Input::get('position');
            $campaignAccount->status = Input::get('status');
            $campaignAccount->save();

            $newEmployee = Employee::where('user_id', '=', $user->user_id)->first() ? Employee::where('user_id', '=', $user->user_id)->first() : new Employee;
            $newEmployee->user_id = $user->user_id;
            $newEmployee->position = Input::get('position');
            $newEmployee->status = Input::get('status');
            $newEmployee->save();

            // Save to user_merchant (1 to M)
            if (Input::get('merchant_ids')) {
                $merchants = UserMerchant::select('merchant_id')->where('user_id', $user->user_id)->get()->toArray();
                $merchantdb = array();
                foreach($merchants as $merchantdbid) {
                    $merchantdb[] = $merchantdbid['merchant_id'];
                }
                $removetenant = array_diff($merchantdb, Input::get('merchant_ids'));
                $addtenant = array_diff(Input::get('merchant_ids'), $merchantdb);
                $newsPromotionActive = 0;
                $couponStatusActive = 0;

                if ($addtenant || $removetenant) {
                    $validator = Validator::make(
                        array(
                            'role_name'    => $user->role->role_name,
                        ),
                        array(
                            'role_name'    => 'in:Campaign Owner',
                        ),
                        array(
                            'role_name.in' => 'Cannot update tenant',
                        )
                    );

                    if ($validator->fails()) {
                        OrbitShopAPI::throwInvalidArgument($validator->messages()->first());
                    }
                }

                $prefix = DB::getTablePrefix();

                if ($removetenant) {
                    foreach ($removetenant as $tenant_id) {
                        $activeCampaign = 0;
                        $newsPromotionActive = 0;
                        $couponStatusActive = 0;

                        $mall = CampaignLocation::select('merchant_id', 'parent_id', 'object_type')->where('merchant_id', '=', $tenant_id)->whereIn('object_type', ['mall', 'tenant'])->first();

                        if (! empty($mall)) {
                            $mallid = '';
                            if ($mall->object_type === 'mall') {
                                $mallid = $mall->merchant_id;
                            } else {
                                $mallid = $mall->parent_id;
                            }

                            $timezone = Mall::leftJoin('timezones','timezones.timezone_id','=','merchants.timezone_id')
                                ->where('merchants.merchant_id','=', $mallid)
                                ->first();

                            $timezoneName = $timezone->timezone_name;

                            $nowMall = Carbon::now($timezoneName);
                            $dateNowMall = $nowMall->toDateString();

                            //get data in news and promotion
                            $newsPromotionActive = News::select('news.news_id')
                                                        ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'news.campaign_status_id')
                                                        ->leftJoin('news_merchant', 'news_merchant.news_id', '=', 'news.news_id')
                                                        ->whereRaw("(CASE WHEN {$prefix}news.end_date < {$this->quote($nowMall)} THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END) NOT IN ('stopped', 'expired')")
                                                        ->where('news_merchant.merchant_id', $tenant_id)
                                                        ->count();

                            //get data in coupon
                            $couponStatusActive = Coupon::select('campaign_status.campaign_status_name')
                                                        ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'promotions.campaign_status_id')
                                                        ->leftJoin('promotion_retailer', 'promotion_retailer.promotion_id', '=', 'promotions.promotion_id')
                                                        ->whereRaw("(CASE WHEN {$prefix}promotions.end_date < {$this->quote($nowMall)} THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END) NOT IN ('stopped', 'expired')")
                                                        ->where('promotion_retailer.retailer_id', $tenant_id)
                                                        ->count();



                            $activeCampaign = (int) $newsPromotionActive + (int) $couponStatusActive;

                            $validator = Validator::make(
                                array(
                                    'active_campaign'  => $activeCampaign,
                                ),
                                array(
                                    'active_campaign'    => 'in: 0',
                                ),
                                array(
                                    'active_campaign.in' => 'Cannot unlink the tenant with an active campaign',
                                )
                            );

                            if ($validator->fails()) {
                                OrbitShopAPI::throwInvalidArgument($validator->messages()->first());
                            }
                        }
                    }
                }

                // get campaign employee and delete merchant
                $employee = CampaignAccount::where('parent_user_id', '=', $user->user_id)->lists('user_id');
                if (! empty($employee)) {
                    $merchantEmployee = UserMerchant::whereIn('user_id', $employee)->delete();
                }

                $ownermerchant = UserMerchant::where('user_id', $user->user_id)->delete();

                // Then update the user_id with the submitted ones
                foreach (Input::get('merchant_ids') as $merchantId) {

                    $userMerchant = new UserMerchant;
                    $userMerchant->user_id = $user->user_id;
                    $userMerchant->merchant_id = $merchantId;
                    $userMerchant->object_type = CampaignLocation::find($merchantId)->object_type;
                    $userMerchant->save();

                    if (! empty($employee)) {
                        foreach ($employee as $emp) {
                            $employeeMerchant = new UserMerchant;
                            $employeeMerchant->user_id = $emp;
                            $employeeMerchant->merchant_id = $merchantId;
                            $employeeMerchant->object_type = CampaignLocation::find($merchantId)->object_type;
                            $employeeMerchant->save();
                        }
                    }
                }
            }

            if ( ! $this->id) {
                // Save to "settings" table
                $setting = new Setting;
                $setting->setting_name = 'agreement_accepted_pmp_account';
                $setting->setting_value = 'false';
                $setting->object_id = $user->user_id;
                $setting->object_type = 'user';
                $setting->save();
            }

            $data = new stdClass();
            $data->id = $user->user_id;

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.news.postnewnews.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.news.postnewnews.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (QueryException $e) {
            Event::fire('orbit.news.postnewnews.query.error', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';

            // Only shows full query error when we are in debug mode
            if (Config::get('app.debug')) {
                $this->response->message = $e->getMessage();
            } else {
                $this->response->message = Lang::get('validation.orbit.queryerror');
            }
            $this->response->data = null;
            $httpCode = 500;

            // Rollback the changes
            $this->rollBack();
        } catch (Exception $e) {
            Event::fire('orbit.news.postnewnews.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = $e->getLine();

            // Rollback the changes
            $this->rollBack();
        }

        return $this->render($httpCode);
    }

    protected function prepareData()
    {
        $data = new stdClass();

        $pmpAccounts = User::excludeDeleted('users')
                            ->pmpAccounts();

        // Filter by mall name
        if (Input::get('mall_name')) {
            $mall = Mall::whereName(Input::get('mall_name'))->first();

            $pmpAccounts = ($mall)
                ? User::ofSpecificMallPmpAccounts($mall->merchant_id)
                : $pmpAccounts->whereUserId('');
        }

        // Join with 'user_details' (one to one)
        $pmpAccounts->join('user_details', 'users.user_id', '=', 'user_details.user_id');

        // Join with 'campaign_account' (1 to 1)
        $pmpAccounts->join('campaign_account', 'users.user_id', '=', 'campaign_account.user_id');

        // Join with 'countries' (1 to 1)
        if (Input::get('location')) {
            $pmpAccounts->leftJoin('countries', 'user_details.country_id', '=', 'countries.country_id');
        }

        // Filter by Account Name
        if (Input::get('account_name')) {
            $pmpAccounts->where('account_name', 'LIKE', '%'.Input::get('account_name').'%');
        }

        // Filter by Company Name
        if (Input::get('company_name')) {
            $pmpAccounts->where('company_name', 'LIKE', '%'.Input::get('company_name').'%');
        }

        // Filter by Location
        if (Input::get('location')) {

            // The following keyword forms handled by the preg_split()
            // "bali"
            // "indonesia"
            // "bali,indonesia"
            // "bali, indonesia"
            // "bali indonesia"
            $keywords = preg_split("/[\s,]+/", Input::get('location'));

            $pmpAccounts->whereCity($keywords[0]);
            switch (count($keywords)) {
                case 2:
                    $pmpAccounts->where('countries.name', $keywords[1]);
                    break;
                default:
                    $pmpAccounts->orWhere('countries.name', $keywords[0]);
                    break;
            }
        }

        // Filter by Status
        if (Input::get('status')) {
            $pmpAccounts->where('campaign_account.status', Input::get('status'));
        }

        // Filter by Creation Date
        if (Input::get('creation_date_from')) {

            // From
            $creationDateTimeFrom = Carbon::createFromFormat('Y-m-d H:i:s', Input::get('creation_date_from'), 'Asia/Singapore')
                ->format('Y-m-d H:i:s');

            $pmpAccounts->where('users.created_at', '>=', $creationDateTimeFrom);

            if (Input::get('creation_date_to')) {

                // To
                $creationDateTimeTo = Carbon::createFromFormat('Y-m-d H:i:s', Input::get('creation_date_to'), 'Asia/Singapore')
                    ->format('Y-m-d H:i:s');

                $pmpAccounts->where('users.created_at', '<=', $creationDateTimeTo);
            }
        }

        // Filter by Role Name
        if (Input::get('role_name')) {
            $pmpAccounts->whereRoleName(Input::get('role_name'));
        }

        // Get total row count
        $allRows = clone $pmpAccounts;
        $data->total_records = $allRows->count();

        if ( ! Input::get('export')) {
            $pmpAccounts->take(Input::get('take'))->skip(Input::get('skip'));
        }

        $sortKey = Input::get('sortby', 'account_name');

        // Prevent ambiguous error
        if ($sortKey == 'created_at') {
            $sortKey = 'users.created_at';
        }

        // Prevent ambiguous error
        if ($sortKey == 'status') {
            $sortKey = 'campaign_account.status';
        }

        $pmpAccounts = $pmpAccounts->orderBy($sortKey, Input::get('sortmode', 'asc'))->get();

        $records = [];
        foreach ($pmpAccounts as $row) {
            $tenantAtMallArray = $this->getTenantAtMallArray($row->userTenants()->lists('merchant_id'));
            $records[] = [
                'account_name' => $row->campaignAccount->account_name,
                'company_name' => $row->company_name,
                'city'         => $row->userDetail->city,
                'role_name'    => $row->role_name,
                'tenant_count' => count($tenantAtMallArray),
                'tenants'      => $tenantAtMallArray,

                // Taken from getUserCreatedAtAttribute() in the model
                //                                                     What is this?
                //                                                         \/
                'created_at'   => $row->user_created_at->setTimezone('Asia/Singapore')->format('d F Y H:i:s'),

                'status'       => $row->campaignAccount->status,
                'id'           => $row->user_id,

                // Needed by frontend for the edit page
                'user_firstname' => $row->user_firstname,
                'user_lastname'  => $row->user_lastname,
                'position'       => $row->campaignAccount->position,
                'user_email'     => $row->user_email,
                'address_line1'  => $row->userDetail->address_line1,
                'province'       => $row->userDetail->province,
                'postal_code'    => $row->userDetail->postal_code,
                'country'        => (object) ['id' => $row->userDetail->country_id, 'name' => @$row->userDetail->userCountry->name],
                'country_name'   => @$row->userDetail->userCountry->name,
            ];
        }

        $data->columns = Config::get('account.listColumns');
        $data->records = $records;

        $this->data = $data;
    }

    protected function validate()
    {
        $fields = [
            'user_firstname' => Input::get('user_firstname'),
            'user_lastname'  => Input::get('user_lastname'),
            'user_email'     => Input::get('user_email'),
            'account_name'   => Input::get('account_name'),
            'status'         => Input::get('status'),
            'company_name'   => Input::get('company_name'),
            'address_line1'  => Input::get('address_line1'),
            'city'           => Input::get('city'),
            'country_id'     => Input::get('country_id'),
            'merchant_ids'   => Input::get('merchant_ids'),
        ];

        if (Input::get('id')) {
            $fields['id'] = Input::get('id');
        } else {
            $fields['user_password'] = Input::get('user_password');
        }

        $rules = [
            'user_firstname' => 'required',
            'user_lastname'  => 'required',
            'user_email'     => 'required|email',
            'account_name'   => 'required',
            'status'         => 'in:active,inactive',
            'company_name'   => 'required',
            'address_line1'  => 'required',
            'city'           => 'required',
            'country_id'     => 'required',
            'merchant_ids'   => 'required|array|exists:merchants,merchant_id',
        ];

        if (Input::get('id')) {
            $rules['id'] = 'exists:users,user_id';
        } else {
            $rules['user_password'] = 'required';
            $rules['user_email'] .= '|orbit.exists.username';
            $rules['account_name'] .= '|unique:campaign_account,account_name';
        }

        $validator = Validator::make($fields, $rules);

        try {
            if ($validator->fails()) {
                OrbitShopAPI::throwInvalidArgument($validator->messages()->first());
            }
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();

            $this->errorCode = 400;
            return false;
        }

        return true;
    }

    protected function registerCustomValidation()
    {
        // Check username, it should not exists
        Validator::extend('orbit.exists.username', function ($attribute, $value, $parameters) {
            $user = User::excludeDeleted()
                        ->where(function ($q) use ($value) {
                            $q->where('user_email', '=', $value)
                              ->orWhere('username', '=', $value);
                          })
                        ->whereIn('user_role_id', function ($q) {
                                $q->select('role_id')
                                  ->from('roles')
                                  ->whereNotIn('role_name', ['Consumer','Guest']);
                          })
                        ->first();

            if (! empty($user)) {
                OrbitShopAPI::throwInvalidArgument('The email address has already been taken');
            }

            return TRUE;
        });
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }

}
