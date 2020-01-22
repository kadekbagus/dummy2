<?php namespace Orbit\Controller\API\v1\Product\DigitalProduct\Request;

use App;
use DigitalProduct;
use Orbit\Helper\Request\ValidateRequest;
use ProviderProduct;
use Validator;

/**
 * Digital Product List Request
 *
 * @todo  find a way to properly inject current user into request (might be a service)
 * @author Budi <budi@gotomalls.com>
 */
class DigitalProductNewRequest extends ValidateRequest
{
    /**
     * Allowed roles to access this request.
     * @var array
     */
    protected $roles = ['product manager'];

    protected $productCode = '';

    /**
     * Get validation rules.
     *
     * @return array array of rules for this request.
     */
    public function rules()
    {
        return [
            'type' => 'required|in:game_voucher,electricity',
            'name' => 'required',
            'code' => 'required|unique_code',
            'provider_id' => 'required|provider_product_exists',
            'games' => 'required|array',
            'price' => 'required|numeric',
            'status' => 'required|in:active,inactive',
            'displayed' => 'required|in:yes,no',
            'promo' => 'required|in:yes,no',
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
            'unique_code' => 'PRODUCT_CODE_ALREADY_EXISTS',
            'provider_product_exists' => 'PROVIDER_PRODUCT_DOES_NOT_EXISTS',
        ];
    }

    public function getValidationErrorMessage()
    {
        $error = parent::getValidationErrorMessage();

        if ($error === 'PRODUCT_CODE_ALREADY_EXISTS') {
            return "Product code **{$this->productCode}** already exists.";
        }

        return $error;
    }

    protected function registerCustomValidations()
    {
        Validator::extend('unique_code', function($attributes, $productCode, $parameters) {
            $this->productCode = $productCode;
            return null === DigitalProduct::select('code')->where('code', $productCode)->first();
        });

        Validator::extend('provider_product_exists', function($attributes, $providerProductId, $parameters) {
            $providerProduct = ProviderProduct::where('provider_product_id', $providerProductId)->first();

            App::instance('providerProduct', $providerProduct);

            return ! empty($providerProduct);
        });
    }
}
