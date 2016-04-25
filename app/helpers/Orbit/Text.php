<?php namespace Orbit;

use Str;
use Carbon\Carbon as Carbon;

class Text
{

    /**
     * Extracted Global date format
     * @param string $dateString
     * @param string $format
     * @return bool|string
     */
    public static function formatDateTime($dateString, $format = 'd F Y H:i:s')
    {
        // Consider Null
        if (! $dateString) {
            return '-';
        }

        $time = strtotime($dateString);

        // Consider Unknown date or null
        if ($time < 1)
        {
            return '-';
        }
        return date($format, $time);
    }

    /**
     * @param string $dateString
     * @param string $format
     * @return bool|string
     */
    public static function formatTime($dateString, $format = 'H:i:s')
    {
        return static::formatDateTime($dateString, $format);
    }

    /**
     * @param string $dateString
     * @param string $format
     * @return bool|string
     */
    public static function formatDate($dateString, $format = 'd F Y')
    {
        return static::formatDateTime($dateString, $format);
    }
    /**
     * @param string|int $number
     * @param int $precision
     * @return string
     */
    public static function formatNumber($number, $precision = 2)
    {
        return number_format($number, $precision);
    }

    public static function formatNumberWithoutPrecision($number)
    {
        return static::formatNumber($number, 0);
    }

    public static function exportFilename($pageTitle, $ext = ".csv", $timezone = "UTC")
    {
        return 'gotomalls-export-' . Str::slug($pageTitle) . '-' . Carbon::now($timezone)->format('D_d_M_Y_Hi') . $ext;
    }
}
