<?php namespace Orbit\Controller\API\v1\Pub\Coupon\Transfer\Request;

/**
 * Coupon Transfer Cancel Request.
 *
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
            'issued_coupon_id' => 'required|issued_coupon_exists|requested_by_owner|transfer_not_completed_yet|transfer_in_progress',
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

            // Make sure the transfer is in progress.
            'transfer_in_progress' => 'TRANSFER_NOT_STARTED_YET_OR_ALREADY_CANCELED',

            // Make sure cancelation is being requested by the coupon owner.
            'requested_by_owner' => 'TRANSFER_INVALID_REQUEST_USER',
        ];
    }
}
