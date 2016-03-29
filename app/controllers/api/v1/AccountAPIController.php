<?php

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
    /** @var array The list columns. */
    protected $listColumns = [
        'account_name' => [
            'title' => 'Account Name',
            'sort_key' => 'account_name',
        ],
        'company_name' => [
            'title' => 'Company Name',
            'sort_key' => 'company_name',
        ],
        'city' => [
            'title' => 'Location',
            'sort_key' => 'city',
        ],
        'tenants' => [
            'title' => 'Tenant(s)',
        ],
        'created_at' => [
            'title' => 'Creation Date',
            'sort_key' => 'created_at',
        ],
        'status' => [
            'title' => 'Status',
            'sort_key' => 'status',
        ],
    ];

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
        $availableMerchantIds = UserMerchant::whereIn('object_type', ['mall', 'tenant'])->whereNull('user_id')->lists('merchant_id');

        // Retrieve from "merchants" table
        $tenants = CampaignLocation::whereIn('merchant_id', $availableMerchantIds)->orderBy('name')->get();
        
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

        // Clean up user_merchant first
        UserMerchant::whereUserId($user->user_id)->delete();

        // Save to user_merchant (1 to M)
        foreach (Input::get('merchant_ids') as $merchantId) {
            $userMerchant = new UserMerchant;
            $userMerchant->user_id = $user->user_id;
            $userMerchant->merchant_id = $merchantId;
            $userMerchant->object_type = 'tenant';
            $userMerchant->save();
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
            $pmpAccounts->whereCity(Input::get('location'))->orWhere('countries.name', '=', Input::get('location'));
        }

        // Filter by Status
        if (Input::get('status')) {
            $pmpAccounts->where('campaign_account.status', Input::get('status'));
        }

        // Filter by Creation Date
        if (Input::get('creation_date_from') && Input::get('creation_date_to')) {

            // Let's make the datetime
            $creationDateTimeFrom = Input::get('creation_date_from').' 00:00:00';
            $creationDateTimeTo = Input::get('creation_date_to').' 23:59:59';

            $pmpAccounts->where('users.created_at', '>=', $creationDateTimeFrom)->where('users.created_at', '<=', $creationDateTimeTo);
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
            $records[] = [
                'account_name' => $row->campaignAccount->account_name,
                'company_name' => $row->company_name,
                'city' => $row->userDetail->city,
                'tenants' => $this->getTenantAtMallArray($row->userTenants()->lists('merchant_id')),
                'created_at' => $row->created_at->format('d F Y H:i:s'),
                'status' => $row->campaignAccount->status,
                'id' => $row->user_id,

                // Needed by frontend for the edit page
                'user_firstname' => $row->user_firstname,
                'user_lastname'  => $row->user_lastname,
                'position'       => $row->campaignAccount->position,
                'user_email'     => $row->user_email,
                'address_line1'  => $row->userDetail->address_line1,
                'province'       => $row->userDetail->province,
                'postal_code'    => $row->userDetail->postal_code,
                'country'        => (object) ['id' => $row->userDetail->country_id, 'name' => @$row->userDetail->userCountry->name],
            ];
        }

        $data->columns = $this->listColumns;
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
            'merchant_ids'   => 'required|array',
        ];

        if (Input::get('id')) {
            $rules['id'] = 'exists:users,user_id';
        } else {
            $rules['user_password'] = 'required';
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
