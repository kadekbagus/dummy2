<?php namespace Orbit\Mailchimp;
/**
 * Factory class to create an Mailchimp instance
 * based on specified driver.
 *
 * @author Rio Astamal <rio@dominopos.com>
 */
use Orbit\Mailchimp\MailchimpFaker;
use Orbit\Mailchimp\Mailchimp;

class MailchimpFactory
{
    /**
     * Name of the mailchimp driver. Could be
     * 'mailchimp-faker' or 'mailchimp'
     *
     * @var string
     */
    protected $driver = 'mailchimp-faker';

    /**
     * Config for the mailchimp instance
     *
     * @var array
     */
    protected $config = [
        'api_key' => NULL,
        'api_url' => NULL
    ];

    /**
     * @param string $driver Name of the driver
     * @return void
     */
    public function __construct(array $config, $driver='mailchimp-faker')
    {
        $this->config = $config;
        $this->driver = $driver;
    }

    /**
     * Set the driver name
     *
     * @param String
     * @return MailchimpFactory
     */
    public function setDriver($driver)
    {
        $this->driver = $driver;

        return $this;
    }

    /**
     * @return string
     */
    public function getDriver()
    {
        return $this->driver;
    }

    /**
     * Set the config for the mailchimp
     *
     * @param String
     * @return MailchimpFactory
     */
    public function setConfig(array $config)
    {
        $this->config = $config;

        return $this;
    }

    /**
     * Get the config for mailchimp
     *
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @param string $driver Name of the driver
     * @return MailchimpInterface
     */
    public static function create($driver='mailchimp-faker')
    {
        return new static($driver);
    }

    /**
     * Get instance of the Mailchimp object based on specified
     * driver
     *
     * @return MailchimpInterface
     */
    public function getInstance()
    {
        $mailchimp = NULL;

        switch ($this->driver) {
            case 'mailchimp':
                $mailchimp = new Mailchimp($this->config);
                break;

            case 'mailchimp-faker':
            default:
                $mailchimp = new MailchimpFaker($this->config);
                break;
        }

        return $mailchimp;
    }
}