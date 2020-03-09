<?php

namespace Orbit\Controller\API\v1\Product\DigitalProduct\Request;

use App;
use DigitalProduct;
use Orbit\Controller\API\v1\Pub\DigitalProduct\Validator\DigitalProductValidator;
use Orbit\Helper\Request\ValidateRequest;
use Validator;

/**
 * Digital Product Update Request
 *
 * @author Budi <budi@gotomalls.com>
 */
class UpdateRequest extends CreateRequest
{
    /**
     * Get validation rules.
     *
     * @return array array of rules for this request.
     */
    public function rules()
    {
        return [
            'id' => 'required|product_exists|provider_product_exists',
            'type' => 'sometimes|required|in:game_voucher,electricity',
            'name' => 'sometimes|required',
            'code' => 'sometimes|required|unique_code_if_changed',
            'provider_id' => 'sometimes|required',
            'games' => 'sometimes|required_if:type,game_voucher|array',
            'price' => 'sometimes|required|numeric',
            'status' => 'sometimes|required|in:active,inactive',
            'displayed' => 'sometimes|required|in:yes,no',
            'promo' => 'sometimes|required|in:yes,no',
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array
     */
    public function messages()
    {
        return array_merge(
            parent::messages(),
            [
                'product_exists' => 'PRODUCT_DOES_NOT_EXISTS',
                'unique_code_if_changed' => 'PRODUCT_CODE_ALREADY_EXISTS',
            ]
        );
    }

    protected function registerCustomValidations()
    {
        parent::registerCustomValidations();

        Validator::extend(
            'product_exists',
            DigitalProductValidator::class . '@exists'
        );

        Validator::extend(
            'provider_product_exists',
            DigitalProductValidator::class . '@providerProductExists'
        );

        Validator::extend(
            'unique_code_if_changed',
            function($attributes, $productCode, $parameters) {
                $this->productCode = $productCode;
                $digitalProduct = App::make('digitalProduct');

                if (empty($digitalProduct)) {
                    return false;
                }

                // If no change, then assume valid.
                if ($digitalProduct->code === $productCode) {
                    return true;
                }

                // If changed, then check for duplication.
                return DigitalProduct::select('code')
                    ->where('code', $productCode)->first() === null;
            }
        );
    }
}
