<?php
namespace Orbit;

use DominoPOS\OrbitSession\Session;
use \Facebook\PersistentData\PersistentDataInterface;

/**
 * Wraps OrbitSession in a Facebook SDK-friendly interface.
 *
 * @package Orbit
 */
class FacebookSessionAdapter implements PersistentDataInterface
{
    /** @var Session  */
    private $session;

    public function __construct(Session $s)
    {
        $this->session = $s;
    }

    /**
     * Get a value from a persistent data store.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function get($key)
    {
        return $this->session->read($this->wrapKey($key));
    }

    /**
     * Set a value in the persistent data store.
     *
     * @param string $key
     * @param mixed $value
     */
    public function set($key, $value)
    {
        $this->session->write($this->wrapKey($key), $value);
    }

    /**
     * @param $key
     * @return string
     */
    protected function wrapKey($key)
    {
        return 'facebooksdk.' . $key;
    }
}