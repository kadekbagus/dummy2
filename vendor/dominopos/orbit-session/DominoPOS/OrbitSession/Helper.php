<?php namespace DominoPOS\OrbitSession;
/**
 * Generic Session Helper
 *
 * @author Yudi Rahono <yudi.rahono@dominopos.com>
 */

class Helper
{
    public static function array_get($array, $key, $default = null)
    {
        if (is_null($key)) return $array;

        if (isset($array[$key])) return $array[$key];

        foreach (explode('.', $key) as $segment)
        {
            if ( ! is_array($array) || ! array_key_exists($segment, $array))
            {
                return value($default);
            }

            $array = $array[$segment];
        }

        return $array;
    }

    public static function array_remove($array, $key)
    {
        if (is_null($key)) return $array;

        if (! array_key_exists($array, $key)) return $array;

        unset($array[$key]);

        return $array;
    }

    /**
     * Touch the session data to change the expiration time and last activity.
     *
     * @author Yudi Rahono <yudi.rahono@dominopos.com>
     * @param \DominoPOS\OrbitSession\SessionData &$sessionData
     * @param int $expire
     */
    public static function touch(&$sessionData, $expire)
    {
        // Refresh the session
        if ($expire !== 0) {
            $sessionData->expireAt = time() + $expire;
        }

        // Last activity
        $sessionData->lastActivityAt = time();
    }
}
