<?php

namespace Orbit\Controller\API\v1\Pub\Purchase\Request\Bill;

use Illuminate\Support\Facades\Validator;
use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Validators\ActiveDiscountValidator;
use Orbit\Helper\Request\ValidateRequest;

/**
 * New order request.
 *
 * @author Budi <budi@gotomalls.com>
 */
class CreateBillPurchaseRequest extends ValidateRequest
{
    protected $roles = ['consumer'];

    public function rules()
    {
        return [
            'object_type' => 'required|in:bill_' . $this->billType,
            'object_id' => 'required|array|orbit.order.can_order|orbit.order.order_enabled',
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
        // Validator::extend('active_discount', ActiveDiscountValidator::class . '@validate');

        // Validator::extend('available_discount', function ($attribute, $value, $parameters, $validators) {
        //     $val = (new AvailableDiscountValidator())->user($this->user());
        //     return $val($attribute, $value, $parameters, $validators);
        // });

        Validator::extend(
            'orbit.order.can_order',
            'Orbit\Helper\Cart\Validator\OrderValidator@canOrder'
        );

        Validator::extend(
            'orbit.order.order_enabled',
            'Orbit\Helper\Cart\Validator\OrderValidator@orderEnabled'
        );
    }
}
