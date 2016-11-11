<?php namespace Orbit\Helper\Net\HttpFetcher;
/**
 * Http fetcher used for Dummy response (unit test)
 *
 * @author Rio Astamal <rio@dominopos.com>
 */
class FakeFetcher implements FetcherInterface
{
    protected $response = NULL;

    /**
     * Setting the fake response.
     *
     * @params string $response
     * @return HttpFetcherFake
     */
    public function setResponse($response)
    {
        $this->response = $response;

        return $this;
    }

    /**
     * @param string $url
     * @param array $params optional
     * @return string|mixed
     */
    public function getUrl($url, $params=[])
    {
        return $this->response;
    }

    /**
     * Set header for the request.
     *
     * @param string $name
     * @param string $value
     * @return FakeFetcher
     */
    public function setHeader($name, $value)
    {
        // do nothing

        return $this;
    }

    /**
     * Set option for the request object
     *
     * @param string $option
     * @param string $value
     * @return FakeFetcher
     */
    public function setOption($option, $value)
    {
        // do nothing

        return $this;
    }
}