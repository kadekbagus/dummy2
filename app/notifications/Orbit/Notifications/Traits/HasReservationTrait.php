<?php

namespace Orbit\Notifications\Traits;

use Carbon\Carbon;
use BrandProductReservation;

trait HasReservationTrait
{
    protected $reservation;

    protected function getReservation($reservationId)
    {
        return BrandProductReservation::onWriteConnection()
            ->with([
                'users',
                'brand_product_variant.brand_product.creator',
                'store.store.mall',
                'variants',
            ])
            ->where('brand_product_reservation_id', $reservationId)
            ->first();
    }

    protected function getReservationData()
    {
        $data = [
            'store'         => $this->getStore(),
            'reservationTime' => $this->formatDate($this->reservation->created_at),
            'expirationTime' => $this->formatDate($this->reservation->expired_at),
            'quantity'      => $this->reservation->quantity,
            'totalPayment' => $this->getTotalPayment(),
            'status'        => $this->reservation->status,
            'product' => [
                'name' => $this->reservation->product_name,
                'variant' => $this->getVariant(),
                'sku' => $this->reservation->brand_product_variant->sku,
                'barcode' => $this->reservation->brand_product_variant->product_code ?: '-',
            ],
        ];

        return $data;
    }

    protected function formatDate($date)
    {
        return Carbon::parse($date)
            ->timezone('Asia/Jakarta')
            ->format('D, d F Y, H:i') . ' (WIB)';
    }

    protected function getStore()
    {
        $store = $this->reservation->store->store;
        return [
            'storeName' => $store->name,
            'mallName' => $store->mall->name,
        ];
    }

    protected function getTotalPayment()
    {
        $total = $this->reservation->quantity
            * $this->reservation->brand_product_variant->selling_price;

        return number_format($total, 2, '.', ',');
    }

    protected function getVariant()
    {
        return $this->reservation->variants->implode('value', ',');
    }

    protected function getAcceptUrl()
    {
        return '#';
    }

    protected function getDeclineUrl()
    {
        return '#';
    }
}
