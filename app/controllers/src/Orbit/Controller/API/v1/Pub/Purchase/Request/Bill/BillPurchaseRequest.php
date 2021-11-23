<?php

namespace Orbit\Controller\API\v1\Pub\Purchase\Request\Bill;

use Illuminate\Support\Facades\Validator;
use Orbit\Helper\Request\ValidateRequest;

/**
 * New order request.
 *
 * @author Budi <budi@gotomalls.com>
 */
class BillPurchaseRequest extends ValidateRequest
{
    protected $roles = ['consumer'];

    protected $billType = '';

    public function __construct($billType = '')
    {
        $this->billType = $billType;

        if (empty($billType)) {
            $this->billType = $this->resolveBillTypeFromRequest();
        }

        parent::__construct();
    }

    public function rules()
    {
        return [
            'object_type' => 'required|in:bill_' . $this->billType,
            'object_id' => join('|', [
                'required',
                'array',
                'orbit.bill.exists',
                'orbit.bill.can_purchase:bill_' . $this->billType,
                'orbit.bill.purchase_enabled:bill_' . $this->billType,
            ]),
            'bill_id' => join('|', [
                'required',
                'max:20',
                'orbit.bill.limit_pending:bill_' . $this->billType,
                'orbit.bill.limit_purchase:bill_' . $this->billType,
            ]),
            // 'promo_code' => 'sometimes|required|alpha_dash|active_discount|available_discount',
            'currency' => 'required',
            'first_name' => 'required',
            'last_name' => 'required',
            'email' => 'required|email',
            'phone' => '',
            'payment_method' => 'required|in:midtrans,midtrans-qris,midtrans-shopeepay,dana,stripe',
        ];
    }

    protected function registerCustomValidations()
    {
        Validator::extend(
            'orbit.bill.can_purchase',
            'Orbit\Controller\API\v1\Pub\Purchase\Validator\BillValidator@canPurchase'
        );

        Validator::extend(
            'orbit.bill.purchase_enabled',
            'Orbit\Controller\API\v1\Pub\Purchase\Validator\BillValidator@purchaseEnabled'
        );
    }
}
