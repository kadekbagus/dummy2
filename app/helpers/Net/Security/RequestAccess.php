<?php namespace Net\Security;
/**
 * A helper class for checking client ip address
 *
 * @author kadek <kadek@dominopos.com>
 * @author Rio Astamal <riO@dominopos.com>
 */
use Config;
use OrbitShop\API\v1\Helper\Command;
use Symfony\Component\HttpFoundation\IpUtils as IP;
use Request;

class RequestAccess
{
    protected $allowedIps = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        // do nothing
    }

    /**
     * Static method for instantiate the class.
     *
     */
    public static function create()
    {
        return new static();
    }

    /**
     * @param string $ip
     * @return boolean
     */
    public function checkIpAddress($ip)
    {
        $allowed_ips = empty($this->allowedIps) ? Config::get('orbit.security.allowed_ips') : $this->allowedIps;

        if (! $allowed_ips) {
            // We have not found the config, so grant the access
            return true;
        }

        if (is_string($allowed_ips) && $allowed_ips === '*') {
            return true;
        }

        return IP::checkIP($ip, $allowed_ips);
    }

    public function setAllowedIps($ips)
    {
        $this->allowedIps = $ips;

        return $this;
    }
}
