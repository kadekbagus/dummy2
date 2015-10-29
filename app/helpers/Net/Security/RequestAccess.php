<?php namespace Net\Security;
/**
 * A helper class for checking client ip address
 *
 * @author kadek <kadek@dominopos.com>
 */
use Config;
use OrbitShop\API\v1\Helper\Command;
use Symfony\Component\HttpFoundation\IpUtils as IP;
use Request;

class RequestAccess
{
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

    public function checkIpAddress($ip)
    {
        $allowed_ips = Config::get('orbit.security.allowed_ips');

        if (is_string($allowed_ips) && $allowed_ips === '*') {
            return true;
        }

        return IP::checkIP($ip, $allowed_ips);
    }
}
