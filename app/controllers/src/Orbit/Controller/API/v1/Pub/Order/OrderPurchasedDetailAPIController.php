<?php

namespace Orbit\Controller\API\v1\Pub\Order;

use Exception;
use OrbitShop\API\v1\PubControllerAPI;
use Orbit\Controller\API\v1\Pub\Order\Request\OrderPurchasedDetailRequest;
use Orbit\Controller\API\v1\Pub\Order\Resource\OrderPurchasedResource;
use Orbit\Helper\Resource\MediaQuery;
use PaymentTransaction;

class OrderPurchasedDetailAPIController extends PubControllerAPI
{
    use MediaQuery;

    protected $imagePrefix = 'brand_product_main_photo_';

    protected $imageVariants = ['desktop_thumb'];

    /**
     * GET - get detail of order transaction detail
     *
     * @author Budi <budi@gotomalls.com>
     *
     * @param Orbit\Helper\Request\ValidateRequest
     * @return Illuminate\Support\Facades\Response
     */
    public function handle(OrderPurchasedDetailRequest $request)
    {
        try {

            $this->setupImageUrlQuery();

            $this->response->data = new OrderPurchasedResource(
                PaymentTransaction::with([
                        'details.order.details.order_variant_details',
                        'details.order.store' => function($query) {
                            $this->imagePrefix = 'retailer_logo_';
                            $this->imageVariants = ['orig'];
                            $query->with(['mall'] + $this->buildMediaQuery());
                        },
                        'details.order.details.brand_product_variant.brand_product' => function($query) {
                            $this->imagePrefix = 'brand_product_main_photo_';
                            $this->imageVariants = ['desktop_thumb'];
                            $query->with($this->buildMediaQuery());
                        },
                        'midtrans',
                    ])
                    ->where('payment_transaction_id', $request->payment_transaction_id)
                    ->orWhere('external_payment_transaction_id', $request->payment_transaction_id)
                    ->firstOrFail()
            );

        } catch (Exception $e) {
            return $this->handleException($e, false);
        }

        return $this->render();
    }
}
