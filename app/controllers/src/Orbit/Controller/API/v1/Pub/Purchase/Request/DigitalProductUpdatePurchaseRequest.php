<?php namespace Orbit\Controller\API\v1\Pub\Purchase\Request;

use Illuminate\Support\Facades\Validator;
use Orbit\Helper\Request\ValidateRequest;

/**
 * Digital Product Purchase Request
 *
 * @todo  find a way to properly inject current user into request (might be a service)
 * @author Budi <budi@gotomalls.com>
 */
class DigitalProductUpdatePurchaseRequest extends ValidateRequest
{
    /**
     * Allowed roles to access this request.
     * @var array
     */
    protected $roles = ['consumer', 'guest'];

    /**
     * Get validation rules.
     *
     * @return array array of rules for this request.
     */
    public function rules()
    {
        return [
            'payment_transaction_id' => 'required|purchase_exists',
            'status' => join('|', [
                'required',
                'in:' . join(',', [
                    'pending', 'success', 'cancel', 'canceled',
                    'failed', 'expired', 'denied', 'suspicious', 'abort',
                    'refund', 'partial_refund',
                ]),
                'orbit.order.can_change_status',
            ]),
            'payment_method' => join('|', [
                'sometimes',
                'required',
                'in:' . join(',', [
                    'midtrans', 'midtrans-qris', 'midtrans-shopeepay',
                    'stripe', 'dana',
                ]),
            ]),
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'purchase_exists' => 'PURCHASE_DOES_NOT_EXISTS',
        ];
    }

    protected function registerCustomValidations()
    {
        Validator::extend('purchase_exists', 'Orbit\Controller\API\v1\Pub\Purchase\Validator\PurchaseValidator@exists');

        Validator::extend(
            'orbit.order.can_change_status',
            'Orbit\Helper\Cart\Validator\OrderValidator@canChangeStatus'
        );
    }
}
