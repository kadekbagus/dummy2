<?php

namespace Orbit\Notifications\Reservation;

use Exception;
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

    protected $logID = 'ReservationNotification';

    protected $signature = 'reservation-notification';

    protected $shouldQueue = true;

    public function __construct($reservation = null)
    {
        $this->reservation = $reservation;
    }

    protected function notificationMethods()
    {
        return ['email'];
    }

    abstract public function getEmailTemplates();

    abstract public function getEmailSubject();

    public function getRecipientEmail()
    {
        return [
            [
                'name' => $this->reservation->users->getFullName(),
                'email' => $this->reservation->users->user_email,
            ],
        ];
    }

    protected function getCustomerData()
    {
        return [
            'customerEmail' => $this->reservation->users->user_email,
            'customerName'  => $this->reservation->users->getFullName(),
            'customerId'    => $this->reservation->users->user_id,
        ];
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

            $data = array_merge(
                [
                    'langs' => $this->getSupportedLanguages(),
                    'cs' => $this->getContactData(),
                    'emailSubject' => $this->getEmailSubject(),
                    'statusColor' => [
                        'pending' => 'orange',
                        'accepted' => 'green',
                        'cancelled' => 'red',
                        'expired' => 'gray',
                        'declined' => 'red',
                        'done' => 'green',
                    ],
                ],
                $data,
                $this->getCustomerData(),
                $this->getReservationData()
            );

            $emailConfig = Config::get('orbit.registration.mobile.sender');

            foreach($this->getRecipientEmail() as $recipient) {
                $data['recipientEmail'] = $recipient['email'];
                $data['recipientName'] = $recipient['name'];

                Mail::send(
                    $this->getEmailTemplates(),
                    $data,
                    function($mail) use ($data, $emailConfig) {
                        $mail->from($emailConfig['email'], $emailConfig['name']);
                        $mail->to($data['recipientEmail']);
                        $mail->subject($data['emailSubject']);
                    }
                );
            }

        } catch (Exception $e) {
            $this->log(sprintf(
                'ReservationMade: Exception line: %s(%s) : %s',
                $e->getFile(),
                $e->getLine(),
                $e->getMessage()
            ));
        }

        $job->delete();
    }
}
