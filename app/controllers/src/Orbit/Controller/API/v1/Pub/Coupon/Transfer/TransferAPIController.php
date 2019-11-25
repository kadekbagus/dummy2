<?php namespace Orbit\Controller\API\v1\Pub\Coupon\Transfer;

use Exception;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Response;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use OrbitShop\API\v1\PubControllerAPI;
use Orbit\Controller\API\v1\Pub\Coupon\Transfer\Repository\CouponTransferRepository;
use Orbit\Controller\API\v1\Pub\Coupon\Transfer\Request\TransferRequest;

/**
 * Transfer Coupon handler.
 */
class TransferAPIController extends PubControllerAPI
{
    /**
     * Handle Coupon transfer  request.
     *
     * @return Illuminate\Http\Response
     */
    public function postTransfer()
    {
        $httpCode = 200;

        try {
            // @todo Should be able to type-hint from method/constructor.
            // If request valid, there should be an instance of IssuedCoupon
            // available in the container.
            (new TransferRequest())->auth($this)->validate();

            // Start coupon transfer...
            $couponTransfer = App::make(CouponTransferRepository::class);
            $couponTransfer->start();

            // record activity
            // $couponTransfer->getRecipient()->activity(new CouponTransferStartingActivity($couponTransfer));

            $this->response->data = $couponTransfer->getResponseData();

        } catch (Exception $e) {
            $response = $this->buildExceptionResponse($e, false);
            $httpCode = $response['httpCode'];
            $this->response = $response['body'];
        }

        return $this->render($httpCode);
    }
}
