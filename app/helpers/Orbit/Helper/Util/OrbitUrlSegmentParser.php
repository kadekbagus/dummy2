<?php namespace Orbit\Helper\Util;
/**
 * Class for parsing URL into segment and query string
 *
 * @author Rio Astamal <rio@dominopos.com>
 * @samples
 * <code>
 * $urls = [
 *     '/coupons/L9zC-dXoxJtNwuS5/haldirams-peanuts-buy-2-get-1-free?sortby=created_date&sortmode=desc&order=latest'
 * ];
 *
 * foreach ($urls as $url)
 * {
 *     $parser = OrbitUrlSegmentParser::create($url);
 *     // print_r($parser);
 *     printf("Segment 0: %s | 1: %s 2: %s\n", $parser->getSegmentAt(0), $parser->getSegmentAt(1), $parser->getSegmentAt(2));
 *     printf("sortby: %s | sortmode: %s | order: %s\n", $parser->getQueryStringValueFor('sortby'), $parser->getQueryStringValueFor('sortmode'), $parser->getQueryStringValueFor('order'));
 * }
 * </code>
 */
class OrbitUrlSegmentParser
{
    protected $segment = [];
    protected $queryString = [];
    protected $url = null;

    /**
     * @param string $url
     * @return void
     */
    public function __construct($url=NULL)
    {
        if (! empty($url)) {
            $this->parse($url);
        }
    }

    /**
     * @param string $url
     * @return OrbitUrlSegmentParser
     */
    public static function create($url)
    {
        return new static($url);
    }

    /**
     * @param string $url
     * @return OrbitUrlSegmentParser
     */
    public function parse($url)
    {
        $this->url = $url;

        $parsedUrl = parse_url($url);

        if (isset($parsedUrl['path'])) {
            $this->segment = explode('/', $parsedUrl['path']);

            // Remove empty element
            $this->segment = array_filter($this->segment, function($value) {
                return $value !== '';
            });

            $this->segments = array_values($this->segments);
        }

        if (isset($parsedUrl['query'])) {
            $this->queryString = $this->parseQueryString($parsedUrl['query']);
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @return array
     */
    public function getSegments()
    {
        return $this->segment;
    }

    /**
     * @return array
     */
    public function getQueryString()
    {
        return $this->queryString;
    }

    /**
     * @param int $segment Position of segment
     * @return string|NULL
     */
    public function getSegmentAt($segment)
    {
        if (isset($this->segments[$segment])) {
            return $this->segments[$segment];
        }

        return NULL;
    }

    /**
     * @param string
     * @return string|NULL
     */
    public function getQueryStringValueFor($parameter)
    {
        if (isset($this->queryString[$parameter])) {
            return $this->queryString[$parameter];
        }

        return NULL;
    }

    /**
     * This is to mitigate limitation of parse_str which does not transform
     * same argument into array. 'foo=bar1&foo=bar2'.
     *
     * @credit http://php.net/manual/en/function.parse-str.php#76792
     * @param string $qs Query string in string
     * @return array
     */
    protected function parseQueryString($qs)
    {
        // result array
        $arr = array();

        #//split on outer delimiter
        $pairs = explode('&', $qs);

        // loop through each pair
        foreach ($pairs as $i) {
                // split into name and value
                list($name,$value) = explode('=', $i, 2);

                // if name already exists
                if( isset($arr[$name]) ) {
                        // stick multiple values into an array
                        if( is_array($arr[$name]) ) {
                                $arr[$name][] = $value;
                        }
                        else {
                                $arr[$name] = array($arr[$name], $value);
                        }
                }
                // otherwise, simply stick it in a scalar
                else {
                        $arr[$name] = $value;
                }
        }

        // return result array
        return $arr;
    }
}