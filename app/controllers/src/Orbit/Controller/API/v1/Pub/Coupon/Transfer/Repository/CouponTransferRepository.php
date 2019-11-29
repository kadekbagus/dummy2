<?php namespace Orbit\Controller\API\v1\Pub\Coupon\Transfer\Repository;

use Carbon\Carbon;
use DB;
use IssuedCoupon;
use Log;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Orbit\Controller\API\v1\Pub\Coupon\Transfer\Notifications\ConfirmTransferNotification;
use Orbit\Controller\API\v1\Pub\Coupon\Transfer\Notifications\TransferCanceledNotification;
use Orbit\Controller\API\v1\Pub\Coupon\Transfer\Notifications\TransferDeclinedNotification;
use User;

/**
 * Coupon transfer repository.
 */
class CouponTransferRepository
{
    /**
     * Instance of IssuedCoupon.
     * @var null
     */
    private $issuedCoupon = null;

    /**
     * Soon to be new owner.
     * @var [type]
     */
    private $recipient = null;

    /**
     * Current User, which can be real owner or the new owner
     * depends on the phase of transfer (starting or accepting/declining).
     *
     * @var [type]
     */
    private $currentUser = null;

    /**
     * Formatted response according to transfer action/state.
     * @var array
     */
    private $response = [];

    /**
     * When resolving this repo, we should have instance of IssuedCoupon first.
     *
     * @param [type] $issuedCoupon [description]
     */
    function __construct($currentUser = null)
    {
        $this->currentUser = $currentUser;
        $this->response = (object) ['issued_coupon_id' => null];
    }

    /**
     * Get the Owner (User) instance.
     *
     * @return [type] [description]
     */
    public function getOwner()
    {
        if ($this->currentUser->roleIs(['guest']) && ! isset($this->issuedCoupon->user)) {
            $this->issuedCoupon->load('user');
            $this->currentUser = $this->issuedCoupon->user;
        }

        return $this->currentUser;
    }

    /**
     * New Owner would be current logged in User which
     * triggerring the accept transfer request.
     *
     * @return [type] [description]
     */
    public function getNewOwner()
    {
        return $this->currentUser;
    }

    /**
     * Get Recipient User (soon-to-be the new owner).
     *
     * @return [type] [description]
     */
    public function getRecipient($recipientEmail = null)
    {
        if (! empty($recipientEmail)) {
            $this->recipient = new User;
            $this->recipient->email = $recipientEmail;
        }
        else if (empty($this->recipient)) {
            $this->recipient = new User;
            $this->recipient->email = $this->issuedCoupon->transfer_email;
        }

        return $this->recipient;
    }

    /**
     * Find IssuedCoupon that will be transfered.
     *
     * @param  [type] $issuedCouponId [description]
     * @return [type]                 [description]
     */
    public function findIssuedCouponForTransfer($issuedCouponId)
    {
        $this->issuedCoupon = IssuedCoupon::with(['user', 'coupon' => function($couponQuery) {
                                                $couponQuery->select(
                                                    'promotion_id',
                                                    'promotion_name',
                                                    'image'
                                                );
                                            }])
                                            ->where('user_id', $this->currentUser->user_id)
                                            ->where('status', 'issued')
                                            ->where('issued_coupon_id', $issuedCouponId)
                                            ->whereNull('transfer_start_at')
                                            ->first();

        return $this->issuedCoupon;
    }

    /**
     * Find IssuedCoupon that will be accepted or declined.
     * We don't filter by current logged in User.
     *
     * @param  [type] $issuedCouponId [description]
     * @return [type]                 [description]
     */
    public function findIssuedCouponForAcceptanceOrDecline($issuedCouponId)
    {
        $this->issuedCoupon = IssuedCoupon::where('status', 'issued')
                                            ->where('issued_coupon_id', $issuedCouponId)
                                            ->where('transfer_status', 'in_progress')
                                            ->first();

        return $this->issuedCoupon;
    }

    /**
     * Get IssuedCoupon instance.
     * @return [type] [description]
     */
    public function getIssuedCoupon()
    {
        return $this->issuedCoupon;
    }

    /**
     * Mark coupon transfer as started (in_progress).
     *
     * @return [type] [description]
     */
    public function start()
    {
        DB::transaction(function() {
            $this->issuedCoupon->transfer_start_at = Carbon::now();
            $this->issuedCoupon->transfer_status = 'in_progress';
            $this->issuedCoupon->transfer_name = OrbitInput::post('name');
            $this->issuedCoupon->transfer_email = OrbitInput::post('email');
            $this->issuedCoupon->save();
        });

        // Send notification/confirmation email to new owner...
        if ($this->issuedCoupon->transfer_status === 'in_progress') {
            $this->response->transfer_status = 'started';
            $this->getRecipient()->notify(new ConfirmTransferNotification($this->issuedCoupon, $this->issuedCoupon->transfer_name));
        }
    }

    /**
     * Accept coupon transfer.
     *
     * @param  [type] $newOwnerId [description]
     * @return void
     */
    public function accept()
    {
        DB::transaction(function() {
            $this->issuedCoupon->original_user_id = $this->issuedCoupon->user_id;
            $this->issuedCoupon->transfer_status = 'complete';
            $this->issuedCoupon->transfer_complete_at = Carbon::now();
            $this->issuedCoupon->user_id = $this->getNewOwner()->user_id; // change to new owner
            $this->issuedCoupon->save();

        });

        // Not sending any notification?
        if ($this->issuedCoupon->transfer_status === 'complete') {
            $this->response->transfer_status = 'accepted';
        }
    }

    /**
     * Decline the coupon transfer.
     * Basically reset all coupon transfer fields to null.
     * @return void
     */
    public function decline()
    {
        $recipientName = $this->issuedCoupon->transfer_name;
        $recipientEmail = $this->issuedCoupon->transfer_email;
        DB::transaction(function() {
            $this->issuedCoupon->resetTransfer();
        });

        // Notify to original owner that the coupon was rejected by recipient.
        if (empty($this->issedCoupon->transfer_status)) {
            $this->response->transfer_status = 'declined';
            $this->getOwner()->notify(new TransferDeclinedNotification($this->issuedCoupon, $recipientName));
        }
    }

    /**
     * Cancel the transfer.
     *
     * @return [type] [description]
     */
    public function cancel()
    {
        $recipientName = $this->issuedCoupon->transfer_name;
        $recipientEmail = $this->issuedCoupon->transfer_email;
        DB::transaction(function() {
            $this->issuedCoupon->resetTransfer();
        });

        // Notify for cancelation.
        if (empty($this->issedCoupon->transfer_status)) {
            $this->response->transfer_status = 'canceled';
            $this->getRecipient($recipientEmail)->notify(new TransferCanceledNotification(
                $this->issuedCoupon,
                $recipientName
            ));
        }
    }

    /**
     * Get formatted response data for transfer request.
     *
     * @return [type] [description]
     */
    public function getResponseData()
    {
        $this->response->issued_coupon_id = $this->getIssuedCoupon()->issued_coupon_id;
        return $this->response;
    }
}
