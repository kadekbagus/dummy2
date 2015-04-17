<?php namespace Net;
/**
 * A helper which dealing mac address related string check.
 *
 * @author Rio Astamal <me@rioastamal.net>
 * @credit http://www.stetsenko.net/2011/01/php-mac-address-validating-and-formatting/
 */
class MacAddr
{
    protected $mac = NULL;

    /**
     * Constructor
     *
     * @param string $mac - The mac address
     */
    public function __construct($mac)
    {
        $this->mac = $mac;
    }

    /**
     * Static method for instantiate the class.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string $mac - The mac address
     * @return MacAddr
     */
    public static function create($mac)
    {
        return new static();
    }

    /**
     * Check for mac validity format.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return boolean
     */
    public function isValid()
    {
        return (preg_match('/([a-fA-F0-9]{2}[:|\-]?){6}/', $this->mac) === 1);
    }

    /**
     * Reformat mac address to lowercase and separate it with ':'
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string $separator
     * @return string
     */
    public function reformat($separator=':')
    {
        // Remove the mac separator
        $newMac = str_replace([':', '-'], '', $this->mac);

        // Join in again
        $newMac = str_split($this->mac, 2);
        $newMac = implode($separator, $newMac);

        return $this;
    }

    /**
     * Get the mac value.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return string
     */
    public function getMac()
    {
        return $this->mac;
    }
}
