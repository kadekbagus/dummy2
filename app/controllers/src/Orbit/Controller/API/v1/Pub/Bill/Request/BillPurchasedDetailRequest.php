<?php

namespace Orbit\Controller\API\v1\Pub\Bill\Request;

use Illuminate\Support\Facades\Validator;
use Orbit\Helper\Request\ValidateRequest;

/**
 * New order request.
 *
 * @author Budi <budi@gotomalls.com>
 */
class BillPurchasedDetailRequest extends ValidateRequest
{
    protected $roles = ['consumer'];

    public function rules()
    {
        return [
            'payment_transaction_id' => join('|', [
                'required',
                'orbit.purchase.exists',
                'orbit.purchase.match_user',
            ]),
        ];
    }

    protected function registerCustomValidations()
    {
        Validator::extend(
            'orbit.purchase.exists',
            'Orbit\Controller\API\v1\Pub\Purchase\Validator\PurchaseValidator@exists'
        );

        Validator::extend(
            'orbit.purchase.match_user',
            'Orbit\Controller\API\v1\Pub\Purchase\Validator\PurchaseValidator@matchUser'
        );
    }
}
