<?php namespace Orbit\Helper\Net;

class Domain
{
    /**
     * @param string $url URL
     * @return string root domain
     */
    public static function getRootDomain($url)
    {
        $pieces = parse_url($url);
        $domain = isset($pieces['host']) ? $pieces['host'] : '';

        if (preg_match('/(?P<domain>[a-z0-9][a-z0-9\-]{1,63}\.[a-z\.]{2,6})$/i', $domain, $regs)) {
            return trim($regs['domain']);
        }

        return false;
    }
}