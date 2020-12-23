<?php

namespace Orbit\Notifications\Reservation\BrandProduct;

use Orbit\Notifications\Reservation\ReservationNotification;

/**
 * Notification when a reservation is made.
 *
 * @author Budi <budi@gotomalls.com>
 */
class ReservationMadeNotification extends ReservationNotification
{
    protected $signature = 'reservation-made-notification';

    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.reservation.made',
        ];
    }

    public function getEmailSubject()
    {
        return trans('email-reservation.made.subject');
    }

    protected function getReservationData()
    {
        return array_merge(parent::getReservationData(), [
            'acceptUrl' => $this->getAcceptUrl(),
            'declineUrl' => $this->getDeclineUrl(),
        ]);
    }
}
