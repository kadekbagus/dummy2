<?php

namespace Orbit\Controller\API\v1\Pub\Reservation\Request;

use Illuminate\Support\Facades\Validator;
use Orbit\Helper\Request\ValidateRequest;

/**
 * Reserve request.
 *
 * @author Budi <budi@gotomalls.com>
 */
class MakeReservationRequest extends ValidateRequest
{
    protected $roles = ['consumer'];

    public function rules()
    {
        $rules = [
            'object_type' => 'required|in:brand_product',
        ];

        switch ($this->object_type) {
            case 'brand_product':
                $rules += [
                    'object_id' => 'required|orbit.brand_product_variant.can_reserve',
                ];
                break;

            // specific rules for other type goes here...
            // case 'coupon':
                // break;

            default:
                break;
        }

        return $rules;
    }

    protected function registerCustomValidations()
    {
        Validator::extend(
            'orbit.brand_product_variant.can_reserve',
            'Orbit\Controller\API\v1\BrandProduct\Validator\BrandProductValidator@canReserve'
        );
    }
}
