<?php namespace Orbit\Controller\API\v1\Pub\Purchase\Request;

use Illuminate\Support\Facades\Validator;
use Orbit\Helper\Request\ValidateRequest;

/**
 * Purchase Detail Request
 *
 * @todo  find a way to properly inject current user into request (might be a service)
 * @author Budi <budi@gotomalls.com>
 */
class PurchaseAvailabilityRequest extends ValidateRequest
{
    /**
     * Allowed roles to access this request.
     * @var array
     */
    protected $roles = ['consumer'];

    /**
     * Get validation rules.
     *
     * @return array array of rules for this request.
     */
    public function rules()
    {
        return [
            'object_id'         => 'required|orbit.product_exists|orbit.product_active',
            'object_type'       => 'required|in:digital_product',
            'quantity'          => 'required|min:1|max:1',
            'customer_number'   => 'required|min:10|orbit.limit.pending|orbit.limit.purchase',
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
            'orbit.product_exists' => 'REQUESTED_ITEM_NOT_FOUND.',
            'orbit.product_active' => 'REQUESTED_ITEM_NOT_FOUND.',
            'orbit.allowed.quantity' => 'REQUESTED_QUANTITY_NOT_AVAILABLE',
            'orbit.limit.purchase' => 'PURCHASE_TIME_LIMITED',
            'orbit.limit.pending' => 'FINISH_PENDING_FIRST',
        ];
    }

    protected function registerCustomValidations()
    {
        Validator::extend(
            'orbit.product_exists',
            'Orbit\Controller\API\v1\Pub\DigitalProduct\Validator\DigitalProductValidator@exists'
        );

        Validator::extend(
            'orbit.product_active',
            'Orbit\Controller\API\v1\Pub\DigitalProduct\Validator\DigitalProductValidator@available'
        );

        Validator::extend(
            'orbit.limit.pending',
            'Orbit\Controller\API\v1\Pub\Purchase\Validator\PurchaseValidator@limitPending'
        );

        Validator::extend(
            'orbit.limit.purchase',
            'Orbit\Controller\API\v1\Pub\Purchase\Validator\PurchaseValidator@limitPurchase'
        );
    }
}
