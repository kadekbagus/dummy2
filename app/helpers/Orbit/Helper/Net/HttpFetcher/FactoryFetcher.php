<?php namespace Orbit\Helper\Net\HttpFetcher;
/**
 * Factory class to create HttpFetcher object
 *
 * @author Rio Astamal <rio@dominopos.com>
 */
class FactoryFetcher
{
    /**
     * Object of the HtppFetcher
     *
     * @var HttpFetcher
     */
    public static $fetcherInstance = NULL;

    /**
     * @param string $fetcherName 'curl' or 'fake'
     */
    public function __construct($fetcherName)
    {
        switch ($fetcherName) {
            case 'curl':
                static::$fetcherInstance = new CurlFetcher();
                break;

            default:
            case 'fake':
                static::$fetcherInstance = new FakeFetcher();
                break;

        }
    }

    /**
     * @param $fetcherName
     * @return HttpFetcher
     */
    public static function create($fetcherName)
    {
        return new static($fetcherName);
    }

    /**
     * @return HttpFetcherInterface implementation
     */
    public function getInstance()
    {
        return static::$fetcherInstance;
    }
}