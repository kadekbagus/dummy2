<?php namespace Orbit\Helper\Util;
/**
 * If the Job driver support bury() command then try to run it, otherwise
 * use the provided callback.
 *
 * @author Shelgi Prasetyo <shelgi@dominopos.com>
 */

use Net\Util\MobileDetect;

class UserAgent extends MobileDetect
{
    protected $rules = array();

    protected static $additionalOperatingSystems = array(
        'Windows'           => 'Windows',
        'Windows NT'        => 'Windows NT',
        'OS X'              => 'Mac OS X',
        'Debian'            => 'Debian',
        'Ubuntu'            => 'Ubuntu',
        'Macintosh'         => 'PPC',
        'OpenBSD'           => 'OpenBSD',
        'Linux'             => 'Linux',
        'ChromeOS'          => 'CrOS',
    );

    /**
     * Match a detection rule and return the matched key.
     *
     * @param  array  $rules
     * @param  null   $userAgent
     * @return string
     */
    protected function findDetectionRulesAgainstUA(array $rules, $userAgent = null)
    {
        // Loop given rules
        foreach ($rules as $key => $regex)
        {
            if (empty($regex)) continue;
            // Check match
            if ($this->match($regex, $userAgent)) return $key ?: reset($this->matchesArray);
        }
        return false;
    }

    /**
     * Get the device name.
     *
     * @param  string $userAgent
     * @return string
     */
    public function deviceType($userAgent = null)
    {
        $type = 'Other';
        if ($this->isMobile()) {
            $type = 'Mobile';
        } elseif ($this->isTablet()) {
            $type = 'Tablet';
        }

        return $type;
    }

    /**
     * Get the device name.
     *
     * @param  string $userAgent
     * @return string
     */
    public function deviceModel($userAgent = null)
    {
        // Get device rules
        $rules = array_merge(
            self::$phoneDevices,
            self::$tabletDevices,
            self::$utilities
        );

        return $this->findDetectionRulesAgainstUA($rules, $userAgent);
    }

    /**
     * Get the platform name.
     *
     * @param  string $userAgent
     * @return string
     */
    public function platform($userAgent = null)
    {
        // Get platform rules
        $rules = array_merge(
            self::$operatingSystems,
            self::$additionalOperatingSystems
        );
        return $this->findDetectionRulesAgainstUA($rules, $userAgent);
    }

    /**
     * Get the browser name.
     *
     * @return string
     */
    public function browser($userAgent = null)
    {
        // Get browser rules
        $rules = self::$browsers;
        return $this->findDetectionRulesAgainstUA($rules, $userAgent);
    }
}