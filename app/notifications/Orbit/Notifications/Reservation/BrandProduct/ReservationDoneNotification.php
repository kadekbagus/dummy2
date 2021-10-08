<?php

namespace Orbit\Notifications\Reservation\BrandProduct;

use Orbit\Notifications\Reservation\ReservationNotification;

/**
 * Notification when a reservation is accepted.
 *
 * @author Budi <budi@gotomalls.com>
 */
class ReservationDoneNotification extends ReservationNotification
{
    protected $signature = 'reservation-done-notification';

    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.reservation.done',
        ];
    }

    public function getEmailSubject()
    {
        return trans('email-reservation.done.subject', [], '', 'en');
    }

    protected function getSupportedLanguages()
    {
        return ['id', 'en'];
    }
}
