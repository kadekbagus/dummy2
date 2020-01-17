<?php namespace Orbit\Controller\API\v1\Pub\Coupon\Transfer;

use Exception;
use Illuminate\Support\Facades\App;
use OrbitShop\API\v1\PubControllerAPI;
use Orbit\Controller\API\v1\Pub\Coupon\Transfer\Repository\CouponTransferRepository;
use Orbit\Controller\API\v1\Pub\Coupon\Transfer\Request\CancelTransferRequest;

/**
 * Cancel Coupon Transfer handler.
 *
 * @author Budi <budi@gotomalls.com>
 */
class CancelTransferAPIController extends PubControllerAPI
{
    /**
     * Handle for canceling Coupon transfer.
     *
     * @return Illuminate\Http\Response
     */
    public function postCancelTransfer()
    {
        $httpCode = 200;

        try {
            // @todo Should be able to type-hint from method/constructor.
            // If request valid, there should be an instance of IssuedCoupon
            // available in the container.
            (new CancelTransferRequest($this))->validate();

            // Cancel coupon transfer...
            $couponTransfer = App::make(CouponTransferRepository::class);
            $couponTransfer->cancel();

            // record activity
            // $couponTransfer->getNewOwner()->activity(new CouponTransferAcceptedActivity());

            $this->response->data = $couponTransfer->getResponseData();

        } catch (Exception $e) {
            return $this->handleException($e, false);
        }

        return $this->render($httpCode);
    }
}
