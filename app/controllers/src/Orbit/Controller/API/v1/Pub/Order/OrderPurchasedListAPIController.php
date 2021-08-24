<?php

namespace Orbit\Controller\API\v1\Pub\Order;

use Exception;
use Illuminate\Support\Facades\DB;
use OrbitShop\API\v1\PubControllerAPI;
use Orbit\Controller\API\v1\Pub\Order\Request\OrderPurchasedListRequest;
use Orbit\Controller\API\v1\Pub\Order\Resource\OrderPurchasedCollection;
use Orbit\Helper\Resource\MediaQuery;
use Order;
use PaymentTransaction;

/**
 * Order purchased list.
 *
 * @author Budi <budi@gotomalls.com>
 */
class OrderPurchasedListAPIController extends PubControllerAPI
{
    use MediaQuery;

    protected $imagePrefix = '';

    protected $imageVariants = ['desktop_thumb', 'resized_default'];

    public function handle(OrderPurchasedListRequest $request)
    {
        try {
            $this->setupImageUrlQuery();

            $purchases = Order::with([
                    'details' => function($query) {
                        $query->with([
                            'order_variant_details',
                            'brand_product_variant' => function($query) {
                                $query->with([
                                    'brand_product' => function($query) {
                                        $this->imagePrefix = 'brand_product_main_photo_';
                                        $query->with($this->buildMediaQuery());
                                    },
                                ]);
                            },
                        ]);
                    },
                    'store' => function($query) {
                        $this->imagePrefix = 'retailer_logo_';
                        $query->with(['mall'] + $this->buildMediaQuery());
                    },
                    'payment_detail.payment'
                ])
                ->where('user_id', $request->user()->user_id)
                ->has('store')
                ->whereHas('payment_detail', function($query) {
                    $query->whereHas('payment', function($query) {
                        $query->where('status', '<>', PaymentTransaction::STATUS_STARTING)
                            ->whereNotNull('payment_transactions.external_payment_transaction_id');
                    });
                })
                ->latest();

            $purchasesCount = clone $purchases;

            $purchases->skip($request->skip)->take($request->getTake());

            $this->response->data = new OrderPurchasedCollection(
                $purchases->get(),
                $purchasesCount->count('order_id')
            );

        } catch (Exception $e) {
            return $this->handleException($e, false);
        }

        return $this->render();
    }
}
