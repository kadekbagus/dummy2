<?php

namespace Orbit\Notifications\Traits;

use BppUser;
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
                'store.mall',
                'details.variant_details',
            ])
            ->findOrFail($reservationId);
    }

    protected function getReservationData()
    {
        $data = [
            'store'         => $this->getStore(),
            'reservationTime' => $this->formatDate($this->reservation->created_at),
            'expirationTime' => $this->formatDate($this->reservation->expired_at),
            'cancelledTime' => $this->formatDate($this->reservation->updated_at),
            'declinedTime' => $this->formatDate($this->reservation->updated_at),
            'pickupTime' => $this->formatDate($this->reservation->updated_at),
            'totalPayment' => $this->getTotalPayment(),
            'status'        => $this->reservation->status,
            'reason' => $this->reservation->cancel_reason,
            'products' => $this->getReservationProducts(),
            'myReservationUrl' => $this->getMyReservationUrl('/products?country=Indonesia'),
        ];

        return $data;
    }

    protected function formatDate($date)
    {
        return Carbon::parse($date)
            ->timezone('Asia/Jakarta')
            ->format('d F Y, H:i') . ' (WIB)';
    }

    protected function getStore()
    {
        $store = $this->reservation->store;
        return [
            'storeId' => $store->merchant_id,
            'storeName' => $store->name,
            'mallName' => $store->mall->name,
        ];
    }

    protected function getTotalPayment()
    {
        return 'Rp ' . number_format($this->reservation->total_amount, 2, '.', ',');
    }

    protected function getReservationProducts()
    {
        $products = [];

        foreach($this->reservation->details as $detail) {
            $products[] = [
                'name' => $detail->product_name,
                'variant' => $detail->variant_details->implode('value', ', '),
                'sku' => $detail->sku,
                'barcode' => $detail->product_code,
                'quantity' => $detail->quantity,
                'price' => $detail->selling_price,
                'total_price' => 'Rp ' . number_format(
                    $detail->quantity * $detail->selling_price, 2, '.', ','
                ),
            ];
        }

        return $products;
    }

    protected function getMyReservationUrl($path)
    {
        return Config::get(
            'orbit.transaction.my_purchases_url',
            'https://www.gotomalls.com/my/purchases'
        ) . $path;
    }

    protected function getSeeReservationUrl()
    {
        return sprintf(
            Config::get('orbit.reservation.see_reservation_url', '#'),
            $this->reservation->brand_product_reservation_id
        );
    }

    protected function getAdminRecipients()
    {
        $recipients = [];

        $store = $this->getStore();
        $brandId = $this->reservation->brand_id;
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
