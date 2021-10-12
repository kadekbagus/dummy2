<?php

namespace Orbit\Controller\API\v1\Pub\Order\Request;

use Illuminate\Support\Facades\Validator;
use Orbit\Helper\Request\ValidateRequest;

/**
 * New order request.
 */
class OrderStatusUpdateRequest extends ValidateRequest
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
            'status' => join('|', [
                'required',
                'in:picked_up',
                'orbit.order.can_change_status',
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

        Validator::extend(
            'orbit.order.can_change_status',
            'Orbit\Helper\Cart\Validator\OrderValidator@canChangeStatus'
        );
    }
}
