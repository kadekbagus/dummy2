<?php

namespace Orbit\Controller\API\v1\Product\Pulsa\Request;

use App;
use Pulsa;
use Orbit\Helper\Request\ValidateRequest;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Validator;
use TelcoOperator;

/**
 * Create new pulsa Request
 *
 * @author kadek <kadek@gotomalls.com>
 */
class PulsaCreateRequest extends ValidateRequest
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
            'telco_operator_id'     => 'required|orbit.empty.telcooperator',
            'pulsa_code'            => 'required|orbit.exist.pulsa',
            'pulsa_display_name'    => 'required',
            'value'                 => 'required',
            'price'                 => 'required',
            'object_type'           => 'sometimes|in:pulsa,data_plan',
            'is_promo'              => 'in:yes,no',
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array
     */
    public function messages()
    {
        $object_type = OrbitInput::post('object_type', 'pulsa');
        $errorMessageObjectType = ucwords(str_replace(['_'], ' ', $object_type));

        return [
            'pulsa_code.required'                => "{$errorMessageObjectType} Product Name M-Cash field is required",
            'pulsa_display_name.required'        => "{$errorMessageObjectType} Product Name field is required",
            'value.required'                     => 'Facial Value field is required',
            'price.required'                     => 'Selling Price field is required',
            'telco_operator_id.required'         => "{$errorMessageObjectType} Operator field is required",
            'orbit.empty.telcooperator'          => "{$errorMessageObjectType} Operator not found",
            'orbit.exist.pulsa'                  => "{$errorMessageObjectType} Product Name M-Cash must be unique",
        ];
    }

    protected function registerCustomValidations()
    {
        Validator::extend('orbit.empty.telcooperator', function ($attribute, $value, $parameters) {
            $telco = TelcoOperator::where('telco_operator_id', $value)->first();

            if (empty($telco)) {
                return FALSE;
            }

            App::instance('orbit.empty.telcooperator', $telco);

            return TRUE;
        });

        Validator::extend('orbit.exist.pulsa', function ($attribute, $value, $parameters) {
            $pulsa = Pulsa::where('pulsa_code', $value)->first();

            if (! empty($pulsa)) {
                return FALSE;
            }

            App::instance('orbit.exist.pulsa', $pulsa);

            return TRUE;
        });

        Validator::extend('pulsa_code_exists_but_me', function ($attribute, $value, $parameters) {
            $pulsa_item_id = trim($parameters[0]);

            $pulsa = Pulsa::where('pulsa_code', $value)
                        ->where('pulsa_item_id', '!=', $pulsa_item_id)
                        ->first();

            if (! empty($pulsa)) {
                return FALSE;
            }

            App::instance('pulsa_code_exists_but_me', $pulsa);

            return TRUE;
        });
    }
}
