<?php

namespace Orbit\Notifications\Reservation;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Config;
use Orbit\Helper\Notifications\Notification;
use Orbit\Notifications\Traits\HasContactTrait;
use Orbit\Notifications\Traits\HasReservationTrait;
use Orbit\Helper\Notifications\Contracts\EmailNotificationInterface;

/**
 * Base reservation notification.
 *
 * @author Budi <budi@gotomalls.com>
 */
abstract class ReservationNotification extends Notification implements
    EmailNotificationInterface
{
    use HasContactTrait, HasReservationTrait;

    protected $signature = 'reservation-notification';

    protected $shouldQueue = true;

    public function __construct($reservation)
    {
        $this->reservation = $reservation;
    }

    abstract public function getEmailTemplates();

    abstract public function getEmailSubject();

    public function getRecipientEmail()
    {
        return $this->reservation->brand_product_variant
            ->brand_product->creator->user_email;
    }

    /**
     * Here we only prepare small data which will be used in actual (heavy)
     * operations inside queue's call (that will be called later, after
     * returning response to the client).
     */
    public function getEmailData()
    {
        return [
            'reservationId'     => $this->reservation,
        ];
    }

    /**
     * We off-loading the heavy operations here into the queue's call
     * instead of synchronously on the request cycle (so it wouldn't slow down
     * response time).
     */
    public function toEmail($job, $data)
    {
        try {
            $this->reservation = $this->getReservation($data['reservationId']);

            $data += [
                'reservation' => $this->getReservationData(),
                'recipientEmail' => $this->getRecipientEmail(),
                'emailSubject' => $this->getEmailSubject(),
            ];

            $emailConfig = Config::get('orbit.registration.mobile.sender');

            Mail::send(
                $this->getEmailTemplates(),
                $data,
                function($mail) use ($data, $emailConfig) {
                    $mail->from($emailConfig['email'], $emailConfig['name']);
                    $mail->to($data['recipientEmail']);
                    $mail->subject($data['emailSubject']);
                }
            );

        } catch (Exception $e) {
            Log::debug('ReservationMade: Exception line: '
                . $e->getLine() . ', Message: ' . $e->getMessage()
            );
        }

        $job->delete();
    }
}
