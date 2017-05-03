<?php namespace Orbit\Helper\Util;
/**
 * Helper to parse filter parameter value from url
 *
 * @author Ahmad <ahmad@dominopos.com>
 */

class FilterParser
{
    /**
     * The URL that need to parse
     *
     * @var array
     */
    protected $urls = [];

    /**
     * Variable to hold the result of the parsed.
     *
     * Values in array and having index [
     *   'filter_country',
     *   'filter_cities',
     *   'filter_keywords',
     *   'filter_categories',
     *   'filter_partner'
     * ]
     *
     * @var array
     */
    protected $result = [];

    public function __construct()
    {
        $this->result = [
            'filter_country' => NULL,
            'filter_cities' => NULL,
            'filter_keywords' => NULL,
            'filter_categories' => NULL,
            'filter_partner' => NULL
        ];
    }

    /**
     * Static method to instantiate the class
     *
     * @author Ahmad <ahmad@dominopos.com>
     * @return FilterParser
     */
    public static function create()
    {
        return new static();
    }

    /**
     * Set the urls
     *
     * @param array $urls
     * @return FilterParser
     */
    public function setUrls(array $urls)
    {
        $this->urls = $urls;

        return $this;
    }

    /**
     * Get the campaign source.
     *
     * @return array
     */
    public function getFilters()
    {
        // Loop each of the URLs, the later will replace the previous one if
        // it is exists and the value is not empty
        foreach ($this->urls as $url) {
            parse_str(parse_url($url, PHP_URL_QUERY), $params);

            $this->result['filter_country'] = isset($params['country']) && !empty($params['country'])
                ? $params['country'] : $this->result['filter_country'];
            $this->result['filter_cities'] = isset($params['cities']) && !empty($params['cities'])
                ? $params['cities'] : $this->result['filter_cities'];
            $this->result['filter_keywords'] = isset($params['keyword']) && !empty($params['keyword'])
                ? $params['keyword'] : $this->result['filter_keywords'];
            $this->result['filter_categories'] = isset($params['category_id']) && !empty($params['category_id'])
                ? $params['category_id'] : $this->result['filter_categories'];
            $this->result['filter_partner'] = isset($params['partner_id']) && !empty($params['partner_id'])
                ? $params['partner_id'] : $this->result['filter_partner'];

            if (is_array($this->result['filter_cities'])) {
                $this->result['filter_cities'] = implode(', ', $this->result['filter_cities']);
            }
            if (is_array($this->result['filter_categories'])) {
                $this->result['filter_categories'] = implode(', ', $this->result['filter_categories']);
            }
        }
        return $this->result;
    }
}
