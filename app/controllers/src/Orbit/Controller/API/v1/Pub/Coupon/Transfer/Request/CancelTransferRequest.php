<?php namespace Orbit\Controller\API\v1\Pub\Coupon\Transfer\Request;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Validator;
use OrbitShop\API\v1\OrbitShopAPI;
use Orbit\Controller\API\v1\Pub\Coupon\Transfer\Repository\CouponTransferRepository;

/**
 * Coupon Transfer Cancel Request.
 *
 * @todo  create proper form request helper.
 * @todo  find a way to properly inject current user into request (might be a service)
 * @author Budi <budi@dominopos.com>
 */
class CancelTransferRequest extends TransferRequest
{
    /**
     * Get validation rules.
     *
     * @return array array of rules for this request.
     */
    public function rules()
    {
        return [
            'issued_coupon_id' => 'required|available_for_accept_or_decline|requested_by_owner',
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
            'available_for_accept_or_decline' => 'NOT_AVAILABLE_FOR_CANCEL',
            'requested_by_owner' => 'REQUESTED_BY_OTHER_USER',
        ];
    }
}
