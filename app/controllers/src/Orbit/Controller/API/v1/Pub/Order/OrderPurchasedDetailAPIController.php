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
                        'details.order.details.brand_product_variant.brand_product' => function($query) {
                            $query->with($this->buildMediaQuery());
                        },
                        'midtrans',
                        'discount_code' => function($discountCodeQuery) {
                            $discountCodeQuery->select(
                                'payment_transaction_id',
                                'discount_code_id',
                                'discount_id',
                                'discount_code as used_discount_code'
                            )->with([
                                'discount' => function($discountDetailQuery) {
                                    $discountDetailQuery->select(
                                        'discount_id',
                                        'discount_code as parent_discount_code',
                                        'discount_title',
                                        'value_in_percent as percent_discount'
                                    );
                            }]);
                        },
                        'discount' => function($discountQuery) {
                            $discountQuery->select(
                                'payment_transaction_id',
                                'object_id',
                                'price as discount_amount'
                            )->with([
                                'discount' => function($discountQuery) {
                                    $discountQuery->select(
                                        'discount_id',
                                        'discount_code as parent_discount_code',
                                        'discount_title',
                                        'value_in_percent as percent_discount'
                                    );
                            }]);
                        },
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
