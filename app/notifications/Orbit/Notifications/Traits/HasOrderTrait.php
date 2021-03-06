<?php

namespace Orbit\Notifications\Traits;

use BppUser;
use Carbon\Carbon;
use BrandProductReservation;
use Discount;
use Illuminate\Support\Facades\Config;
use PaymentTransaction;

/**
 * Common method helpers related to notification for Product/Order transaction.
 *
 * @author Budi <budi@gotomalls.com>
 */
trait HasOrderTrait
{
    /**
     * Get the payment object instance based on given transaction id.
     *
     * @param  string $paymentId - payment transaction id
     * @return PaymentTransaction - payment transaction object instance.
     */
    protected function getPayment($paymentId)
    {
        return PaymentTransaction::onWriteConnection()->with([
                'details.order.details.order_variant_details',
                'details.order.details.brand_product_variant.brand_product',
                'details.order.store.mall',
                'midtrans',
                'user',
                'discount',
            ])->findOrFail($paymentId);
    }

    /**
     * @override
     * @return [type] [description]
     */
    protected function getTransactionData()
    {
        $transaction = [
            'id'        => $this->payment->payment_transaction_id,
            'itemName'  => null,
            'otherProduct' => -1,
            'orders'     => [],
            'discounts' => [],
            'total'     => $this->payment->getGrandTotal(),
        ];

        foreach($this->payment->details as $detail) {

            if ($detail->order) {
                foreach($detail->order->details as $orderDetail) {
                    $orderId = $detail->order->order_id;
                    $store = $detail->order->store;
                    $product = $orderDetail->brand_product_variant;

                    if (! isset($transaction['orders'][$orderId])) {
                        $transaction['orders'][$orderId] = [
                            'id' => $orderId,
                            'items' => [],
                            'store' => [
                                'storeId' => $detail->order->merchant_id,
                                'storeName' => $store->name,
                                'mallId' => $store->mall->merchant_id,
                                'mallName' => $store->mall->name,
                                'floor' => $store->floor,
                                'unit' => $store->unit,
                            ],
                        ];
                    }

                    $detailItem = [
                        'name'      => $product->brand_product->product_name,
                        'shortName' => $product->brand_product->product_name,
                        'variant'   => $this->getVariant($orderDetail),
                        'quantity'  => $orderDetail->quantity,
                        'price'     => $this->formatCurrency($orderDetail->selling_price, $detail->currency),
                        'total'     => $this->formatCurrency($orderDetail->selling_price * $orderDetail->quantity, $detail->currency),
                    ];

                    if (empty($transaction['itemName'])) {
                        $transaction['itemName'] = $detailItem['name'];
                    }

                    $transaction['orders'][$orderId]['items'][] = $detailItem;
                    $transaction['otherProduct']++;
                }
            }
            else if ($detail->price < 0 || $detail->object_type === 'discount') {
                $discount = Discount::select('value_in_percent')->find($detail->object_id);
                $discount = ! empty($discount) ? $discount->value_in_percent . '%' : '';
                $detailItem = [
                    'name'      => $discount,
                    'shortName' => $discount,
                    'price'     => $detail->getPrice(),
                    'total'     => $detail->getTotal(),
                ];
                $transaction['discounts'][] = $detailItem;
            }
        }

        if (isset($transaction['orders'])) {
            $transaction['orders'] = array_values($transaction['orders']);
        }

        return $transaction;
    }

    /**
     * Get product variant information.
     *
     * @param  OrderDetail $orderDetail Order Detail object instance.
     * @return string - variant information separated by comma (,).
     */
    protected function getVariant($orderDetail)
    {
        return $orderDetail->order_variant_details->implode('value', ', ');
    }

    /**
     * Get list of store id and brand id from the purchase.
     *
     * @return array $stores - array of the store id.
     */
    protected function getStoresAndBrands()
    {
        $data = [[], []];

        $this->payment->details->filter(function($detail) {
                return ! empty($detail->order);
            })->each(function($detail) use (&$data) {
                 $data[0][$detail->order->merchant_id] = $detail->order->merchant_id;
                 $data[1][$detail->order->brand_id] = $detail->order->brand_id;
            });

        return $data;
    }

    protected function getFollowUpOrderUrl($order)
    {
        $baseUrl = Config::get(
            'orbit.product_order.follow_up_url',
            'https://bpp.gotomalls.com/#!/orders/%s'
        );

        return  sprintf($baseUrl, $order->order_id);
    }

    /**
     * Get admin/store user email recipients details.
     *
     * @return array $recipients - list of admin/store user recipient details
     */
    protected function getAdminRecipients()
    {
        $storeAdmins = [];

        list($stores, $brands) = $this->getStoresAndBrands();
        $allAdmin = BppUser::with(['stores' => function($query) use ($stores) {
                $query->whereIn('merchants.merchant_id', $stores)
                    ->with(['mall']);
            }])
            ->where('status', 'active')
            ->whereIn('base_merchant_id', $brands)
            ->where(function($query) use ($stores) {
                $query->where('user_type', 'brand')
                    ->orWhereHas('stores', function($query) use ($stores) {
                        $query->whereIn('bpp_user_merchants.merchant_id', $stores);
                    });
            })
            ->get();

        $brandUsers = [];

        $allAdmin->each(function($admin) use (&$brandUsers) {
            if ($admin->user_type === 'brand') {
                $brandUsers[$admin->bpp_user_id] = [
                    'brand_id' => $admin->base_merchant_id,
                    'name' => $admin->name,
                    'email' => $admin->email,
                ];
            }
        });

        foreach($allAdmin as $admin) {
            $adminId = $admin->bpp_user_id;
            $brandId = $admin->base_merchant_id;

            foreach($admin->stores as $store) {
                $storeId = $store->merchant_id;

                if (! isset($storeAdmins[$storeId])) {
                    $storeAdmins[$storeId] = [
                        'admins' => [],
                        'details' => [
                            'storeName' => $store->name,
                            'mallName' => $store->mall->name,
                            'floor' => $store->floor,
                            'unit' => $store->unit,
                        ],
                    ];
                }

                foreach($brandUsers as $brandUserId => $brandUser) {
                    if ($brandUser['brand_id'] === $brandId) {
                        $storeAdmins[$storeId]['admins'][$brandUserId] = $brandUser;
                        break;
                    }
                }

                if (! isset($storeAdmins[$storeId]['admins'][$adminId])) {
                    $storeAdmins[$storeId]['admins'][$adminId] = [
                        'name' => $admin->name,
                        'email' => $admin->email,
                    ];
                }
            }
        }

        return $storeAdmins;
    }
}
