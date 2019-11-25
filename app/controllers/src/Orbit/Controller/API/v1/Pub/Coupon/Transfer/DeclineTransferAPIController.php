<?php namespace Orbit\Controller\API\v1\Pub\Coupon\Transfer;

use Exception;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Response;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use OrbitShop\API\v1\PubControllerAPI;
use Orbit\Controller\API\v1\Pub\Coupon\Transfer\Repository\CouponTransferRepository;
use Orbit\Controller\API\v1\Pub\Coupon\Transfer\Request\DeclineTransferRequest;

/**
 * Decline Coupon Transfer handler.
 */
class DeclineTransferAPIController extends PubControllerAPI
{
    /**
     * Handle Declining Coupon transfer.
     *
     * @return Illuminate\Http\Response
     */
    public function postDeclineTransfer()
    {
        $httpCode = 200;

        try {
            // @todo Should be able to type-hint from method/constructor.
            // If request valid, there should be an instance of IssuedCoupon
            // available in the container.
            (new DeclineTransferRequest())->auth($this)->validate();

            // Accept coupon transfer...
            $couponTransfer = App::make(CouponTransferRepository::class);
            $couponTransfer->decline();

            // record activity
            // $couponTransfer->getNewOwner()->activity(new CouponTransferAcceptedActivity());

            $this->response->data = $couponTransfer->getResponseData();

        } catch (Exception $e) {
            $response = $this->buildExceptionResponse($e, false);
            $httpCode = $response['httpCode'];
            $this->response = $response['body'];
        }

        return $this->render($httpCode);
    }
}
