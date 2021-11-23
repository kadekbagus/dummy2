<?php

namespace Orbit\Controller\API\v1\Pub\Bill\Repository;

use Exception;
use Illuminate\Support\Facades\App;
use Setting;

/**
 * Bill Repository.
 */
class BillRepository
{
    private $bill = null;

    private $request = null;

    private $billTypeIds = [
        'enable_electricity_bill_page' => 'electricity_bills',
        'enable_pdam_bill_page' => 'pdam_bills',
        'enable_pbb_tax_page' => 'pbb_tax',
        'enable_bpjs_bill_page' => 'bpjs',
        'enable_internet_provider_bill_page' => 'internet_providers',
    ];

    private $billTypeSettings = [];

    function __construct($request = null)
    {
        if (App::bound('currentRequest')) {
            $this->setRequest(App::make('currentRequest'));
        }

        if ($request) {
            $this->setRequest($request);
        }

        if ($this->request) {
            $this->loadBillTypes($this->request->status);
        }
    }

    public function setRequest($request)
    {
        $this->request = $request;
        return $this;
    }

    public static function getBillTypeIds()
    {
        return [
            'electricity_bills',
            'pdam_bills',
            'pbb_tax',
            'bpjs',
            'internet_providers',
            'gtm_mdr_value',
        ];
    }

    public function getBillTypes($status = 'all', $force = false)
    {
        if ($force || empty($this->billTypeSettings)) {
            $this->loadBillTypes($status);
        }

        return $this->billTypeSettings;
    }

    public function getBillProviders($request)
    {
        return $billProviders;
    }

    public function inquiry($billType, $params = [])
    {
        return Inquiry::create()
            ->inquiry($billType, $params);
    }

    public function pay($billType, $params = [])
    {
        switch ($billType) {
            case 'electricity_bills':
                return Pay::create(['product_type' => $billType])
                    ->pay($params);
                break;

            default:
                throw new Exception('invalid bill type!');
                break;
        }
    }

    private function loadBillTypes($status = 'all')
    {
        $settings = Setting::select('setting_name', 'setting_value')
            ->whereIn('setting_name', [
                'enable_electricity_bill_page',
                'enable_pdam_bill_page',
                'enable_pbb_tax_page',
                'enable_bpjs_bill_page',
                'enable_internet_provider_bill_page',
                'gtm_mdr_value',
            ])
            ->when($status === 'enabled', function($query) {
                return $query->where('setting_value', 1);
            })
            ->get();

        $billSettings = [];
        foreach($settings as $setting) {
            $settingName = $setting->setting_name;
            $billTypeId = $this->billTypeIds[$settingName];

            $this->billTypeSettings[$billTypeId] = [
                $settingName => (int) $setting->setting_value,
                'name' => trans('bills.' . $billTypeId),
            ];
        }
    }
}
