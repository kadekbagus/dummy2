<?php

namespace Orbit\Controller\API\v1\Pub\Reservation\Request;

use Illuminate\Support\Facades\Validator;
use Orbit\Helper\Request\ValidateRequest;

/**
 * Reserve request.
 *
 * @author Budi <budi@gotomalls.com>
 */
class CancelReservationRequest extends ValidateRequest
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
                    'reservation_id' => join('|', [
                        'required',
                        'orbit.brand_product_variant.rsvp_exists',
                        'orbit.brand_product_variant.can_cancel_rsvp',
                    ]),
                ];
                break;

            // specific rules for other type goes here...
            // case 'coupon':
                // break;

            default:
                return $this->handleValidationFails();
                break;
        }

        return $rules;
    }

    protected function registerCustomValidations()
    {
        Validator::extend(
            'orbit.brand_product_variant.rsvp_exists',
            'Orbit\Controller\API\v1\BrandProduct\Validator\BrandProductValidator@reservationExists'
        );

        Validator::extend(
            'orbit.brand_product_variant.can_cancel_rsvp',
            'Orbit\Controller\API\v1\BrandProduct\Validator\BrandProductValidator@reservationCanBeCanceled'
        );
    }
}
