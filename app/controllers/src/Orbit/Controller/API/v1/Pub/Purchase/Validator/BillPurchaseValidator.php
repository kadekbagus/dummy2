<?php

namespace Orbit\Controller\API\v1\Pub\Purchase\Validator;

use App;
use Carbon\Carbon;
use DigitalProduct;
use Illuminate\Support\Facades\Config;
use OrbitShop\API\v1\Helper\Input;
use Orbit\Helper\MCash\API\Bill;
use PaymentTransaction;
use ProviderProduct;
use Setting;

/**
 * Validator related to purchase.
 *
 * @author Budi <budi@gotomalls.com>
 */
class BillPurchaseValidator
{
    public function purchaseEnabled($attr, $value, $params)
    {
        if (! App::bound('digitalProduct')) {
            return false;
        }

        $digitalProduct = App::make('digitalProduct');

        $billSettingNames = Bill::getBillSettingName();
        $settingName = $billSettingNames[$digitalProduct->product_type];

        $setting = Setting::select('setting_value')
            ->where('setting_name', $settingName)
            ->active()
            ->first();

        if (empty($setting)) {
            return false;
        }

        return (int) $setting->setting_value === 1;
    }

    public function limitPending($attribute, $billId, $parameters)
    {
        if (App::bound('providerProduct')) {
            $providerProduct = App::make('providerProduct');
        }
        else {
            $providerProduct = ProviderProduct::where(
                'provider_product_id',
                $digitalProduct->selected_provider_product_id
            )->first();

            if (empty($providerProduct)) {
                return false;
            }
        }

        return PaymentTransaction::select('payment_transactions.payment_transaction_id')
                            ->join('payment_transaction_details', 'payment_transactions.payment_transaction_id', '=', 'payment_transaction_details.payment_transaction_id')
                            // ->join('digital_products', 'payment_transaction_details.object_id', '=', 'digital_products.digital_product_id')
                            ->join('provider_products', 'payment_transaction_details.provider_product_id', '=', 'provider_products.provider_product_id')
                            ->whereIn('payment_transaction_details.object_type', ['pulsa', 'data_plan', 'digital_product'])
                            ->where('provider_products.code', $providerProduct->code)
                            ->where('payment_transactions.extra_data', $billId)
                            ->whereIn('payment_transactions.status', [
                                PaymentTransaction::STATUS_PENDING,
                            ])
                            ->count() === 0;
    }

    public function limitPurchase($attribuets, $value, $params, $data)
    {
        $digitalProduct = app('digitalProduct');
        $customerNumber = $value;

        if (empty($digitalProduct)) {
            return false;
        }

        $providerProduct = ProviderProduct::where(
            'provider_product_id',
            $digitalProduct->selected_provider_product_id
        )->first();

        if (empty($providerProduct)) {
            return false;
        }

        // If provider is not mcash, then skip checking for purchase limitation.
        // Just assume rule passed.
        if ($providerProduct->provider_name !== 'mcash') {
            return true;
        }

        $interval = Config::get('orbit.partners_api.mcash.interval_before_next_purchase', 60);
        $lastTime = Carbon::now('UTC')->subMinutes($interval)->format('Y-m-d H:i:s');

        $samePurchase = PaymentTransaction::select('payment_transactions.payment_transaction_id')
                        ->join('payment_transaction_details', 'payment_transactions.payment_transaction_id', '=', 'payment_transaction_details.payment_transaction_id')
                        ->join('digital_products', 'payment_transaction_details.object_id', '=', 'digital_products.digital_product_id')
                        ->join('provider_products', 'digital_products.selected_provider_product_id', '=', 'provider_products.provider_product_id')
                        ->whereIn('payment_transaction_details.object_type', ['pulsa', 'data_plan', 'digital_product'])
                        ->where('provider_products.code', $providerProduct->code)
                        ->where('payment_transactions.extra_data', $customerNumber)
                        ->where('payment_transactions.updated_at', '>', $lastTime)
                        ->whereIn('payment_transactions.status', [
                            PaymentTransaction::STATUS_SUCCESS,
                            PaymentTransaction::STATUS_SUCCESS_NO_COUPON,
                            PaymentTransaction::STATUS_SUCCESS_NO_PULSA,
                            PaymentTransaction::STATUS_SUCCESS_NO_PRODUCT,
                        ])
                        ->count();

        return $samePurchase === 0;
    }

    public function matchUser($attrs, $value, $params)
    {
        if (App::bound('currentUser') && App::bound('purchase')) {
            return App::make('currentUser')->user_id ===
                App::make('purchase')->user_id;
        }

        return false;
    }
}
