<?php

namespace Orbit\Notifications\Traits;

use BppUser;
use Carbon\Carbon;
use BrandProductReservation;
use Illuminate\Support\Facades\Config;
use PaymentTransaction;

trait HasOrderTrait
{
    protected $transactionData = [];

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
     * @todo move to HasPaymentTrait
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

    protected function formatDate($date)
    {
        return Carbon::parse($date)
            ->timezone('Asia/Jakarta')
            ->format('D, d F Y, H:i') . ' (WIB)';
    }

    protected function getTotalPayment()
    {
        $total = $this->reservation->quantity
            * $this->reservation->selling_price;

        return 'Rp ' . number_format($total, 2, '.', ',');
    }

    protected function getVariant()
    {
        return strtoupper($this->reservation->variants->implode('value', ', '));
    }

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
