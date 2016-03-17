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
        'number_of_tenant' => [
            'title' => 'Number of Tenant(s)',
            'sort_key' => 'number_of_tenant',
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
        $records = [
            [
                'name' => 'Starbucks Jakarta',
                'company_name' => 'Visionet',
                'location' => 'Jakarta, Indonesia',
                'number_of_tenant' => '2',
                'tenants' => ['Starbucks@Lippo Mall Puri', 'Starbucks@Lippo Mall Kemang'],
                'creation_date' => '26 January 2016 14:51:35',
                'status' => 'active',
            ],
        ];

        $data = new stdClass();
        $data->columns = $this->listColumns;
        $data->records = $records;

        return [
            'code' => 0,
            'status' => 'success',
            'message' => 'Request OK',
            'data' => $data,
        ];
    }
}
