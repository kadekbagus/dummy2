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
    protected function createUpdateUserMerchant($user)
    {
        // Campaign Employees cannot change their tenants` ownership
        if ($user->role->role_name == 'Campaign Employee') {
            return;
        }        

        // First, set user_id as null
        $currentUserMerchants = UserMerchant::whereUserId($user->user_id)->get();
        foreach ($currentUserMerchants as $currentUserMerchant) {
            $currentUserMerchant->user_id = null;
            $currentUserMerchant->save();
        }
 
        // Then update the user_id with the submitted ones 
        foreach (Input::get('merchant_ids') as $merchantId) { 

            $userMerchant = UserMerchant::whereMerchantId($merchantId)->whereUserId($user->user_id)->first();
            if ( ! $userMerchant) {
                $userMerchant = new UserMerchant;
            }

            $userMerchant->user_id = $user->user_id; 
            $userMerchant->merchant_id = $merchantId; 
 
            // Get "object_type" from "merchants" table 
            $userMerchant->object_type = CampaignLocation::find($merchantId)->object_type; 
             
            $userMerchant->save(); 
        } 
    }

    /**
     * The main method
     *
     * @author Qosdil A. <qosdil@dominopos.com>
     * @todo Validation.
     */
    public function getAccount()
    {
        $this->prepareData();

        $this->data->returned_records = count($this->data->records);

        $this->response->data = $this->data;
        return $this->render(200);
    }

    public function getAvailableTenantsSelection()
    {
        $availableMerchantIds = UserMerchant::whereIn('object_type', ['mall', 'tenant'])->lists('merchant_id');

        // Retrieve from "merchants" table
        $tenants = CampaignLocation::whereNotIn('merchant_id', $availableMerchantIds)->orderBy('name')->get();
        
        $selection = [];
        foreach ($tenants as $tenant) {
            $selection[] = [
                'id'     => $tenant->merchant_id,
                'name'   => $tenant->tenant_at_mall,
                'status' => $tenant->status,
            ];
        }

        $this->response->data = ['row_count' => count($selection), 'available_tenants' => $selection];
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

        // Save to user_merchant (1 to M)
        $this->createUpdateUserMerchant($user);

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
        return $this->render(200);
    }

    protected function prepareData()
    {
        $data = new stdClass();

        $pmpAccounts = User::pmpAccounts();

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
            $rules['user_email'] .= '|unique:users,user_email';
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
}
