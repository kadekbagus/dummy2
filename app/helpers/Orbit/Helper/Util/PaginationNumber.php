<?php namespace Orbit\Helper\Util;
/**
 * Helper to get config for default per page listing and max record returned
 * for a set list of response.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use Config;
use OrbitShop\API\v1\Helper\Input as OrbitInput;

class PaginationNumber
{
    /**
     * The item name of the config.
     *
     * @var string
     */
    protected $name = '';

    /**
     * The default per page in pagination list.
     *
     * @var int
     */
    protected $perPage = 20;

    /**
     * The default max record in a list.
     *
     * @var int
     */
    protected $maxRecord = 50;

    /**
     * Default config name for per_page pagination fallback.
     *
     * @var string
     */
    protected $defaultPerPageConfig = 'orbit.pagination.per_page';

    /**
     * Default config name for max_record pagination fallback.
     *
     * @var string
     */
    protected $defaultMaxRecordConfig = 'orbit.pagination.max_record';

    /**
     * List of config for pagination or max record.
     *
     * @var array
     */
    protected $configList = [
        'per_page'      => 'orbit.pagination.%s.per_page',
        'max_record'    => 'orbit.pagination.%s.max_record'
    ];

    /**
     * Constructor
     *
     * @param string $name The config
     * @param array $config (optional)
     * @return void
     */
    public function __construct($name, array $config=array())
    {
        $this->name = $name;
        $this->configList = $config + $this->configList;
    }

    /**
     * Static method to instantiate the class.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return Setting
     */
    public static function create($name, array $config=array())
    {
        return new static($name, $config);
    }

    /**
     * Get The value of default per page pagination setting for particular
     * list.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param int $number
     * @return Pagination
     */
    public function setPerPage($number = NULL)
    {
        $perPage = $number;

        if (is_null($number)) {
            $defaultConfig = sprintf($this->configList['per_page'], $this->name);

            // Get default per page (take)
            $perPage = (int)Config::get($defaultConfig);
        }

        if ($perPage <= 0) {
            $perPage = (int)Config::get($this->defaultPerPageConfig);
            if ($perPage <= 0) {
                // Second fallback
                // Default would be taken from the object attribute $perPage
                return $this;
            }
        }

        $maxRecord = $this->setMaxRecord(NULL)->maxRecord;
        if ($perPage > $maxRecord) {
            $perPage = $maxRecord;
        }

        $this->perPage = $perPage;

        return $this;
    }

    /**
     * Get The value of default max record pagination setting for particular
     * list.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param int $number
     * @return Pagination
     */
    public function setMaxRecord($number = NULL)
    {
        $maxRecord = $number;
        if (is_null($number)) {
            $defaultConfig = sprintf($this->configList['max_record'], $this->name);

            // Get default per page (take)
            $maxRecord = (int)Config::get($defaultConfig);
        }

        if ($maxRecord <= 0) {
            $maxRecord = (int)Config::get($this->defaultMaxRecordConfig);
            if ($maxRecord <= 0) {
                // Second fallback
                // Default would be taken from the object attribute $perPage
                return $this;
            }
        }

        $this->maxRecord = $maxRecord;

        return $this;
    }

    /**
     * Method to set the default config name of per_page fallback.
     *
     * @author Rio Astamal <rio@dominopos.com>
     * @param string $name Name of the config
     * @return Pagination
     */
    public function setDefaultPerPageConfig($name)
    {
        $this->defaultPerPageConfig = $name;

        return $this;
    }

    /**
     * Method to set the default config name of max record fallback.
     *
     * @author Rio Astamal <rio@dominopos.com>
     * @param string $name Name of the config
     * @return Pagination
     */
    public function setDefaultMaxRecordConfig($name)
    {
        $this->defaultMaxRecordConfig = $name;

        return $this;
    }

    /**
     * Simplify the input to get 'take' parameter.
     *
     * @author Rio Astamal <rio@dominopos.com>
     * @param string $name The config name
     * @param string $takeName The name of the take argument
     * @return int
     */
    public static function parseTakeFromGet($name, $takeName='take')
    {
        $pg = new static($name);
        $take = $pg->perPage;
        OrbitInput::get($takeName, function ($_take) use (&$take, $pg) {
            $take = $pg->setPerPage($_take)->perPage;
        });

        return $take;
    }

    /**
     * Simplify the input to get 'skip' parameter.
     *
     * @author Rio Astamal <rio@dominopos.com>
     * @param string $name The config name
     * @param strint $skipName The name of the skip argument
     * @return int
     */
    public static function parseSkipFromGet($name, $skipName='skip')
    {
        $skip = 0;
        OrbitInput::get($skipName, function($_skip) use (&$skip)
        {
            if ($_skip < 0) {
                $_skip = 0;
            }

            $skip = $_skip;
        });

        return $skip;
    }

    /**
     * Magic method to get the property value.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return mixed
     */
    public function __get($property)
    {
        if (property_exists($this, $property)) {
            return $this->$property;
        }
    }
}
