<?php

namespace Orbit\Notifications\Traits;

use Carbon\Carbon;
use BrandProductReservation;
use Illuminate\Support\Facades\Config;

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
            'reason' => $this->reservation->cancel_reason,
            'product' => [
                'name' => $this->reservation->product_name,
                'variant' => $this->getVariant(),
                'sku' => $this->reservation->sku,
                'barcode' => $this->reservation->product_code ?: '-',
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
            'storeId' => $store->merchant_id,
            'storeName' => $store->name,
            'mallName' => $store->mall->name,
        ];
    }

    protected function getTotalPayment()
    {
        $total = $this->reservation->quantity
            * $this->reservation->selling_price;

        return 'Rp ' . number_format($total, 2, '.', ',');
    }

    protected function getVariant()
    {
        return $this->reservation->variants->implode('value', ', ');
    }

    protected function getSeeReservationUrl()
    {
        return sprintf(
            Config::get('orbit.reservation.see_reservation_url', '#'),
            $this->reservation->brand_product_reservation_id
        );
    }
}
