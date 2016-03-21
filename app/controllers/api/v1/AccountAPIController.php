<?php

use OrbitShop\API\v1\ControllerAPI;

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

        $pmpAccounts = $pmpAccounts->take(Input::get('take'))->skip(Input::get('skip'))
            ->orderBy(Input::get('sortby', 'user_firstname'), Input::get('sortmode', 'asc'))
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
            ];
        }

        $data->columns = $this->listColumns;
        $data->records = $records;

        $this->data = $data;
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

    protected function getTenantAtMallArray($tenantIds)
    {
        $tenantArray = [];
        foreach (Tenant::whereIn('merchant_id', $tenantIds)->orderBy('name')->get() as $row) {
            $tenantArray[] = $row->tenant_at_mall;
        }

        return $tenantArray;
    }
}
