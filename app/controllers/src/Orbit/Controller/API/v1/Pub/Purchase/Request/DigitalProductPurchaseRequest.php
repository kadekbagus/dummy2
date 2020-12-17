<?php namespace Orbit\Controller\API\v1\Pub\Purchase\Request;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Validator;
use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Validators\ActiveDiscountValidator;
use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Validators\AvailableDiscountValidator;
use Orbit\Helper\Request\ValidateRequest;

/**
 * Digital Product Purchase Request
 *
 * @todo  find a way to properly inject current user into request (might be a service)
 * @author Budi <budi@gotomalls.com>
 */
class DigitalProductPurchaseRequest extends ValidateRequest
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
            'object_id' => 'required|product_exists|provider_product_exists', // digital product id
            'object_type' => 'required|in:digital_product', // digital_product
            'game_id' => 'sometimes|required|product_with_game_exists',
            'electric_id' => 'sometimes|required|limit.pending|limit.purchase',
            // 'provider_product_id' => 'required|provider_product_exists',
            'promo_code' => 'sometimes|required|alpha_dash|active_discount|available_discount',
            'object_name' => 'required',
            'quantity' => 'required|numeric|max:1',
            'amount' => 'required|numeric',
            'currency' => 'required',
            'first_name' => 'required',
            'last_name' => 'required',
            'email' => 'required|email',
            'phone' => '',
            'payment_method' => 'required|in:midtrans,midtrans-qris,dana,stripe',
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
            'product_exists' => 'PRODUCT_DOES_NOT_EXISTS',
            'product_with_game_exists' => 'PRODUCT_FOR_GAME_DOES_NOT_EXISTS',
            'provider_product_exists' => 'PROVIDER_PRODUCT_DOES_NOT_EXISTS',
            'promo_code.alpha_dash' => 'PROMO_CODE_MUST_BE_ALPHA_DASH',
            'promo_code.active_discount' => 'PROMO_CODE_NOT_ACTIVE',
            'promo_code.available_discount' => 'PROMO_CODE_NOT_AVAILABLE',
            'limit.pending' => 'FINISH_PENDING_FIRST',
            'limit.purchase' => 'PURCHASE_TIME_LIMITED',
        ];
    }

    protected function registerCustomValidations()
    {
        Validator::extend('product_exists', 'Orbit\Controller\API\v1\Pub\DigitalProduct\Validator\DigitalProductValidator@exists');

        Validator::extend('product_with_game_exists', 'Orbit\Controller\API\v1\Pub\DigitalProduct\Validator\DigitalProductValidator@existsWithGame');

        Validator::extend('provider_product_exists', 'Orbit\Controller\API\v1\Pub\DigitalProduct\Validator\DigitalProductValidator@providerProductExists');

        Validator::extend('active_discount', ActiveDiscountValidator::class . '@validate');

        Validator::extend('available_discount', function ($attribute, $value, $parameters, $validators) {
            $val = (new AvailableDiscountValidator())->user($this->user());
            return $val($attribute, $value, $parameters, $validators);
        });

        Validator::extend(
            'limit.pending',
            'Orbit\Controller\API\v1\Pub\Purchase\Validator\PurchaseValidator@limitPending'
        );

        Validator::extend(
            'limit.purchase',
            'Orbit\Controller\API\v1\Pub\Purchase\Validator\PurchaseValidator@limitPurchase'
        );
    }
}
