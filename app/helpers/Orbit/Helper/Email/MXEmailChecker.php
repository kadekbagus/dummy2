<?php namespace Orbit\Helper\Email;
/**
 * Check email validity based on MX Record return value.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
class MXEmailChecker
{
    /**
     * Email address to check
     *
     * @var string
     */
    protected $email = NULL;

    /**
     * MX Records
     *
     * @var array
     */
    protected $mxRecords = [];

    /**
     * Constructor
     */
    public function __construct($email)
    {
        $this->email = $email;
    }

    /**
     * Static constructor.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return MXEmailChecker
     */
    public static function create($email)
    {
        return new static($email);
    }

    /**
     * MX Host checker.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string $hostname
     * @return array of host
     */
    public static function getMX($hostname)
    {
        getmxrr($hostname, $mx);

        return $mx;
    }

    /**
     * Check the MX record of the email address
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return MXEmailChecker
     */
    public function check()
    {
        $emails = explode('@', $this->email, 2);

        if (count($emails) < 2) {
            return $this;
        }

        $hosts = static::getMX($emails[1]);

        if (empty($hosts)) {
            return $this;
        }

        $this->mxRecords = $hosts;

        return $this;
    }

    /**
     * Get the mx records value.
     *
     * @return boolean|array
     */
    public function getMXRecords()
    {
        return $this->mxRecords;
    }

    /**
     * Get the email address.
     *
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }
}