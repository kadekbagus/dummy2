<?php namespace Orbit\Helper\Net\HttpFetcher;
/**
 * Simple Http Fetcher interface.
 *
 * @author Rio Astamal <rio@dominopos.com>
 */
interface FetcherInterface
{
    public function getUrl($url, array $params=[]);
    public function setHeader($header, $value);
    public function setOption($option, $value);
}