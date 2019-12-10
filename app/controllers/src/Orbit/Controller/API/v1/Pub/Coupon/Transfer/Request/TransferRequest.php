<?php namespace Orbit\Controller\API\v1\Pub\Coupon\Transfer\Request;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Validator;
use Orbit\Controller\API\v1\Pub\Coupon\Transfer\Repository\CouponTransferRepository;

/**
 * Coupon Transfer Form Request.
 *
 * @todo  create proper form request helper.
 * @todo  find a way to properly inject current user into request (might be a service)
 * @author Budi <budi@dominopos.com>
 */
class TransferRequest extends FormRequest
{
    /**
     * Allowed roles to access this request.
     * @var array
     */
    protected $roles = ['consumer'];

    /**
     * Get validation rules.
     *
     * @return array array of rules for this request.
     */
    public function rules()
    {
        return [
            'issued_coupon_id' => 'bail|required|available.for.transfer',
            'name' => 'required',
            'email'  => 'required|email|distinct_with_owner_email',
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
            'available.for.transfer' => 'COUPON_NOT_AVAILABLE_FOR_TRANSFER',
            'distinct_with_owner_email' => 'RECIPIENT_EMAIL_SAME_AS_OWNER_EMAIL',
        ];
    }

    /**
     * Register custom validation for coupon transfer.
     *
     * @return void
     */
    public function registerCustomValidations()
    {
        // @todo create proper bail-on-first-validation-error rule.
        Validator::extend('bail', function($attribute, $value, $parameters, $validator) {
            $this->bail = true;

            return true;
        });

        // Validate that issued coupon available for transferring.
        Validator::extend('available.for.transfer', function($attribute, $issuedCouponId, $parameters) {
            $couponTransfer = App::make(CouponTransferRepository::class);
            $couponTransfer->setUser($this->user);
            return ! empty($couponTransfer->findIssuedCouponForTransfer($issuedCouponId));
        });

        // Validate that requested IssuedCoupon is in available for accept state (in_progress)
        Validator::extend('available_for_accept_or_decline', function($attribute, $issuedCouponId, $parameters) {
            $couponTransfer = App::make(CouponTransferRepository::class);

            return ! empty($couponTransfer->findIssuedCouponForAcceptanceOrDecline($issuedCouponId));
        });

        // Validate that current logged in user's email = transfer_email.
        Validator::extend('match_transfer_email', function($attribute, $issuedCouponId, $parameters, $validator) {
            $validatorData = $validator->getData();
            $couponTransfer = App::make(CouponTransferRepository::class);
            $issuedCoupon = $couponTransfer->getIssuedCoupon();

            return ! empty($issuedCoupon)
                && $issuedCoupon->transfer_email === $this->user->user_email
                && $issuedCoupon->transfer_email === $validatorData['email'];
        });

        // Validate that request email = issued coupon transfer_email.
        Validator::extend('match_transfer_email_only', function($attribute, $issuedCouponId, $parameters, $validator) {
            $validatorData = $validator->getData();
            $couponTransfer = App::make(CouponTransferRepository::class);
            $issuedCoupon = $couponTransfer->getIssuedCoupon();

            return ! empty($issuedCoupon) && $issuedCoupon->transfer_email === $validatorData['email'];
        });

        // Validate that request is being requested by coupon owner.
        Validator::extend('requested_by_owner', function() {
            $couponTransfer = App::make(CouponTransferRepository::class);
            $issuedCoupon = $couponTransfer->getIssuedCoupon();

            return ! empty($issuedCoupon) && $issuedCoupon->user_id === $this->user->user_id;
        });

        // Validate that recipient email !== currently logged in user's email.
        Validator::extend('distinct_with_owner_email', function($attribute, $email, $parameters) {
            return $email !== $this->user->user_email;
        });
    }
}
