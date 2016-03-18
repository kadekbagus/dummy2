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
        'name' => [
            'title' => 'Account Name',
            'sort_key' => 'name',
        ],
        'company_name' => [
            'title' => 'Company Name',
            'sort_key' => 'company_name',
        ],
        'location' => [
            'title' => 'Location',
            'sort_key' => 'location',
        ],
        'tenants' => [
            'title' => 'Tenant(s)',
            'sort_key' => 'tenants',
        ],
        'creation_date' => [
            'title' => 'Creation Date',
            'sort_key' => 'creation_date',
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
        $data = new stdClass();

        $pmpAccounts = User::pmpAccounts();

        // Filter by Status
        if (Input::get('status')) {
            $pmpAccounts->whereStatus(Input::get('status'));
        }

        // Get total row count
        $allRows = clone $pmpAccounts;
        $data->total_records = $allRows->count();

        $pmpAccounts = $pmpAccounts->take(Input::get('take'))->skip(Input::get('skip'))->get();

        $records = [];
        foreach ($pmpAccounts as $row) {
            $records[] = [
                'name' => $row->full_name,
                'company_name' => $row->userDetail->company_name,
                'location' => $row->userDetail->location,
                'tenants' => $this->getTenantAtMallArray($row->userTenants()->lists('merchant_id')),
                'creation_date' => $row->created_at->format('d F Y H:i:s'),
                'status' => $row->status,
            ];
        }

        $data->columns = $this->listColumns;
        $data->records = $records;

        $data->returned_records = count($records);

        $this->response->data = $data;
        return $this->render(200);
    }

    protected function getTenantAtMallArray($tenantIds)
    {
        $tenantArray = [];
        foreach (Tenant::whereIn('merchant_id', $tenantIds)->get() as $row) {
            $tenantArray[] = $row->tenant_at_mall;
        }

        return $tenantArray;
    }
}
