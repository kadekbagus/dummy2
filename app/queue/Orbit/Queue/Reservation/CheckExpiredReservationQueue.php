<?php

namespace Orbit\Queue\Reservation;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use Orbit\Controller\API\v1\Reservation\ReservationInterface;

/**
 * Queue which check whether reservation should be expired or not
 * on a specific time. If so, then mark as expired.
 *
 * @author Budi <budi@dominopos.com>
 */
class CheckExpiredReservationQueue
{
    protected $signature = 'CheckExpiredReservationQueue';

    /**
     * Handle the job.
     */
    public function fire($job, $data)
    {
        $reservationId = $data['reservationId'];

        $reservation = App::make(ReservationInterface::class, [$data['type']]);

        $reservationObject = $reservation->get($reservationId);

        if (empty($reservationObject)) {
            $this->log("Reservation {$reservationId} not found.");
            $job->delete();
            return;
        }

        try {
            DB::beginTransaction();

            if ($reservation->accepted($reservationObject)) {
                $reservation->expire($reservationObject);

                Event::fire('orbit.reservation.expired', [$reservationObject]);

                $this->log("Reservation {$reservationId} expired.");
            }
            else {
                $this->log("Reservation {$reservationId} is good. Nothing to do.");
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            $this->log("Exception: {$e->getMessage()}");
        }

        $job->delete();
    }

    private function log($message, $type = 'info')
    {
        Log::{$type}($this->signature . ': '  . $message);
    }
}
