<?php namespace Net;
/**
 * A helper which dealing mac address related string check.
 *
 * @author Rio Astamal <me@rioastamal.net>
 * @credit http://www.stetsenko.net/2011/01/php-mac-address-validating-and-formatting/
 */
use OrbitShop\API\v1\Helper\Command;

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
     * Method to get Mac based on IP address. It uses the linux utilities
     * ip-utils.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string $ip IP address
     * @return string
     */
    public function getMacFromIP($ip)
    {
        // Ping first to make sure we got arp table list,
        // we don't care about the ping result
        $pingCmdObject = Command::Factory('ping -w 1 -s 32 -c 1 ' . $ip)->run();

        // Get the Mac Address
        $arpCmd = sprintf("ip neigh show | grep %s | awk '{print \$5}'", $ip);
        $arpCmdObject = Command::Factory($arpCmd, TRUE)->run();

        if ($arpCmdObject->getExitCode() !== 0) {
            return NULL;
        }

        return trim($arpCmdObject->getStdOut());
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
        // Remove non hex chars
        $nonHexRemoved = preg_replace('/[^[:xdigit:]]/', '', $this->mac);

        // Join in again
        $this->mac = strtolower($this->mac);
        $this->mac = str_split($this->mac, 2);
        $this->mac = implode($separator, $newMac);

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

    /**
     * Convert the mac to string
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return string
     */
    public function __toString()
    {
        return (string)$this->mac;
    }
}
