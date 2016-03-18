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
     */
    public function getAccount()
    {
        $pmpAccounts = User::pmpAccounts()->get();

        $records = [];
        foreach ($pmpAccounts as $row) {
            $records[] = [
                'name' => $row->full_name,
                'company_name' => $row->userDetail->company_name,
                'location' => $row->userDetail->location,
                'tenants' => [],
                'creation_date' => $row->created_at->format('d F Y H:i:s'),
                'status' => $row->status,
            ];
        }

        $data = new stdClass();
        $data->columns = $this->listColumns;
        $data->records = $records;

        $this->response->data = $data;
        return $this->render(200);
    }
}
