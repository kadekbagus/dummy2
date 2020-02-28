<?php namespace Orbit\Controller\API\v1\Product\DigitalProduct\Request;

use App;
use DigitalProduct;
use Orbit\Helper\Request\ValidateRequest;
use Validator;

/**
 * Digital Product Update Request
 *
 * @author Budi <budi@gotomalls.com>
 */
class UpdateRequest extends DigitalProductNewRequest
{
    /**
     * Get validation rules.
     *
     * @return array array of rules for this request.
     */
    public function rules()
    {
        return array_merge(
            [
                'id' => 'required|product_exists'
            ],
            parent::rules(),
            [
                'type' => 'sometimes|required|in:game_voucher,electricity',
                'code' => 'required|unique_code_if_changed',
            ]
        );
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

        Validator::extend('product_exists', 'Orbit\Controller\API\v1\Pub\DigitalProduct\Validator\DigitalProductValidator@exists');

        Validator::extend('unique_code_if_changed', function($attributes, $productCode, $parameters) {
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
            return null === DigitalProduct::select('code')->where('code', $productCode)->first();
        });
    }
}
