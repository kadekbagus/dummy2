<?php

namespace Orbit\Notifications\Traits;

use BppUser;
use Carbon\Carbon;
use BrandProductReservation;
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
            'date'      => $this->payment->getTransactionDate(),
            'customer'  => $this->getCustomerData(),
            'itemName'  => null,
            'otherProduct' => -1,
            'items'     => [],
            'discounts' => [],
            'total'     => $this->payment->getGrandTotal(),
        ];

        foreach($this->payment->details as $item) {
            $detailItem = [
                'name'      => $item->object_name,
                'shortName' => $item->object_name,
                'variant'   => '',
                'quantity'  => $item->quantity,
                'price'     => $item->getPrice(),
                'total'     => $item->getTotal(),
            ];

            if ($item->order) {
                foreach($item->order->details as $orderDetail) {
                    $product = $orderDetail->brand_product_variant;
                    $detailItem = [
                        'name'      => $product->brand_product->product_name,
                        'shortName' => $product->brand_product->product_name,
                        'variant'   => $this->getVariant($orderDetail),
                        'quantity'  => $orderDetail->quantity,
                        'price'     => $this->formatCurrency($orderDetail->selling_price, $item->currency),
                        'total'     => $this->formatCurrency($item->order->total_amount, $item->currency),
                    ];

                    if (empty($transaction['itemName'])) {
                        $transaction['itemName'] = $detailItem['name'];
                    }

                    $transaction['items'][] = $detailItem;
                    $transaction['otherProduct']++;
                }
            }
            else if ($item->price < 0 || $item->object_type === 'discount') {
                $discount = Discount::select('value_in_percent')->find($item->object_id);
                $discount = ! empty($discount) ? $discount->value_in_percent . '%' : '';
                $detailItem['name'] = $discount;
                $detailItem['quantity'] = '';
                $transaction['discounts'][] = $detailItem;
            }
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
     * Get admin/store user email recipients details.
     *
     * @return array $recipients - list of admin/store user recipient details
     */
    protected function getAdminRecipients()
    {
        $recipients = [];

        $stores = $this->getStores();
        $brandId = $this->reservation->brand_product_variant->brand_product->brand_id;
        $allAdmin = BppUser::with(['stores'])
            ->where('status', 'active')
            ->where('base_merchant_id', $brandId)
            ->where(function($query) use ($store) {
                $query->where('user_type', 'brand')
                    ->orWhereHas('stores', function($query) use ($store) {
                        $query->where('bpp_user_merchants.merchant_id', $store['storeId']);
                    });
            })
            ->get();

        foreach($allAdmin as $admin) {
            $recipients[$admin->bpp_user_id] = [
                'name' => $admin->name,
                'email' => $admin->email,
            ];
        }

        return $recipients;
    }
}
