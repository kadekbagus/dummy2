<?php

namespace Orbit\Notifications\Reservation\BrandProduct;

use BppUser;
use Orbit\Notifications\Reservation\ReservationNotification;

/**
 * Cancel reservation notification email.
 *
 * @author Budi <budi@gotomalls.com>
 */
class ReservationCanceledNotification extends ReservationNotification
{
    protected $signature = 'reservation-canceled-notification';

    public function getRecipientEmail()
    {
        $recipients = [];

        $store = $this->getStore();
        $brandId = $this->reservation->brand_product_variant->brand_product->brand_id;
        $allAdmin = BppUser::where('status', 'active')
            ->where('base_merchant_id', $brandId)
            ->where(function($query) use ($store) {
                $query->where('user_type', 'brand')
                    ->orWhere('merchant_id', $store['storeId']);
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

    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.reservation.canceled',
        ];
    }

    public function getEmailSubject()
    {
        return trans('email-reservation.canceled.subject');
    }
}
