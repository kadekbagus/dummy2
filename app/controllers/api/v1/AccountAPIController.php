<?php

/**
 * An API controller for managing user.
 */
use Carbon\Carbon;
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use Helper\EloquentRecordCounter as RecordCounter;

/**
 * The PMP Account controller
 *
 * @author Qosdil A. <qosdil@dominopos.com>
 */
class AccountAPIController extends ControllerAPI
{
    protected $pmpAccountModifiyRoles = ['super admin', 'mall admin', 'mall owner', 'campaign owner', 'campaign admin', 'campaign employee'];

    protected $valid_role    = NULL;
    protected $valid_country = NULL;
    protected $valid_account_type = NULL;
    protected $valid_lang = NULL;
    protected $allow_select_all_tenant = ['Dominopos', '3rd Party', 'Master'];

    /**
     * The main method
     *
     * @author Ahmad <ahmad@dominopos.com>
     */
    public function getAccountDetail()
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

            $campaignAccountId = OrbitInput::get('campaign_account_id');

            $validator = Validator::make(
                array(
                    'campaign_account_id'             => $campaignAccountId
                ),
                array(
                    'campaign_account_id'             => 'required'
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $account = $this->prepareData($campaignAccountId);


            $this->response->data = $account;
        } catch (QueryException $e) {
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
        } catch (Exception $e) {
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        return $this->render(200);
    }

    /**
     * The main method
     *
     * @author Qosdil A. <qosdil@dominopos.com>
     * @author Shelgi <shelgi@dominopos.com>
     * @author Ahmad <ahmad@dominopos.com>
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

            $prefix = DB::getTablePrefix();

            $accounts = CampaignAccount::excludeDeleted('campaign_account')
                ->select(
                    'campaign_account_id',
                    'account_name',
                    'type_name',
                    'company_name',
                    'city',
                    DB::raw("{$prefix}countries.name as country_name"),
                    'role_name',
                    DB::raw("count({$prefix}user_merchant.user_merchant_id) as tenant_count"),
                    'campaign_account.status'
                )
                ->leftJoin('account_types', 'account_types.account_type_id', '=', 'campaign_account.account_type_id')
                ->leftJoin('users', 'users.user_id', '=', 'campaign_account.user_id')
                ->leftJoin('user_details', 'users.user_id', '=', 'user_details.user_id')
                ->leftJoin('roles', 'users.user_role_id', '=', 'roles.role_id')
                ->leftJoin('countries', 'countries.country_id', '=', 'user_details.country_id')
                ->leftJoin('user_merchant', 'user_merchant.user_id', '=', 'campaign_account.user_id');

            // Filter by account type id
            OrbitInput::get('account_type_id', function ($account_type_id) use ($accounts) {
                $accounts->where('account_types.account_type_id', '=', $account_type_id);
            });

            // Filter by account type name
            OrbitInput::get('account_type_name', function ($account_type_name) use ($accounts) {
                $accounts->where('account_types.type_name', 'LIKE', '%' . $account_type_name . '%');
            });

            // Filter by Account Name
            if (Input::get('account_name')) {
                $accounts->where('account_name', 'LIKE', '%' . Input::get('account_name') . '%');
            }

            // Filter by Company Name
            if (Input::get('company_name')) {
                $accounts->where('company_name', 'LIKE', '%' . Input::get('company_name') . '%');
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

                $accounts->whereCity($keywords[0]);
                switch (count($keywords)) {
                    case 2:
                        $accounts->where('countries.name', $keywords[1]);
                        break;
                    default:
                        $accounts->orWhere('countries.name', $keywords[0]);
                        break;
                }
            }

            // Filter by Status
            if (OrbitInput::get('status')) {
                $accounts->where('campaign_account.status', OrbitInput::get('status'));
            }

            // Filter by Role Name
            if (OrbitInput::get('role_name')) {
                $accounts->whereRoleName(OrbitInput::get('role_name'));
            }

            $accounts->groupBy('campaign_account.campaign_account_id');

            $_accounts = clone($accounts);

            $totalRec = RecordCounter::create($_accounts)->count();

            $accounts->take(OrbitInput::get('take', 15))
                ->skip(OrbitInput::get('skip', 0));

            $sortKey = OrbitInput::get('sortby', 'account_name');

            // Prevent ambiguous error
            if ($sortKey == 'created_at') {
                $sortKey = 'users.created_at';
            }

            // Prevent ambiguous error
            if ($sortKey == 'status') {
                $sortKey = 'campaign_account.status';
            }

            $accounts->orderBy($sortKey, OrbitInput::get('sortmode', 'asc'));

            $data = new stdClass();
            $data->records = $accounts->get();

            $data->returned_records = count($data->records);
            $data->total_records = $totalRec;

            $this->response->data = $data;
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

    protected function getTenantAtMallArray($type_name, $user_id = NULL)
    {
        $permission = [
                'Mall'      => 'mall',
                'Merchant'  => 'tenant',
                'Agency'    => 'mall_tenant',
                '3rd Party' => 'mall',
                'Dominopos' => 'mall_tenant',
                'Master'    => 'mall_tenant'
            ];

        $prefix = DB::getTablePrefix();
        $get_tenants = CampaignLocation::select(DB::raw("COUNT(DISTINCT {$prefix}merchants.merchant_id) as total_location"));

        // access
        if (array_key_exists($type_name, $permission)) {
            $access = explode("_", $permission[$type_name]);
            $get_tenants->whereIn('object_type', $access);
        }

        // filter
        if (! is_null($user_id)) {
            $get_tenants->excludeDeleted()
                        ->whereRaw("
                            EXISTS (
                                SELECT 1
                                FROM {$prefix}user_merchant um
                                JOIN {$prefix}campaign_account ca
                                    ON ca.user_id = um.user_id
                                JOIN {$prefix}account_types at
                                    ON at.account_type_id = ca.account_type_id
                                    AND at.status = 'active'
                                WHERE {$prefix}merchants.merchant_id = um.merchant_id
                                    AND ca.user_id = {$this->quote($user_id)}
                                GROUP BY um.merchant_id
                            )
                        ");
        } else {
            $get_tenants->where('status', 'active');
        }

        $get_tenants = $get_tenants->get();

        $tenantArray = $get_tenants[0]->total_location;

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

            // Successfull Creation
            $activityNotes = sprintf('PMP Account Created: %s', $newmall->name);
            $activity->setUser($user)
                    ->setActivityName('create_mall')
                    ->setActivityNameLong('Create Mall OK')
                    ->setObject($newmall)
                    ->setNotes($activityNotes)
                    ->responseOK();
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

    protected function prepareData($campaignAccountId)
    {
        $data = new stdClass();

        $prefix = DB::getTablePrefix();
        $pmpAccounts = User::excludeDeleted('users')
                        // ->join('roles', 'users.user_role_id', '=', 'roles.role_id')->whereIn('role_name', ['Campaign Owner', 'Campaign Employee', 'Campaign Admin']);
                            ->pmpAccounts();

        // Join with 'user_details' (one to one)
        $pmpAccounts->join('user_details', 'users.user_id', '=', 'user_details.user_id');

        // Join with 'campaign_account' (1 to 1)
        $pmpAccounts->join('campaign_account', 'users.user_id', '=', 'campaign_account.user_id');

        // Join with 'account_types' (1 to 1)
        $pmpAccounts->leftJoin('account_types', 'campaign_account.account_type_id', '=', 'account_types.account_type_id');

        // Join with 'countries' (1 to 1)
        if (Input::get('location')) {
            $pmpAccounts->leftJoin('countries', 'user_details.country_id', '=', 'countries.country_id');
        }

        $pmpAccounts->select( '*',
                DB::raw("
                        (SELECT count(n.news_id) as total
                        FROM {$prefix}news_translations nt
                        INNER JOIN {$prefix}news n
                            ON n.news_id = nt.news_id
                        LEFT JOIN {$prefix}user_campaign AS uc
                            ON uc.campaign_id = n.news_id
                        LEFT JOIN {$prefix}campaign_account AS ca
                            ON ca.user_id = uc.user_id
                        LEFT JOIN {$prefix}campaign_account AS cas
                            ON cas.parent_user_id = ca.parent_user_id
                        INNER JOIN {$prefix}object_supported_language osl
                            ON osl.object_id = ca.campaign_account_id
                        WHERE nt.status != 'deleted'
                            AND (ca.user_id = {$prefix}campaign_account.parent_user_id
                                OR ca.parent_user_id = {$prefix}campaign_account.parent_user_id
                                OR ca.user_id = {$prefix}campaign_account.user_id
                                OR ca.parent_user_id = {$prefix}campaign_account.user_id)
                            AND osl.language_id = (select lx.language_id from {$prefix}languages lx where lx.name = {$prefix}campaign_account.mobile_default_language)
                            AND osl.status = 'active'
                        GROUP BY n.news_id
                        LIMIT 1

                        union

                        SELECT count(c.promotion_id) as total
                        FROM {$prefix}coupon_translations ct
                        INNER JOIN {$prefix}promotions c
                            ON c.promotion_id = ct.promotion_id
                        LEFT JOIN {$prefix}user_campaign AS uc
                            ON uc.campaign_id = c.promotion_id
                        LEFT JOIN {$prefix}campaign_account AS ca
                            ON ca.user_id = uc.user_id
                        LEFT JOIN {$prefix}campaign_account AS cas
                            ON cas.parent_user_id = ca.parent_user_id
                        INNER JOIN {$prefix}object_supported_language osl
                            ON osl.object_id = ca.campaign_account_id
                        WHERE ct.status != 'deleted'
                            AND (ca.user_id = {$prefix}campaign_account.parent_user_id
                                OR ca.parent_user_id = {$prefix}campaign_account.parent_user_id
                                OR ca.user_id = {$prefix}campaign_account.user_id
                                OR ca.parent_user_id = {$prefix}campaign_account.user_id)
                            AND osl.language_id = (SELECT lx.language_id FROM {$prefix}languages lx where lx.name = {$prefix}campaign_account.mobile_default_language)
                            AND osl.status = 'active'
                        GROUP BY c.promotion_id
                        LIMIT 1
                        ) as total_campaign
                    ")
            );
        $pmpAccounts = $pmpAccounts->where('campaign_account.campaign_account_id', $campaignAccountId)->firstOrFail();

        if ($pmpAccounts->campaignAccount->is_link_to_all === 'Y' && in_array($pmpAccounts->type_name, $this->allow_select_all_tenant)) {
            $tenantAtMallArray = $this->getTenantAtMallArray($pmpAccounts->type_name);
        } else {
            $tenantAtMallArray = $this->getTenantAtMallArray($pmpAccounts->type_name, $pmpAccounts->user_id);
        }

        $disable_mobile_default_language = ($pmpAccounts->total_campaign > 0) ? true : false;

        $pmpAccounts->account_name = $pmpAccounts->campaignAccount->account_name;
        $pmpAccounts->company_name = $pmpAccounts->company_name;
        $pmpAccounts->city = $pmpAccounts->userDetail->city;
        $pmpAccounts->role_name = $pmpAccounts->role_name;
        $pmpAccounts->account_type_id = $pmpAccounts->campaignAccount->account_type_id;
        $pmpAccounts->type_name = $pmpAccounts->type_name;
        $pmpAccounts->select_all_tenants = $pmpAccounts->campaignAccount->is_link_to_all;
        $pmpAccounts->is_subscribed = $pmpAccounts->campaignAccount->is_subscribed;
        $pmpAccounts->mobile_default_language = $pmpAccounts->campaignAccount->mobile_default_language;
        $pmpAccounts->phone = $pmpAccounts->campaignAccount->phone;
        $pmpAccounts->disable_mobile_default_language = $disable_mobile_default_language;
        $pmpAccounts->tenant_count = $tenantAtMallArray;

        $pmpAccounts->status = $pmpAccounts->campaignAccount->status;
        $pmpAccounts->id = $pmpAccounts->user_id;

            // Needed by frontend for the edit page
        $pmpAccounts->user_firstname = $pmpAccounts->user_firstname;
        $pmpAccounts->user_lastname = $pmpAccounts->user_lastname;
        $pmpAccounts->position = $pmpAccounts->campaignAccount->position;
        $pmpAccounts->user_email = $pmpAccounts->user_email;
        $pmpAccounts->address_line1 = $pmpAccounts->userDetail->address_line1;
        $pmpAccounts->province = $pmpAccounts->userDetail->province;
        $pmpAccounts->postal_code = $pmpAccounts->userDetail->postal_code;
        $pmpAccounts->country = (object) ['id' => $pmpAccounts->userDetail->country_id, 'name' => @$pmpAccounts->userDetail->userCountry->name];
        $pmpAccounts->country_name = @$pmpAccounts->userDetail->userCountry->name;
        $pmpAccounts->pmp_languages = $pmpAccounts->campaignAccount->pmpLanguages;

        return $pmpAccounts;
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


    /**
     * Handle creation new pmp account
     *
     * @author Irianto <irianto@dominopos.com>
    */
    public function postNewAccount()
    {
        $activity = Activity::portal()
                            ->setActivityType('create');

        $apiUser = NULL;
        $new_user = NULL;

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

            $user_firstname  = OrbitInput::post('user_firstname');
            $user_lastname   = OrbitInput::post('user_lastname');
            $user_email      = OrbitInput::post('user_email');
            $account_name    = OrbitInput::post('account_name');
            $status          = OrbitInput::post('status');
            $company_name    = OrbitInput::post('company_name');
            $address_line1   = OrbitInput::post('address_line1');
            $city            = OrbitInput::post('city');
            $country_id      = OrbitInput::post('country_id');
            $merchant_ids    = OrbitInput::post('merchant_ids', []);
            $account_type_id = OrbitInput::post('account_type_id');
            $user_password   = OrbitInput::post('user_password');
            $role_name       = OrbitInput::post('role_name');
            $province        = OrbitInput::post('province');
            $postal_code     = OrbitInput::post('postal_code');
            $position        = OrbitInput::post('position');
            $phone           = OrbitInput::post('phone');

            $languages = OrbitInput::post('languages', []);
            $mobile_default_language = OrbitInput::post('mobile_default_language');

            // select all link to tenant just for 3rd and dominopos
            $select_all_tenants = OrbitInput::post('select_all_tenants', 'N');

            $is_subscribed = OrbitInput::post('is_subscribed', 'N');

            // for handle empty string cause select all tenant is not required
            if ($is_subscribed !== 'Y') {
                $is_subscribed = 'N';
            }

            // split validation account type for support select all tenant for account type 3rd and dominopos
            $validator = Validator::make(
                array(
                    'account_type_id'       => $account_type_id,
                ),
                array(
                    'account_type_id'       => 'required|orbit.empty.account_type',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();

                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $account_type = $this->valid_account_type;

            $validation_data = [
                'is_subscribed'           => $is_subscribed,
                'select_all_tenants'      => $select_all_tenants,
                'user_firstname'          => $user_firstname,
                'user_lastname'           => $user_lastname,
                'user_email'              => $user_email,
                'account_name'            => $account_name,
                'status'                  => $status,
                'company_name'            => $company_name,
                'address_line1'           => $address_line1,
                'city'                    => $city,
                'country_id'              => $country_id,
                'merchant_ids'            => $merchant_ids,
                'user_password'           => $user_password,
                'role_name'               => $role_name,
                'languages'               => $languages,
                'mobile_default_language' => $mobile_default_language,
            ];

            $validation_error = [
                'is_subscribed'           => 'in:N,Y',
                'select_all_tenants'      => 'in:N,Y|orbit.access.select_all_tenants:' . $account_type->type_name,
                'user_firstname'          => 'required',
                'user_lastname'           => 'required',
                'user_email'              => 'required|email|orbit.exists.username',
                'account_name'            => 'required|unique:campaign_account,account_name',
                'status'                  => 'required|in:active,inactive',
                'company_name'            => 'required',
                'address_line1'           => 'required',
                'city'                    => 'required',
                'country_id'              => 'required|orbit.empty.country',
                'merchant_ids'            => 'required_if:status,active|array|exists:merchants,merchant_id|orbit.exists.link_to_tenant',
                'user_password'           => 'required|min:6',
                'role_name'               => 'required|in:Campaign Owner,Campaign Admin|orbit.empty.role:' . $account_type->type_name,
                'languages'               => 'required|array',
                'mobile_default_language' => 'required|size:2|orbit.formaterror.language',
            ];

            if ($select_all_tenants === 'Y') {
                if (in_array($account_type->type_name, $this->allow_select_all_tenant)) {
                    unset($validation_data['merchant_ids']);
                    unset($validation_error['merchant_ids']);
                }
            } else {
                // for handle empty string cause select all tenant is not required
                $select_all_tenants = 'N';

                // account type master must linkt to all tenants
                if ($account_type->type_name === 'Master') {
                    OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.access.master_link_to_tenant'));
                }
            }

            $validator = Validator::make(
                $validation_data,
                $validation_error,
                array(
                    'user_password.min' => Lang::get('validation.orbit.formaterror.min'),
                    'orbit.empty.role' => Lang::get('validation.orbit.empty.role_name')
                )
            );

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();

                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $role_campaign_owner = $this->valid_role;
            // new user pmp campaign owner
            $new_user                 = new User();
            $new_user->user_firstname = $user_firstname;
            $new_user->user_lastname  = $user_lastname;
            $new_user->user_email     = $user_email;
            $new_user->username       = $user_email;
            $new_user->status         = $status;
            $new_user->user_password  = Hash::make($user_password);

            $new_user->user_role_id = $role_campaign_owner->role_id;

            $new_user->save();

            $country = $this->valid_country;
            // new user detail
            $new_user_detail                = new UserDetail();
            $new_user_detail->user_id       = $new_user->user_id;
            $new_user_detail->company_name  = $company_name;
            $new_user_detail->address_line1 = $address_line1;
            $new_user_detail->city          = $city;
            $new_user_detail->province      = $province;
            $new_user_detail->postal_code   = $postal_code;

            $new_user_detail->country_id    = $country->country_id;

            $new_user_detail->save();

            // Save to campaign_account table (1 to 1)
            $campaignAccount                  = new CampaignAccount();
            $campaignAccount->user_id         = $new_user->user_id;
            $campaignAccount->account_type_id = $account_type->account_type_id;
            $campaignAccount->account_name    = $account_name;
            $campaignAccount->is_link_to_all  = $select_all_tenants;
            $campaignAccount->is_subscribed   = $is_subscribed;
            $campaignAccount->position        = $position;
            $campaignAccount->phone           = $phone;
            $campaignAccount->status          = $status;

            // check mobile default language must in supported language
            if (in_array($mobile_default_language, $languages)) {
                $campaignAccount->mobile_default_language = $mobile_default_language;
            } else {
                OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.empty.mobile_default_lang'));
            }
            $campaignAccount->save();

            // new employee
            $new_employee = new Employee();
            $new_employee->user_id = $new_user->user_id;
            $new_employee->position = $position;
            $new_employee->status = $status;
            $new_employee->save();

            // Save to user_merchant (1 to M)
            if ($merchant_ids) {
                // Then update the user_id with the submitted ones
                foreach ($merchant_ids as $merchantId) {
                    $userMerchant = new UserMerchant;
                    $userMerchant->user_id = $new_user->user_id;
                    $userMerchant->merchant_id = $merchantId;
                    $userMerchant->object_type = CampaignLocation::find($merchantId)->object_type;
                    $userMerchant->save();
                }
            }

            // languages
            if (count($languages) > 0) {
                foreach ($languages as $language_name) {
                    $validator = Validator::make(
                        array(
                            'language'             => $language_name
                        ),
                        array(
                            'language'             => 'required|size:2|orbit.formaterror.language'
                        )
                    );

                    // Run the validation
                    if ($validator->fails()) {
                        $errorMessage = $validator->messages()->first();
                        OrbitShopAPI::throwInvalidArgument($errorMessage);
                    }

                    $pmp_account_languages = new ObjectSupportedLanguage();
                    $pmp_account_languages->object_id = $campaignAccount->campaign_account_id;
                    $pmp_account_languages->object_type = 'pmp_account';
                    $pmp_account_languages->status = 'active';
                    $pmp_account_languages->language_id = Language::where('name', '=', $language_name)->first()->language_id;
                    $pmp_account_languages->save();
                }
            }

            // Save to "settings" table
            $setting = new Setting;
            $setting->setting_name = 'agreement_accepted_pmp_account';
            $setting->setting_value = 'false';
            $setting->object_id = $new_user->user_id;
            $setting->object_type = 'user';
            $setting->save();

            $this->response->data = $new_user;

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activityNotes = sprintf('PMP Account Created: %s', $new_user->user_email);
            $activity->setUser($apiUser)
                    ->setActivityName('create_pmp_account')
                    ->setActivityNameLong('Create PMP Account OK')
                    ->setObject($new_user)
                    ->setNotes($activityNotes)
                    ->responseOK();
        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($apiUser)
                    ->setActivityName('create_pmp_account')
                    ->setActivityNameLong('Create PMP Account Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($apiUser)
                    ->setActivityName('create_pmp_account')
                    ->setActivityNameLong('Create PMP Account Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
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

            // Creation failed Activity log
            $activity->setUser($apiUser)
                    ->setActivityName('create_pmp_account')
                    ->setActivityNameLong('Create PMP Account Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();

            if (Config::get('app.debug')) {
                $this->response->data = $e->__toString();
            } else {
                $this->response->data = null;
            }

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($apiUser)
                    ->setActivityName('create_pmp_account')
                    ->setActivityNameLong('Create PMP Account Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save the activity
        $activity->save();

        return $this->render($httpCode);
    }

    /**
     * Handle update pmp account
     *
     * @author Irianto <irianto@dominopos.com>
    */
    public function postUpdateAccount()
    {
        $activity = Activity::portal()
                            ->setActivityType('update');

        $apiUser = NULL;
        $update_user = NULL;

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

            $user_id         = OrbitInput::post('id');
            $user_firstname  = OrbitInput::post('user_firstname');
            $user_lastname   = OrbitInput::post('user_lastname');
            $user_email      = OrbitInput::post('user_email');
            $account_name    = OrbitInput::post('account_name');
            $status          = OrbitInput::post('status');
            $company_name    = OrbitInput::post('company_name');
            $address_line1   = OrbitInput::post('address_line1');
            $city            = OrbitInput::post('city');
            $country_id      = OrbitInput::post('country_id');
            $merchant_ids    = OrbitInput::post('merchant_ids');
            $account_type_id = OrbitInput::post('account_type_id');
            $role_name       = OrbitInput::post('role_name');
            $user_password   = OrbitInput::post('user_password');
            $phone           = OrbitInput::post('phone');

            $mobile_default_language = OrbitInput::post('mobile_default_language');
            $languages = OrbitInput::post('languages');
            // select all link to tenant just for 3rd and dominopos
            $select_all_tenants = OrbitInput::post('select_all_tenants', 'N');

            $is_subscribed = OrbitInput::post('is_subscribed', 'N');

            // split validation account type for support select all tenant for account type 3rd and dominopos
            $validator = Validator::make(
                array(
                    'account_type_id'       => $account_type_id,
                ),
                array(
                    'account_type_id'       => 'required|orbit.empty.account_type',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();

                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $account_type = $this->valid_account_type;

            $validation_data = [
                'id'                 => $user_id,
                'is_subscribed'      => $is_subscribed,
                'select_all_tenants' => $select_all_tenants,
                'user_firstname'     => $user_firstname,
                'user_lastname'      => $user_lastname,
                'user_email'         => $user_email,
                'account_name'       => $account_name,
                'status'             => $status,
                'company_name'       => $company_name,
                'address_line1'      => $address_line1,
                'city'               => $city,
                'country_id'         => $country_id,
                'merchant_ids'       => $merchant_ids,
                'role_name'          => $role_name,
                'user_password'      => $user_password,
                'languages'               => $languages,
                'mobile_default_language' => $mobile_default_language,
            ];

            $validation_error = [
                'id'                 => 'required|exists:users,user_id',
                'is_subscribed'      => 'in:N,Y',
                'select_all_tenants' => 'in:N,Y|orbit.access.select_all_tenants:' . $account_type->type_name,
                'user_firstname'     => 'required',
                'user_lastname'      => 'required',
                'user_email'         => 'required|email', // user_email exist but not me
                'account_name'       => 'required', // account name exist but not me
                'status'             => 'in:active,inactive',
                'company_name'       => 'required',
                'address_line1'      => 'required',
                'city'               => 'required',
                'country_id'         => 'required|orbit.empty.country',
                'merchant_ids'       => 'array|exists:merchants,merchant_id|orbit.exists.link_to_tenant',
                'role_name'          => 'required|in:Campaign Owner,Campaign Employee,Campaign Admin|orbit.empty.role:' . $account_type->type_name,
                'user_password'      => 'min:6',
                'languages'               => 'array',
                'mobile_default_language' => 'size:2|orbit.formaterror.language',
            ];

            if ($select_all_tenants === 'Y') {
                if (in_array($account_type->type_name, $this->allow_select_all_tenant)) {
                    unset($validation_data['merchant_ids']);
                    unset($validation_error['merchant_ids']);

                    // delete link if exists on table user merchant
                    $pmp_account = CampaignAccount::where('parent_user_id', '=', $user_id)
                                            ->lists('user_id');
                    array_push($pmp_account, $user_id);

                    $del_user_merchant = UserMerchant::whereIn('user_id', $pmp_account)->delete();
                }
            } else {
                // account type master must linkt to all tenants
                if ($account_type->type_name === 'Master') {
                    OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.access.master_link_to_tenant'));
                }
            }

            $validator = Validator::make(
                $validation_data,
                $validation_error,
                array(
                    'orbit.empty.role'  => Lang::get('validation.orbit.empty.role_name')
                )
            );

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();

                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $role_campaign_owner = $this->valid_role;

            // update user pmp campaign owner
            $update_user = User::excludeDeleted()
                                ->where('user_id', $user_id)
                                ->where('user_role_id', $role_campaign_owner->role_id)
                                ->first();

            OrbitInput::post('user_firstname', function($user_firstname) use ($update_user) {
                $update_user->user_firstname = trim($user_firstname);
            });

            OrbitInput::post('user_lastname', function($user_lastname) use ($update_user) {
                $update_user->user_lastname = trim($user_lastname);
            });

            OrbitInput::post('status', function($status) use ($update_user) {
                $update_user->status = $status;
            });

            OrbitInput::post('user_password', function($user_password) use ($update_user) {
                if (! empty(trim($user_password))) {
                    $update_user->user_password = Hash::make($user_password);
                }
            });

            $update_user->save();

            $country = $this->valid_country;

            // update user detail
            $update_user_detail = UserDetail::where('user_id', $update_user->user_id)
                                            ->first();

            OrbitInput::post('company_name', function($company_name) use ($update_user_detail) {
                $update_user_detail->company_name = $company_name;
            });

            OrbitInput::post('address_line1', function($address_line1) use ($update_user_detail) {
                $update_user_detail->address_line1 = $address_line1;
            });

            OrbitInput::post('city', function($city) use ($update_user_detail) {
                $update_user_detail->city = $city;
            });

            OrbitInput::post('province', function($province) use ($update_user_detail) {
                $update_user_detail->province = $province;
            });

            OrbitInput::post('postal_code', function($postal_code) use ($update_user_detail) {
                $update_user_detail->postal_code = $postal_code;
            });

            OrbitInput::post('country_id', function($country_id) use ($update_user_detail) {
                $update_user_detail->country_id = $country_id;
            });

            OrbitInput::post('gender', function($gender) use ($update_user_detail) {
                $update_user_detail->gender = $gender;
            });

            $update_user_detail->save();

            // Save to campaign_account table (1 to 1)
            $campaignAccount = CampaignAccount::excludeDeleted('campaign_account')
                                            ->where('user_id', $update_user->user_id)
                                            ->first();

            // update pmp employee
            $pmp_employee = CampaignAccount::excludeDeleted()
                                    ->where('parent_user_id', $update_user->user_id)
                                    ->get();

            OrbitInput::post('account_name', function($account_name) use ($campaignAccount, $pmp_employee) {
                $campaignAccount->account_name = $account_name;

                if (count($pmp_employee) > 0) {
                    foreach ($pmp_employee as $employee) {
                        $employee->account_name = $account_name;
                        $employee->save();
                    }
                }
            });

            OrbitInput::post('select_all_tenants', function($select_all_tenants) use ($campaignAccount, $pmp_employee) {
                // for handle empty string cause select all tenant is not required
                if ($select_all_tenants !== 'Y') {
                    $select_all_tenants = 'N';
                }

                $campaignAccount->is_link_to_all = $select_all_tenants;

                if (count($pmp_employee) > 0) {
                    foreach ($pmp_employee as $employee) {
                        $employee->is_link_to_all = $select_all_tenants;
                        $employee->save();
                    }
                }
            });

            OrbitInput::post('is_subscribed', function($is_subscribed) use ($campaignAccount, $pmp_employee) {
                // for handle empty string cause select all tenant is not required
                if ($is_subscribed !== 'Y') {
                    $is_subscribed = 'N';
                }

                $campaignAccount->is_subscribed = $is_subscribed;

                if (count($pmp_employee) > 0) {
                    foreach ($pmp_employee as $employee) {
                        $employee->is_subscribed = $is_subscribed;
                        $employee->save();
                    }
                }
            });

            OrbitInput::post('position', function($position) use ($campaignAccount) {
                $campaignAccount->position = $position;
            });

            OrbitInput::post('phone', function($phone) use ($campaignAccount) {
                $campaignAccount->phone = $phone;
            });

            OrbitInput::post('status', function($status) use ($campaignAccount) {
                $campaignAccount->status = $status;
            });

            OrbitInput::post('mobile_default_language', function($mobile_default_language) use ($campaignAccount, $languages, $update_user) {
                $old_mobile_default_language = $campaignAccount->mobile_default_language;
                if ($old_mobile_default_language !== $mobile_default_language) {
                    $check_lang = Language::excludeDeleted()
                                    ->where('name', $old_mobile_default_language)
                                    ->first();

                    if (! empty($check_lang)) {
                        // news and promotion translation
                        $news_promotion_translation = NewsTranslation::excludeDeleted('news_translations')
                                                ->join('news', 'news.news_id', '=', 'news_translations.news_id')
                                                ->allowedForPMPUser($update_user, 'news_promotion')
                                                ->join('object_supported_language', 'object_supported_language.object_id', '=', DB::raw('ca.campaign_account_id'))
                                                ->where('object_supported_language.language_id', $check_lang->language_id)
                                                ->where('object_supported_language.status', 'active')
                                                ->first();
                        if (empty($news_promotion_translation)) {
                            $errorMessage = Lang::get('validation.orbit.exists.link_mobile_default_lang');
                            OrbitShopAPI::throwInvalidArgument($errorMessage);
                        }

                        // coupon translation
                        $coupon_translation = CouponTranslation::excludeDeleted('coupon_translations')
                                                ->join('promotions', 'promotions.promotion_id', '=', 'coupon_translations.promotion_id')
                                                ->allowedForPMPUser($update_user, 'coupon')
                                                ->join('object_supported_language', 'object_supported_language.object_id', '=', DB::raw('ca.campaign_account_id'))
                                                ->where('object_supported_language.language_id', $check_lang->language_id)
                                                ->where('object_supported_language.status', 'active')
                                                ->first();
                        if (empty($coupon_translation)) {
                            $errorMessage = Lang::get('validation.orbit.exists.link_mobile_default_lang');
                            OrbitShopAPI::throwInvalidArgument($errorMessage);
                        }
                    }
                }

                if (in_array($mobile_default_language, $languages)) {
                    $campaignAccount->mobile_default_language = $mobile_default_language;
                } else {
                    OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.empty.mobile_default_lang'));
                }
            });

            $campaignAccount->save();

            OrbitInput::post('languages', function($languages) use ($campaignAccount, $mobile_default_language) {
                // new languages
                $pmp_account_languages = [];
                foreach ($languages as $language_name) {
                    $validator = Validator::make(
                        array(
                            'language'             => $language_name
                        ),
                        array(
                            'language'             => 'required|size:2|orbit.formaterror.language'
                        )
                    );

                    // Run the validation
                    if ($validator->fails()) {
                        $errorMessage = $validator->messages()->first();
                        OrbitShopAPI::throwInvalidArgument($errorMessage);
                    }

                    $language_data = $this->valid_lang;

                    // check lang
                    $old_pmp_account_languages = ObjectSupportedLanguage::excludeDeleted()
                                                    ->where('object_id', $campaignAccount->campaign_account_id)
                                                    ->where('object_type', 'pmp_account')
                                                    ->where('language_id', $language_data->language_id)
                                                    ->get();

                    if (count($old_pmp_account_languages) > 0) {
                        foreach ($old_pmp_account_languages as $old_pmp_account_language) {
                            $pmp_account_languages[] = $old_pmp_account_language->language_id;
                        }
                    } else {
                        $newpmp_account_language = new ObjectSupportedLanguage();
                        $newpmp_account_language->object_id = $campaignAccount->campaign_account_id;
                        $newpmp_account_language->object_type = 'pmp_account';
                        $newpmp_account_language->status = 'active';
                        $newpmp_account_language->language_id = Language::where('name', '=', $language_name)->first()->language_id;
                        $newpmp_account_language->save();

                        $pmp_account_languages[] = $newpmp_account_language->language_id;
                    }
                }

                // find lang will be delete
                $languages_will_be_delete = ObjectSupportedLanguage::excludeDeleted()
                                                ->where('object_id', $campaignAccount->campaign_account_id)
                                                ->where('object_type', 'pmp_account')
                                                ->whereNotIn('language_id', $pmp_account_languages)
                                                ->get();

                if (count($languages_will_be_delete) > 0) {
                    $del_lang = [];
                    foreach ($languages_will_be_delete as $check_lang) {
                        if ($check_lang->name === $mobile_default_language) {
                            OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.exists.mobile_default_lang'));
                        }

                        //colect language will be delete
                        $del_lang[] = $check_lang->language_id;
                    }
                    if (count($del_lang) > 0) {
                        // delete languages
                        $delete_languages = ObjectSupportedLanguage::excludeDeleted()
                                                ->where('object_id', $campaignAccount->campaign_account_id)
                                                ->where('object_type', 'pmp_account')
                                                ->whereIn('language_id', $del_lang)
                                                ->update(['status' => 'deleted']);
                    }
                }
            });

            // update employee
            $update_employee = Employee::excludeDeleted()
                                        ->where('user_id', $update_user->user_id)
                                        ->first();

            OrbitInput::post('position', function($position) use ($update_employee) {
                $update_employee->position = $position;
            });

            OrbitInput::post('status', function($status) use ($update_employee) {
                $update_employee->status = $status;
            });

            $update_employee->save();

            $prefix = DB::getTablePrefix();
            $existingUserMerchants = UserMerchant::where('user_id', $update_user->user_id)->get()->lists('merchant_id');
            $campaignStatus = CampaignStatus::select('campaign_status_id')->where('campaign_status_name', '=', 'paused')->first();

            // delete all link to tenant
            if (empty($merchant_ids) && !is_array($merchant_ids) && !empty($existingUserMerchants)) {
                // delete user merchant
                $ownermerchant = UserMerchant::where('user_id', $update_user->user_id)->delete();
                // delete campaign link to tenant
                $news = News::select('news.news_name','news.news_id', 'news.object_type', 'news.status', 'news.campaign_status_id', 'news.created_by',
                                 DB::raw("(select COUNT(DISTINCT {$prefix}news_merchant.news_merchant_id)
                                            from {$prefix}news_merchant
                                                left join {$prefix}merchants on {$prefix}merchants.merchant_id = {$prefix}news_merchant.merchant_id
                                                left join {$prefix}merchants pm on {$prefix}merchants.parent_id = pm.merchant_id
                                                where {$prefix}news_merchant.news_id = {$prefix}news.news_id) as total_location"),
                                 DB::raw("CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired' THEN {$prefix}campaign_status.campaign_status_name ELSE (CASE WHEN {$prefix}news.end_date < (SELECT CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', ot.timezone_name) FROM {$prefix}merchants om
                                                LEFT JOIN {$prefix}timezones ot on ot.timezone_id = om.timezone_id WHERE om.merchant_id = {$prefix}news.mall_id)
                                            THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END) END  AS campaign_status"))
                            ->leftJoin('news_merchant', 'news_merchant.news_id', '=', 'news.news_id')
                            ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'news.campaign_status_id')
                            ->excludeDeleted('news')
                            ->having('campaign_status', '=', 'ongoing')
                            ->where('news.created_by', '=', $update_user->user_id)
                            ->whereIn('news_merchant.merchant_id', $existingUserMerchants)
                            ->groupBy('news.news_id')
                            ->get();

                if (!empty($news)) {
                    foreach($news as $key => $value) {
                        // delete link to campaign (news_merchant)
                        $deleteLocation = NewsMerchant::where('news_id', '=', $value->news_id)
                                                      ->whereIn('merchant_id', $existingUserMerchants)
                                                      ->delete();
                        // pause the campaign
                        $updateNews = News::where('news_id', '=', $value->news_id)->first();
                        $updateNews->status = 'inactive';
                        $updateNews->campaign_status_id = $campaignStatus->campaign_status_id;
                        $updateNews->save();

                        // update ES
                        if ($value->object_type == 'news') {
                            Queue::push('Orbit\\Queue\\Elasticsearch\\ESNewsUpdateQueue', ['news_id' => $value->news_id]);
                        }

                        if ($value->object_type == 'promotion') {
                            Queue::push('Orbit\\Queue\\Elasticsearch\\ESPromotionUpdateQueue', ['news_id' => $value->news_id]);
                        }
                    }
                }

                $coupons = Coupon::select('promotions.promotion_id','promotions.promotion_name','promotions.status',
                                    DB::raw("CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired' THEN {$prefix}campaign_status.campaign_status_name ELSE (CASE WHEN {$prefix}promotions.end_date < (SELECT CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', ot.timezone_name)
                                                                                                                        FROM {$prefix}merchants om
                                                                                                                        LEFT JOIN {$prefix}timezones ot on ot.timezone_id = om.timezone_id
                                                                                                                        WHERE om.merchant_id = {$prefix}promotions.merchant_id)
                                                                    THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END) END AS campaign_status"),
                                    DB::raw("(select COUNT(DISTINCT {$prefix}promotion_retailer.promotion_retailer_id)
                                                                            from {$prefix}promotion_retailer
                                                                            inner join {$prefix}merchants on {$prefix}merchants.merchant_id = {$prefix}promotion_retailer.retailer_id
                                                                            inner join {$prefix}merchants pm on {$prefix}merchants.parent_id = pm.merchant_id
                                                                            where {$prefix}promotion_retailer.promotion_id = {$prefix}promotions.promotion_id) as total_location")
                                    )
                                ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'promotions.campaign_status_id')
                                ->leftJoin('promotion_retailer', 'promotion_retailer.promotion_id', '=', 'promotions.promotion_id')
                                ->excludeDeleted('promotions')
                                ->having('campaign_status', '=', 'ongoing')
                                ->where('promotions.created_by', '=', $update_user->user_id)
                                ->whereIn('promotion_retailer.retailer_id', $existingUserMerchants)
                                ->groupBy('promotions.promotion_id')
                                ->get();

                if (!empty($coupons)) {
                    foreach($coupons as $key => $value) {
                        // delete link to campaign (promotion_retailer)
                        $deleteLocation = PromotionRetailer::where('promotion_id', '=', $value->promotion_id)
                                                          ->whereIn('retailer_id', $existingUserMerchants)
                                                          ->delete();

                        // delete redemption place
                        $deleteRetailerRedeem = CouponRetailerRedeem::where('promotion_id', '=', $value->promotion_id)
                                                                    ->whereIn('retailer_id', $existingUserMerchants)
                                                                    ->delete();

                        $updateCoupon = Coupon::where('promotion_id', '=', $value->promotion_id)->first();
                        $updateCoupon->status = 'inactive';
                        $updateCoupon->campaign_status_id = $campaignStatus->campaign_status_id;
                        $updateCoupon->save();

                        Queue::push('Orbit\\Queue\\Elasticsearch\\ESCouponUpdateQueue', ['coupon_id' => $value->promotion_id]);
                    }
                }

            }

            // Save to user_merchant (1 to M)
            if ($merchant_ids) {
                $merchants = UserMerchant::where('user_id', $update_user->user_id)->get()->lists('merchant_id');

                // handle from select all tenants when tenant has link to active campaign
                if (empty($merchants)) {
                    $link_to_tenants = CampaignLocation::excludeDeleted();

                    if ($account_type->type_name === 'Dominopos') {
                        $link_to_tenants->whereIn('object_type', ['mall', 'tenant']);
                    }

                    if ($account_type->type_name === '3rd Party') {
                        $link_to_tenants->where('object_type', 'mall');
                    }

                    $merchants = $link_to_tenants->get()->lists('merchant_id');
                }

                $removetenant = array_values(array_diff($merchants, $merchant_ids));
                $addtenant = array_diff($merchant_ids, $merchants);
                $newsPromotionActive = 0;
                $couponStatusActive = 0;

                if ($addtenant || $removetenant) {
                    $validator = Validator::make(
                        array(
                            'role_name'    => $update_user->role->role_name,
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

                // if ($removetenant) {
                //     foreach ($removetenant as $tenant_id) {
                //         $newsPromotionActive = 0;
                //         $couponStatusActive = 0;

                //         $mall = CampaignLocation::select('merchant_id',
                //                                     'name',
                //                                     'parent_id',
                //                                     'object_type')
                //                                 ->where('merchant_id', '=', $tenant_id)
                //                                 ->whereIn('object_type', ['mall', 'tenant'])
                //                                 ->first();

                //         if (! empty($mall)) {
                //             $mallid = '';
                //             if ($mall->object_type === 'mall') {
                //                 $mallid = $mall->merchant_id;
                //             } else {
                //                 $mallid = $mall->parent_id;
                //             }

                //             $timezone = Mall::leftJoin('timezones','timezones.timezone_id','=','merchants.timezone_id')
                //                 ->where('merchants.merchant_id','=', $mallid)
                //                 ->first();

                //             $timezoneName = $timezone->timezone_name;

                //             $nowMall = Carbon::now($timezoneName);
                //             $dateNowMall = $nowMall->toDateString();

                //             //get data in news and promotion
                //             $newsPromotionActive = News::allowedForPMPUser($update_user, 'news_promotion')
                //                                         ->select('news.news_id', 'news.object_type', 'news.is_having_reward')
                //                                         ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'news.campaign_status_id')
                //                                         ->leftJoin('news_merchant', 'news_merchant.news_id', '=', 'news.news_id')
                //                                         ->whereRaw("(CASE WHEN {$prefix}news.end_date < {$this->quote($nowMall)} THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END) NOT IN ('stopped', 'expired')")
                //                                         ->where('news_merchant.merchant_id', $tenant_id)
                //                                         ->first();
                //             if (! empty($newsPromotionActive)) {
                //                 if ($newsPromotionActive->is_having_reward === 'Y') {
                //                     $errorMessage = "Cannot unlink the tenant with an active promotional event on {$mall->object_type} {$mall->name}";
                //                 } else {
                //                     $errorMessage = "Cannot unlink the tenant with an active {$newsPromotionActive->object_type} on {$mall->object_type} {$mall->name}";
                //                 }
                //                 OrbitShopAPI::throwInvalidArgument($errorMessage);
                //             }

                //             //get data in coupon
                //             $couponStatusActive = Coupon::allowedForPMPUser($update_user, 'coupon')
                //                                         ->select('campaign_status.campaign_status_name')
                //                                         ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'promotions.campaign_status_id')
                //                                         ->leftJoin('promotion_retailer', 'promotion_retailer.promotion_id', '=', 'promotions.promotion_id')
                //                                         ->whereRaw("(CASE WHEN {$prefix}promotions.end_date < {$this->quote($nowMall)} THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END) NOT IN ('stopped', 'expired')")
                //                                         ->where('promotion_retailer.retailer_id', $tenant_id)
                //                                         ->first();
                //             if (! empty($couponStatusActive)) {
                //                 $errorMessage = "Cannot unlink the tenant with an active coupon on {$mall->object_type} {$mall->name}";
                //                 OrbitShopAPI::throwInvalidArgument($errorMessage);
                //             }
                //         }
                //     }
                // }


                // get campaign employee and delete merchant
                $employee = CampaignAccount::where('parent_user_id', '=', $update_user->user_id)
                                        ->lists('user_id');

                if (! empty($employee)) {
                    $merchantEmployee = UserMerchant::whereIn('user_id', $employee)
                                                    ->delete();
                }

                $ownermerchant = UserMerchant::where('user_id', $update_user->user_id)->delete();

                // Then update the user_id with the submitted ones
                foreach ($merchant_ids as $merchantId) {

                    $userMerchant = new UserMerchant;
                    $userMerchant->user_id = $update_user->user_id;
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

                // remove link to tenant on campaign
                if (! empty($removetenant)) {
                    // news, promotion, promotional event
                    $news = News::select('news.news_name','news.news_id', 'news.object_type', 'news.status', 'news.campaign_status_id', 'news.created_by',
                                 DB::raw("(select COUNT(DISTINCT {$prefix}news_merchant.news_merchant_id)
                                            from {$prefix}news_merchant
                                                left join {$prefix}merchants on {$prefix}merchants.merchant_id = {$prefix}news_merchant.merchant_id
                                                left join {$prefix}merchants pm on {$prefix}merchants.parent_id = pm.merchant_id
                                                where {$prefix}news_merchant.news_id = {$prefix}news.news_id) as total_location"),
                                 DB::raw("CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired' THEN {$prefix}campaign_status.campaign_status_name ELSE (CASE WHEN {$prefix}news.end_date < (SELECT CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', ot.timezone_name) FROM {$prefix}merchants om
                                                LEFT JOIN {$prefix}timezones ot on ot.timezone_id = om.timezone_id WHERE om.merchant_id = {$prefix}news.mall_id)
                                            THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END) END  AS campaign_status"))
                                ->leftJoin('news_merchant', 'news_merchant.news_id', '=', 'news.news_id')
                                ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'news.campaign_status_id')
                                ->excludeDeleted('news')
                                ->having('campaign_status', '=', 'ongoing')
                                ->where('news.created_by', '=', $update_user->user_id)
                                ->whereIn('news_merchant.merchant_id', $removetenant)
                                ->groupBy('news.news_id')
                                ->get();

                    if (!empty($news)) {
                        foreach($news as $key => $value) {
                            // delete link to campaign (news_merchant)
                            $deleteLocation = NewsMerchant::where('news_id', '=', $value->news_id)
                                                          ->whereIn('merchant_id', $removetenant)
                                                          ->delete();

                            $currentLocation = NewsMerchant::select(DB::raw('count(news_id) as total_location'))
                                                            ->where('news_id', '=', $value->news_id)
                                                            ->first();

                            if ($currentLocation->total_location == 0) {
                                $updateNews = News::where('news_id', '=', $value->news_id)->first();
                                $updateNews->status = 'inactive';
                                $updateNews->campaign_status_id = $campaignStatus->campaign_status_id;
                                $updateNews->save();
                            }

                            // update ES
                            if ($value->object_type == 'news') {
                                Queue::push('Orbit\\Queue\\Elasticsearch\\ESNewsUpdateQueue', ['news_id' => $value->news_id]);
                            }

                            if ($value->object_type == 'promotion') {
                                Queue::push('Orbit\\Queue\\Elasticsearch\\ESPromotionUpdateQueue', ['news_id' => $value->news_id]);
                            }
                        }
                    }


                    // coupon
                    $coupons = Coupon::select('promotions.promotion_id','promotions.promotion_name','promotions.status',
                                        DB::raw("CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired' THEN {$prefix}campaign_status.campaign_status_name ELSE (CASE WHEN {$prefix}promotions.end_date < (SELECT CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', ot.timezone_name)
                                                                                                                            FROM {$prefix}merchants om
                                                                                                                            LEFT JOIN {$prefix}timezones ot on ot.timezone_id = om.timezone_id
                                                                                                                            WHERE om.merchant_id = {$prefix}promotions.merchant_id)
                                                                        THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END) END AS campaign_status"),
                                        DB::raw("(select COUNT(DISTINCT {$prefix}promotion_retailer.promotion_retailer_id)
                                                                                from {$prefix}promotion_retailer
                                                                                inner join {$prefix}merchants on {$prefix}merchants.merchant_id = {$prefix}promotion_retailer.retailer_id
                                                                                inner join {$prefix}merchants pm on {$prefix}merchants.parent_id = pm.merchant_id
                                                                                where {$prefix}promotion_retailer.promotion_id = {$prefix}promotions.promotion_id) as total_location")
                                        )
                                    ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'promotions.campaign_status_id')
                                    ->leftJoin('promotion_retailer', 'promotion_retailer.promotion_id', '=', 'promotions.promotion_id')
                                    ->excludeDeleted('promotions')
                                    ->where('promotions.created_by', '=', $update_user->user_id)
                                    ->whereIn('promotion_retailer.retailer_id', $removetenant)
                                    ->having('campaign_status', '=', 'ongoing')
                                    ->groupBy('promotions.promotion_id')
                                    ->get();

                    if (!empty($coupons)) {
                        foreach($coupons as $key => $value) {
                            // delete link to campaign (promotion_retailer)
                            $deleteLocation = PromotionRetailer::where('promotion_id', '=', $value->promotion_id)
                                                              ->whereIn('retailer_id', $removetenant)
                                                              ->delete();

                            // delete redemption place
                            $deleteRetailerRedeem = CouponRetailerRedeem::where('promotion_id', '=', $value->promotion_id)
                                                                    ->whereIn('retailer_id', $removetenant)
                                                                    ->delete();

                            $currentLocation = PromotionRetailer::select(DB::raw('count(promotion_id) as total_location'))
                                                                ->where('promotion_id', '=', $value->promotion_id)
                                                                ->first();

                            // if only one location, update the campaign status to paused
                            if ($currentLocation->total_location == 0) {
                                $updateCoupon = Coupon::where('promotion_id', '=', $value->promotion_id)->first();
                                $updateCoupon->status = 'inactive';
                                $updateCoupon->campaign_status_id = $campaignStatus->campaign_status_id;
                                $updateCoupon->save();
                            }

                            Queue::push('Orbit\\Queue\\Elasticsearch\\ESCouponUpdateQueue', ['coupon_id' => $value->promotion_id]);
                        }
                    }

                }

            }

            // if the pmp account is inactive, set all campaign under that pmp account as paused
            if ($status === 'inactive') {
                $news = News::select('news.news_name','news.news_id', 'news.object_type', 'news.status', 'news.campaign_status_id',
                                 DB::raw("(select COUNT(DISTINCT {$prefix}news_merchant.news_merchant_id)
                                            from {$prefix}news_merchant
                                                left join {$prefix}merchants on {$prefix}merchants.merchant_id = {$prefix}news_merchant.merchant_id
                                                left join {$prefix}merchants pm on {$prefix}merchants.parent_id = pm.merchant_id
                                                where {$prefix}news_merchant.news_id = {$prefix}news.news_id) as total_location"),
                                 DB::raw("CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired' THEN {$prefix}campaign_status.campaign_status_name ELSE (CASE WHEN {$prefix}news.end_date < (SELECT CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', ot.timezone_name) FROM {$prefix}merchants om
                                                LEFT JOIN {$prefix}timezones ot on ot.timezone_id = om.timezone_id WHERE om.merchant_id = {$prefix}news.mall_id)
                                            THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END) END  AS campaign_status"))
                            ->leftJoin('news_merchant', 'news_merchant.news_id', '=', 'news.news_id')
                            ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'news.campaign_status_id')
                            ->excludeDeleted('news')
                            ->having('campaign_status', '=', 'ongoing')
                            ->where('news.created_by', '=', $update_user->user_id)
                            ->groupBy('news.news_id')
                            ->get();

                if (!empty($news)) {
                    foreach($news as $key => $value) {
                        $updateNews = News::where('news_id', '=', $value->news_id)->first();
                        $updateNews->status = 'inactive';
                        $updateNews->campaign_status_id = $campaignStatus->campaign_status_id;
                        $updateNews->save();

                        // update ES
                        if ($value->object_type == 'news') {
                            Queue::push('Orbit\\Queue\\Elasticsearch\\ESNewsUpdateQueue', ['news_id' => $value->news_id]);
                        }

                        if ($value->object_type == 'promotion') {
                            Queue::push('Orbit\\Queue\\Elasticsearch\\ESPromotionUpdateQueue', ['news_id' => $value->news_id]);
                        }
                    }
                }

                // coupon
                $coupons = Coupon::select('promotions.promotion_id','promotions.promotion_name','promotions.status',
                                    DB::raw("CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired' THEN {$prefix}campaign_status.campaign_status_name ELSE (CASE WHEN {$prefix}promotions.end_date < (SELECT CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', ot.timezone_name)
                                                                                                                        FROM {$prefix}merchants om
                                                                                                                        LEFT JOIN {$prefix}timezones ot on ot.timezone_id = om.timezone_id
                                                                                                                        WHERE om.merchant_id = {$prefix}promotions.merchant_id)
                                                                    THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END) END AS campaign_status"),
                                    DB::raw("(select COUNT(DISTINCT {$prefix}promotion_retailer.promotion_retailer_id)
                                                                            from {$prefix}promotion_retailer
                                                                            inner join {$prefix}merchants on {$prefix}merchants.merchant_id = {$prefix}promotion_retailer.retailer_id
                                                                            inner join {$prefix}merchants pm on {$prefix}merchants.parent_id = pm.merchant_id
                                                                            where {$prefix}promotion_retailer.promotion_id = {$prefix}promotions.promotion_id) as total_location")
                                    )
                                ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'promotions.campaign_status_id')
                                ->leftJoin('promotion_retailer', 'promotion_retailer.promotion_id', '=', 'promotions.promotion_id')
                                ->excludeDeleted('promotions')
                                ->having('campaign_status', '=', 'ongoing')
                                ->where('promotions.created_by', '=', $update_user->user_id)
                                ->groupBy('promotions.promotion_id')
                                ->get();

                if (!empty($coupons)) {
                    foreach($coupons as $key => $value) {
                        $updateCoupon = Coupon::where('promotion_id', '=', $value->promotion_id)->first();
                        $updateCoupon->status = 'inactive';
                        $updateCoupon->campaign_status_id = $campaignStatus->campaign_status_id;
                        $updateCoupon->save();

                        Queue::push('Orbit\\Queue\\Elasticsearch\\ESCouponUpdateQueue', ['coupon_id' => $value->promotion_id]);
                    }
                }
            }

            $this->response->data = $update_user;
            Event::fire('orbit.user.postupdateaccount.after.save', array($this, $update_user));

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activityNotes = sprintf('PMP Account Updated: %s', $update_user->user_email);
            $activity->setUser($apiUser)
                    ->setActivityName('update_pmp_account')
                    ->setActivityNameLong('Update PMP Account OK')
                    ->setObject($update_user)
                    ->setNotes($activityNotes)
                    ->responseOK();
        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($apiUser)
                    ->setActivityName('update_pmp_account')
                    ->setActivityNameLong('Update PMP Account Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($apiUser)
                    ->setActivityName('update_pmp_account')
                    ->setActivityNameLong('Update PMP Account Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
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

            // Creation failed Activity log
            $activity->setUser($apiUser)
                    ->setActivityName('update_pmp_account')
                    ->setActivityNameLong('Update PMP Account Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();

            if (Config::get('app.debug')) {
                $this->response->data = $e->getMessage().' '.$e->getLine();
            } else {
                $this->response->data = null;
            }

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($apiUser)
                    ->setActivityName('update_pmp_account')
                    ->setActivityNameLong('Update PMP Account Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save the activity
        $activity->save();

        return $this->render($httpCode);
    }

    protected function registerCustomValidation()
    {
        // Check username, it should not exists
        Validator::extend('orbit.exists.username', function ($attribute, $value, $parameters) {
            $user = User::where(function ($q) use ($value) {
                            $q->where('user_email', '=', $value)
                              ->orWhere('username', '=', $value);
                          })
                        ->whereIn('user_role_id', function ($q) {
                                $q->select('role_id')
                                  ->from('roles')
                                  ->whereNotIn('role_name', ['Consumer','Guest']);
                          });

            $user_sql = $user->toSql();
            $user_query = $user->getQuery();

            $user = DB::table(DB::raw("({$user_sql}) as sub_query"))
                    ->mergeBindings($user_query)
                    ->whereRaw("sub_query.status != 'deleted'")->first();

            if (! empty($user)) {
                OrbitShopAPI::throwInvalidArgument('The email address has already been taken');
            }

            return TRUE;
        });

        // Check account type, it should not empty
        Validator::extend('orbit.empty.account_type', function ($attribute, $value, $parameters) {
            $account_type = AccountType::excludeDeleted()
                                ->where('account_type_id', $value)
                                ->first();

            if (empty($account_type)) {
                OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.empty.account_type'));
            }

            $this->valid_account_type = $account_type;

            return TRUE;
        });

        // Check role, it should not empty
        Validator::extend('orbit.empty.role', function ($attribute, $value, $parameters) {
            $account_type = $parameters[0];

            if ($account_type === 'Master' && $value !== 'Campaign Admin') {
                return FALSE;
            }

            $role = Role::where('role_name', $value)
                        ->first();

            if (empty($role)) {
                return FALSE;
            }

            $this->valid_role = $role;

            return TRUE;
        });

        // Check country, it should not empty
        Validator::extend('orbit.empty.country', function ($attribute, $value, $parameters) {
            $country = Country::where('country_id', $value)
                        ->first();

            if (empty($country)) {
                return FALSE;
            }

            $this->valid_country = $country;

            return TRUE;
        });

        // Check link to tenant is not exists just for account type mall, merchant, and agency
        Validator::extend('orbit.exists.link_to_tenant', function ($attribute, $value, $parameters) {
            $prefix = DB::getTablePrefix();
            $account_type = $this->valid_account_type;

            if (! is_null($account_type)){
                // unique link to tenant
                if ($account_type->unique_rule !== 'none') {
                    $unique_rule =explode("_", $account_type->unique_rule);
                    $mall_tenant = UserMerchant::leftJoin('merchants', 'merchants.merchant_id', '=', 'user_merchant.merchant_id')
                                                ->leftJoin('campaign_account', 'campaign_account.user_id', '=', 'user_merchant.user_id')
                                                ->leftJoin('account_types', 'account_types.account_type_id', '=', 'campaign_account.account_type_id')
                                                ->where('account_types.unique_rule', '!=', 'none')
                                                ->where('merchants.status', '=', 'active')
                                                ->whereIn('user_merchant.object_type', $unique_rule)
                                                ->whereIn('user_merchant.merchant_id', $value);

                    OrbitInput::post('id', function($user_id) use ($mall_tenant, $prefix) {
                        $mall_tenant->whereRaw("(
                                not exists (
                                    select 1
                                    from {$prefix}campaign_account oca,
                                    (
                                        select ifnull(ca.parent_user_id, ca.user_id) as uid
                                        from {$prefix}campaign_account ca
                                        where ca.user_id = {$this->quote($user_id)}
                                    ) as ca
                                    where oca.user_id = ca.uid or oca.parent_user_id = ca.uid
                                        and oca.user_id = {$prefix}user_merchant.user_id
                                )
                            )");
                    });

                    $mall_tenant = $mall_tenant->first();
                    if (! empty($mall_tenant)) {
                        return FALSE;
                    }
                }

                // filter by account type
                $permission = [
                        'Mall'      => 'mall',
                        'Merchant'  => 'tenant',
                        'Agency'    => 'mall_tenant',
                        '3rd Party' => 'mall',
                        'Dominopos' => 'mall_tenant'
                    ];
                $access = explode("_", $permission[$account_type->type_name]);
                // access
                if (array_key_exists($account_type->type_name, $permission)) {
                    $mall_tenant = CampaignLocation::where('merchants.status', '=', 'active')
                                                ->whereIn('merchants.object_type', $access)
                                                ->whereIn('merchants.merchant_id', $value)
                                                ->count();

                    if ($mall_tenant !== count($value)) {
                        return FALSE;
                    }
                }

                return TRUE;
            } else {
                return FALSE;
            }
        });

        // Check permission select all tenants
        Validator::extend('orbit.access.select_all_tenants', function ($attribute, $value, $parameters) {
            $type_name = $parameters[0];
            $select_all_tenants = $value;

            if ($select_all_tenants === 'Y') {
                if (in_array($type_name, $this->allow_select_all_tenant)) {
                    return TRUE;
                }
                return FALSE;
            }

            return TRUE;
        });

        Validator::extend('orbit.formaterror.language', function($attribute, $value, $parameters)
        {
            $lang = Language::where('name', '=', $value)->where('status', '=', 'active')->first();

            if (empty($lang)) {
                return FALSE;
            }

            $this->valid_lang = $lang;
            return TRUE;
        });
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }

}
