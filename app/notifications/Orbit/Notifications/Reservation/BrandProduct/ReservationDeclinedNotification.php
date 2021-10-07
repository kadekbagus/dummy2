<?php

namespace Orbit\Notifications\Reservation\BrandProduct;

use Orbit\Notifications\Reservation\ReservationNotification;

/**
 * Notification when a reservation is accepted.
 *
 * @author Budi <budi@gotomalls.com>
 */
class ReservationDeclinedNotification extends ReservationNotification
{
    protected $signature = 'reservation-declined-notification';

    public function __construct($reservation = null)
    {
        parent::__construct($reservation);
    }

    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.reservation.declined',
        ];
    }

    public function getEmailSubject()
    {
        return trans('email-reservation.declined.subject', [], '', 'en');
    }

    protected function getSupportedLanguages()
    {
        return ['id', 'en'];
    }
}
