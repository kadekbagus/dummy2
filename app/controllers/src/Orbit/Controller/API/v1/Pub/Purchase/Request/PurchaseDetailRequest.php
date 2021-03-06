<?php namespace Orbit\Controller\API\v1\Pub\Purchase\Request;

use Illuminate\Support\Facades\Validator;
use Orbit\Helper\Request\ValidateRequest;

/**
 * Purchase Detail Request
 *
 * @todo  find a way to properly inject current user into request (might be a service)
 * @author Budi <budi@gotomalls.com>
 */
class PurchaseDetailRequest extends ValidateRequest
{
    /**
     * Allowed roles to access this request.
     * @var array
     */
    protected $roles = ['guest', 'consumer'];

    /**
     * Get validation rules.
     *
     * @return array array of rules for this request.
     */
    public function rules()
    {
        return [
            'payment_transaction_id'   => 'required|purchase_exists',
            'bypass_user'              => 'sometimes|required|in:Y,N',
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
    }
}
