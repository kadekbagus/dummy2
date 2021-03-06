<?php namespace Orbit\Helper\Net\HttpFetcher;
/**
 * Http fetcher based on Curl implementation.
 *
 * @author Rio Astamal <rio@dominopos.com>
 */
use CurlWrapper;

class CurlFetcher implements FetcherInterface
{
    protected $fetcher = NULL;

    /**
     * @return void
     */
    public function __construct()
    {
        $this->fetcher = new CurlWrapper();
        // Disable certificate check
        $this->setOption(CURLOPT_SSL_VERIFYHOST, 0);
        $this->setOption(CURLOPT_SSL_VERIFYPEER, 0);
    }

    /**
     * @param string $url
     * @param array $params optional
     * @return string|mixed
     */
    public function getUrl($url, array $params=[])
    {
        return $this->fetcher->get($url, $params);
    }

    /**
     * Set header for the request.
     *
     * @param string $name
     * @param string $value
     * @return CurlFetcher
     */
    public function setHeader($name, $value)
    {
        $this->fetcher->addHeader($name, $value);

        return $this;
    }

    /**
     * Set option for the curl object.
     *
     * @param string $name
     * @param string $value
     * @return CurlFetcher
     */
    public function setOption($option, $value)
    {
        $this->fetcher->addOption($option, $value);

        return $this;
    }
}