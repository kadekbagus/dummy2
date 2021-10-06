<?php

namespace Orbit\Notifications\Reservation\BrandProduct;

use Orbit\Notifications\Reservation\ReservationNotification;

/**
 * Notification when a reservation is accepted.
 *
 * @author Budi <budi@gotomalls.com>
 */
class ReservationExpiredNotification extends ReservationNotification
{
    protected $signature = 'reservation-expired-notification';

    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.reservation.expired',
        ];
    }

    public function getEmailSubject()
    {
        return trans('email-reservation.expired.subject', [], '', 'en');
    }

    protected function getSupportedLanguages()
    {
        return ['id', 'en'];
    }
}
