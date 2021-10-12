<?php

namespace Orbit\Controller\API\v1\Pub\Reservation\Request;

use Illuminate\Support\Facades\Validator;
use Orbit\Helper\Request\ValidateRequest;

/**
 * Reserve request.
 *
 * @author Budi <budi@gotomalls.com>
 */
class ReservationPickedUpRequest extends ValidateRequest
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
                        'orbit.brand_product_variant.rsvp_match_user',
                        'orbit.brand_product_variant.can_change_status:picked_up',
                    ]),
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
            'orbit.brand_product_variant.rsvp_exists',
            'Orbit\Controller\API\v1\BrandProduct\Validator\BrandProductValidator@reservationExists'
        );

        Validator::extend(
            'orbit.brand_product_variant.rsvp_match_user',
            'Orbit\Controller\API\v1\BrandProduct\Validator\BrandProductValidator@matchReservationUser'
        );

        Validator::extend(
            'orbit.brand_product_variant.can_change_status',
            'Orbit\Controller\API\v1\BrandProduct\Validator\BrandProductValidator@reservationStatusCanBeChanged'
        );
    }
}
