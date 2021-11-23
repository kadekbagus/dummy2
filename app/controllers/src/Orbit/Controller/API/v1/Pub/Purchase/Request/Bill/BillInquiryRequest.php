<?php

namespace Orbit\Controller\API\v1\Pub\Purchase\Request\Bill;

use Illuminate\Support\Facades\Validator;

/**
 * Bill detail request (inquiry).
 *
 * @author Budi <budi@gotomalls.com>
 */
class BillInquiryRequest extends BillPurchaseRequest
{
    public function rules()
    {
        $availableBillTypes = BillRepository::getBillTypeIds();
        return [
            'object_type' => join('|', [
                'required',
                'in:digital_product',
                'orbit.bill.purchase_enabled',
            ]),
            'object_id' => join('|', [
                'required',
                'orbit.bill.product_exists',
                'orbit.bill.provider_product_exists',
                'orbit.bill.can_purchase',
            ]),
            'bill_id' => join('|', [
                'required',
                'max:20',
                'orbit.bill.limit_pending',
                'orbit.bill.limit_purchase',
            ]),
            // 'promo_code' => 'sometimes|required|alpha_dash|active_discount|available_discount',
            'currency' => 'required',
            // 'first_name' => 'required',
            // 'last_name' => 'sometimes|required',
            // 'email' => 'required|email',
            // 'phone' => '',
            'payment_method' => 'required|in:midtrans,midtrans-qris,midtrans-shopeepay,dana,stripe',
        ];
    }

    protected function registerCustomValidations()
    {
        Validator::extend(
            'orbit.bill.can_purchase',
            'Orbit\Controller\API\v1\Pub\Purchase\Validator\BillPurchaseValidator@canPurchase'
        );

        Validator::extend(
            'orbit.bill.purchase_enabled',
            'Orbit\Controller\API\v1\Pub\Purchase\Validator\BillPurchaseValidator@purchaseEnabled'
        );
    }
}
