<?php

use Carbon\Carbon;

class OrbitCarbon extends Carbon
{
    /**
     * There should be a Carbon method for this.
     *
     * @param string $timezone The timezone name, e.g. 'Asia/Jakarta'.
     * @return string The hours diff, e.g. '+07:00'.
     * @author Qosdil A. <qosdil@gmail.com>
     */
    public static function getTimezoneHoursDiff($timezone)
    {
        $mallDateTime = Carbon::createFromFormat('Y-m-d H:i:s', '2016-01-01 00:00:00', $timezone);
        $utcDateTime = Carbon::createFromFormat('Y-m-d H:i:s', '2016-01-01 00:00:00');
        $diff = $mallDateTime->diff($utcDateTime);
        $sign = ($diff->invert) ? '-' : '+';
        $hour = ($diff->h < 10) ? '0'.$diff->h : $diff->h;
        return $sign.$hour.':00';
    }
}
