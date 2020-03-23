<?php namespace Orbit\Controller\API\v1\Pub\Coupon\Transfer\Request;

/**
 * Coupon Transfer Decline Form Request.
 *
 * @todo  find a way to properly inject current user into request (might be a service)
 * @author Budi <budi@dominopos.com>
 */
class DeclineTransferRequest extends TransferRequest
{
    /**
     * Role 'guest' would allow customer to access the api without logging in.
     * @var [type]
     */
    protected $roles = ['guest', 'consumer'];

    /**
     * Get validation rules.
     *
     * @return array array of rules for this request.
     */
    public function rules()
    {
        return [
            'issued_coupon_id' => 'required|issued_coupon_exists|transfer_not_completed_yet|transfer_in_progress',
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
            'issued_coupon_exists' => 'COUPON_NOT_FOUND',

            // Means that the transfer already completed,
            // so we need to display proper message.
            'transfer_not_completed_yet' => 'TRANSFER_ALREADY_COMPLETED',

            // Means that the transfer is not in 'in_progress' state.
            'transfer_in_progress' => 'TRANSFER_NOT_STARTED_YET_OR_ALREADY_DECLINED',

            // Make sure request email match the transfer email/recipient email.
            'match_transfer_email_only' => 'REQUEST_EMAIL_NOT_MATCH',
        ];
    }
}
