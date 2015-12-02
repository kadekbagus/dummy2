<?php
namespace Orbit;

/**
 * Class CloudMAC
 *
 * @package Orbit
 *
 * @todo Improve someday, use config for keys, etc.
 */
class CloudMAC
{
    private static function computeMac($values, $timestamp, $key)
    {
        $h = hash_init('sha256', HASH_HMAC, $key);
        ksort($values);
        foreach ($values as $k => $v) {
            hash_update($h, $k);
            hash_update($h, "\x00");
            hash_update($h, $v);
            hash_update($h, "\x00");
        }
        hash_update($h, 'time');
        hash_update($h, "\x00");
        hash_update($h, $timestamp);
        hash_update($h, "\x00");
        return hash_final($h);
    }

    /**
     * @param array $values
     * @param int $timestamp
     * @return string MAC
     */
    public static function computeCloudMac($values, $timestamp)
    {
        return static::computeMac($values, $timestamp, 'Yes, hello, this is cloud1234567');
    }

    /**
     * @param array $values
     * @return array
     */
    public static function wrapDataFromCloud($values)
    {
        $timestamp = time();
        $mac = static::computeCloudMac($values, $timestamp);
        $values['timestamp'] = $timestamp;
        $values['mac'] = $mac;
        return $values;
    }

    /**
     * @param string $sent_mac
     * @param int $timestamp
     * @param array $values
     * @return bool valid or not
     */
    public static function validateDataFromCloud($sent_mac, $timestamp, $values)
    {
        return $sent_mac === static::computeCloudMac($values, $timestamp);
    }

    /**
     * @param array $values k=>v
     * @param int $timestamp
     * @return string MAC
     */
    public static function computeBoxMac($values, $timestamp)
    {
        return static::computeMac($values, $timestamp, 'Yes, hello, this is box123456789');
    }

    /**
     * @param array $values
     * @return array
     */
    public static function wrapDataFromBox($values)
    {
        $timestamp = time();
        $mac = static::computeBoxMac($values, $timestamp);
        $values['timestamp'] = $timestamp;
        $values['mac'] = $mac;
        return $values;
    }

    /**
     * @param string $sent_mac
     * @param int $timestamp
     * @param array $values
     * @return bool
     */
    public static function validateDataFromBox($sent_mac, $timestamp, $values)
    {
        return $sent_mac === static::computeBoxMac($values, $timestamp);
    }

}
