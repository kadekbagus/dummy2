<?php

namespace Orbit\Notifications\Reservation\BrandProduct;

use Orbit\Notifications\Reservation\ReservationNotification;

/**
 * Notification when a reservation is accepted.
 *
 * @author Budi <budi@gotomalls.com>
 */
class ReservationAcceptedNotification extends ReservationNotification
{
    protected $signature = 'reservation-accepted-notification';

    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.reservation.accepted',
        ];
    }

    public function getEmailSubject()
    {
        return trans('email-reservation.accepted.subject', [], '', 'id');
    }

    protected function getEnabledLanguages()
    {
        return ['id', 'en'];
    }
}
