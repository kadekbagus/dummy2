<?php namespace Orbit\Controller\API\v1\Pub\Coupon\Transfer\Request;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Validator;
use OrbitShop\API\v1\OrbitShopAPI;
use Orbit\Controller\API\v1\Pub\Coupon\Transfer\Repository\CouponTransferRepository;

/**
 * Coupon Transfer Accept Form Request.
 *
 * @todo  create proper form request helper.
 * @todo  find a way to properly inject current user into request.
 * @author Budi <budi@dominopos.com>
 */
class AcceptTransferRequest extends TransferRequest
{
    /**
     * Get validation rules.
     *
     * @return array array of rules for this request.
     */
    public function rules()
    {
        return [
            'issued_coupon_id' => 'required|issued_coupon_exists|transfer_not_completed_yet|transfer_in_progress|match_transfer_email',
            'email' => 'required|email',
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
            'issued_coupon_exists' => 'COUPON_NOT_FOUND',

            // Means that the transfer already completed,
            // so we need to display proper message.
            'transfer_not_completed_yet' => 'TRANSFER_ALREADY_COMPLETED',

            // Means that the transfer is not in 'in_progress' state.
            'transfer_in_progress' => 'TRANSFER_NOT_STARTED_YET',

            // means the transfer should be accepted by user that logged in with
            // same email as transfer_email.
            'match_transfer_email' => 'USER_NOT_ALLOWED_TO_ACCEPT',
        ];
    }
}
