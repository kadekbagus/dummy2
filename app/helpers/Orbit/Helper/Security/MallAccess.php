<?php namespace Orbit\Helper\Security;
/**
 * A helper class for checking client ip address
 *
 * @author kadek <kadek@dominopos.com>
 */
use Config;

class MallAccess
{
    /**
     * Constructor
     */
    public function __construct()
    {
        // Do nothing
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
     * Method to check whether this mall are accessible.
     *
     * @param Mall $mall Instance of Mall object
     * @return boolean
     */
    public function isAccessible($mall)
    {
        $isDemo = Config::get('orbit.is_demo', FALSE);

        if ($isDemo) {
            // No need to check
            return TRUE;
        }

        // if the mall is not active then it should not accessible
        return $mall->status === 'active';
    }
}