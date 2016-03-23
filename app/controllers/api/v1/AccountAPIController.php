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
        'user_firstname' => [
            'title' => 'Account Name',
            'sort_key' => 'user_firstname',
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

    protected function getTenantAtMallArray($tenantIds)
    {
        $tenantArray = [];
        foreach (Tenant::whereIn('merchant_id', $tenantIds)->orderBy('name')->get() as $row) {
            $tenantArray[] = $row->tenant_at_mall;
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

        if (Input::get('user_password')) {
            $user->user_password = Hash::make(Input::get('user_password'));
        }

        if ( ! $this->id) {
            $user->status = 'active';
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
        $userDetail->country = Input::get('country');
        $userDetail->save();

        // Save to employees table (1 to 1)
        $employee = ($this->id) ? Employee::whereUserId($user->user_id)->first() : new Employee;
        $employee->user_id = $user->user_id;
        $employee->position = Input::get('position');

        if ( ! $this->id) {
            $employee->status = 'active';
        }

        $employee->save();

        // Save to campaign_account table (1 to 1)
        $campaignAccount = ($this->id) ? CampaignAccount::whereUserId($user->user_id)->first() : new CampaignAccount;
        $campaignAccount->user_id = $user->user_id;
        $campaignAccount->account_name = Input::get('account_name');
        $campaignAccount->status = Input::get('status');
        $campaignAccount->save();

        // Clean up user_merchant first
        UserMerchant::whereIn('merchant_id', Input::get('merchant_ids'));
        UserMerchant::whereUserId($user->user_id)->delete();

        // Save to user_merchant (1 to M)
        foreach (Input::get('merchant_ids') as $merchantId) {
            $userMerchant = new UserMerchant;
            $userMerchant->user_id = $user->user_id;
            $userMerchant->merchant_id = $merchantId;
            $userMerchant->object_type = 'tenant';
            $userMerchant->save();
        }

        return $this->render(200);
    }

    /**
     * Post New Account
     *
     * @author Qosdil A. <qosdil@dominopos.com>
     */
    public function postNewAccount()
    {
        // Do validation
        if (!$this->validate()) {
            return $this->render($this->errorCode);
        }

        // Save to users table
        $user = new User;
        $user->user_firstname = Input::get('user_firstname');
        $user->user_lastname = Input::get('user_lastname');
        $user->user_email = Input::get('user_email');
        $user->username = Input::get('user_email');
        $user->user_password = Hash::make(Input::get('user_password'));
        $user->status = 'active';
        $user->save();

        // Save to user_details table (1 to 1)
        $userDetail = new UserDetail;
        $userDetail->user_id = $user->user_id;
        $userDetail->company_name = Input::get('company_name');
        $userDetail->address_line1 = Input::get('address_line1');
        $userDetail->city = Input::get('city');
        $userDetail->province = Input::get('province');
        $userDetail->postal_code = Input::get('postal_code');
        $userDetail->country = Input::get('country');
        $userDetail->save();

        // Save to employees table (1 to 1)
        $employee = new Employee;
        $employee->user_id = $user->user_id;
        $employee->position = Input::get('position');
        $employee->status = 'active';
        $employee->save();

        // Save to campaign_account table (1 to 1)
        $campaignAccount = new CampaignAccount;
        $campaignAccount->user_id = $user->user_id;
        $campaignAccount->account_name = Input::get('account_name');
        $campaignAccount->status = Input::get('status');
        $campaignAccount->save();

        // Save to user_merchant (1 to M)
        foreach (Input::get('merchant_ids') as $merchantId) {
            $userMerchant = new UserMerchant;
            $userMerchant->user_id = $user->user_id;
            $userMerchant->merchant_id = $merchantId;
            $userMerchant->object_type = 'tenant';
            $userMerchant->save();
        }

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

        // Filter by Location
        if (Input::get('location')) {
            $pmpAccounts->whereCity(Input::get('location'))->orWhere('country', Input::get('location'));
        }

        // Filter by Status
        if (Input::get('status')) {
            $pmpAccounts->whereStatus(Input::get('status'));
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

        $sortKey = Input::get('sortby', 'user_firstname');

        // Prevent ambiguous error
        if ($sortKey == 'created_at') {
            $sortKey = 'users.created_at';
        }

        $pmpAccounts = $pmpAccounts->take(Input::get('take'))->skip(Input::get('skip'))
            ->orderBy($sortKey, Input::get('sortmode', 'asc'))
            ->get();

        $records = [];
        foreach ($pmpAccounts as $row) {
            $records[] = [
                'user_firstname' => $row->full_name,
                'company_name' => $row->company_name,
                'city' => $row->userDetail->location,
                'tenants' => $this->getTenantAtMallArray($row->userTenants()->lists('merchant_id')),
                'created_at' => $row->created_at->format('d F Y H:i:s'),
                'status' => $row->status,
                'id' => $row->user_id,
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
            'user_password'  => Input::get('user_password'),
            'account_name'   => Input::get('account_name'),
            'status'         => Input::get('status'),
            'company_name'   => Input::get('company_name'),
            'address_line1'  => Input::get('address_line1'),
            'city'           => Input::get('city'),
            'country'        => Input::get('country'),
            'merchant_ids'   => Input::get('merchant_ids'),
        ];

        if (Input::get('id')) {
            $fields['id'] = Input::get('id');
        }

        $rules = [
            'user_firstname' => 'required',
            'user_lastname'  => 'required',
            'user_email'     => 'required|email',
            'user_password'  => 'required',
            'account_name'   => 'required',
            'status'         => 'in:active,inactive',
            'company_name'   => 'required',
            'address_line1'  => 'required',
            'city'           => 'required',
            'country'        => 'required',
            'merchant_ids'   => 'required|array',
        ];

        if (Input::get('id')) {
            $rules['id'] = 'exists:users,user_id';
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
