<?php

namespace Orbit\Notifications\Reservation\BrandProduct;

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
        return $this->getAdminRecipients();
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

    protected function getReservationData()
    {
        return array_merge(parent::getReservationData(), [
            'showExpirationTime' => false,
            'showCancelledTime' => true,
        ]);
    }
}
