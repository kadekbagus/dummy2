<?php

namespace Orbit\Notifications\Reservation\BrandProduct;

use Orbit\Notifications\Reservation\ReservationNotification;

/**
 * Notification when a reservation is accepted.
 *
 * @author Budi <budi@gotomalls.com>
 */
class ReservationExpiredAdminNotification extends ReservationNotification
{
    protected $signature = 'reservation-expired-admin-notification';

    public function getRecipientEmail()
    {
        return $this->getAdminRecipients();
    }

    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.reservation.expired-admin',
        ];
    }

    public function getEmailSubject()
    {
        return trans('email-reservation.expired.subject', [], '', 'en');
    }
}
