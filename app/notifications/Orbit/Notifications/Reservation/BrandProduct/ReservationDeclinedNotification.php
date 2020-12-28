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

    protected $reason = '';

    public function __construct($reservation = null, $reason = 'Out of Stock')
    {
        parent::__construct($reservation);
        $this->reason = $reason;
    }

    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.reservation.declined',
        ];
    }

    public function getEmailSubject()
    {
        return trans('email-reservation.declined.subject', [], '', 'id');
    }

    public function getEmailData()
    {
        return array_merge(parent::getEmailData(), [
            'reason' => $this->reason,
        ]);
    }

    protected function getEnabledLanguages()
    {
        return ['id', 'en'];
    }
}
