<?php namespace Orbit\Controller\API\v1\Pub\Coupon\Transfer\Request;

/**
 * Coupon Transfer Decline Form Request.
 *
 * @todo  create proper form request helper.
 * @todo  find a way to properly inject current user into request (might be a service)
 * @author Budi <budi@dominopos.com>
 */
class DeclineTransferRequest extends TransferRequest
{
    protected $roles = ['guest', 'consumer'];

    /**
     * Get validation rules.
     *
     * @return array array of rules for this request.
     */
    public function rules()
    {
        return [
            'issued_coupon_id' => 'required|available_for_accept_or_decline',
            'email' => 'required|email|match_transfer_email_only',
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
            'available_for_accept_or_decline' => 'NOT_AVAILABLE_FOR_DECLINE',
            'match_transfer_email_only' => 'REQUEST_EMAIL_NOT_MATCH',
        ];
    }
}
