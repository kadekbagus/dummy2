<?php

namespace Orbit\Controller\API\v1\Pub\Purchase\Request\Bill;

use Illuminate\Support\Facades\Validator;
use Orbit\Helper\Request\ValidateRequest;

/**
 * Bill detail request (inquiry).
 *
 * @author Budi <budi@gotomalls.com>
 */
class BillInquiryRequest extends ValidateRequest
{
    public function rules()
    {
        return [
            'object_id' => join('|', [
                'required',
                'orbit.digital_product.exists',
                // 'orbit.bill.can_purchase',
            ]),
            'object_type' => join('|', [
                'required',
                'in:digital_product',
                'orbit.bill.purchase_enabled',
            ]),
            'object_name' => 'required',
            'bill_id' => join('|', [
                'required',
                'max:20',
                'orbit.bill.limit_pending',
                // 'orbit.bill.limit_purchase',
            ]),
            'post_data' => 'sometimes|required',
            // 'promo_code' => 'sometimes|required|alpha_dash|active_discount|available_discount',
            'currency' => 'required',
            'first_name' => 'required',
            'last_name' => 'sometimes|required',
            'email' => 'required|email',
            'phone' => 'sometimes|required',
            'payment_method' => 'required|in:midtrans,midtrans-qris,midtrans-shopeepay,dana,stripe',
        ];
    }

    protected function registerCustomValidations()
    {
        Validator::extend(
            'orbit.digital_product.exists',
            'Orbit\Controller\API\v1\Pub\DigitalProduct\Validator\DigitalProductValidator@existsWithProviderProduct'
        );

        Validator::extend(
            'orbit.bill.purchase_enabled',
            'Orbit\Controller\API\v1\Pub\Purchase\Validator\BillPurchaseValidator@purchaseEnabled'
        );

        Validator::extend(
            'orbit.bill.limit_pending',
            'Orbit\Controller\API\v1\Pub\Purchase\Validator\BillPurchaseValidator@limitPending'
        );
    }
}
