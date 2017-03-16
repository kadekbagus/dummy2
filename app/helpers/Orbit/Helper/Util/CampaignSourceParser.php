<?php namespace Orbit\Helper\Util;
/**
 * Helper to parse campaign source from URL query string such as from
 * Google Analytics
 *
 * @author Rio Astamal <rio@dominopos.com>
 */
class CampaignSourceParser
{
    /**
     * Vendor
     *
     * @var array
     */
    protected $vendor = 'google_analytics';

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
     *   'campaign_source', // which site sent the traffic, and is a required parameter. Example: utm_source=Google
     *   'campaign_medium', // what type of link was used, such as cost per click or email. Example: utm_medium=cpc or banner
     *   'campaign_name',  // a specific product promotion or strategic campaign. Example: utm_campaign=spring_sale
     *   'campaign_term', // identifies search terms. Example: utm_term=running+shoes
     *   'campaign_content', // identifies what specifically was clicked to bring the user to the site, such as a banner ad or a text link. It is used for A/B testing and content-targeted ads. Examples: utm_content=logolink or utm_content=textlin
     * ]
     *
     * @var array
     */
    protected $result = [];

    public function __construct($vendor='google_analytics')
    {
        $this->result = [
            'campaign_source' => 'Other',
            'campaign_medium' => 'Other',
            'campaign_name' => 'Other',
            'campaign_term' => 'Other',
            'campaign_content' => 'Other'
        ];
    }

    /**
     * Static method to instantiate the class
     *
     * @author Rio Astamal <rio@dominopos.com>
     * @return CampaignSourceParser
     */
    public static function create($vendor='google_analytics')
    {
        return new static($vendor);
    }

    /**
     * Set the vendor
     *
     * @param string
     * @return CampaignSourceParser
     */
    public function setVendor($vendor)
    {
        $this->vendor = $vendor;

        return $this;
    }

    /**
     * Set the vendor
     *
     * @param array $urls
     * @return CampaignSourceParser
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
    public function getCampaignSource()
    {
        switch ($this->vendor) {
            default:

            case 'google_analytics':
                // Loop each of the URLs, the later will replace the previous one if
                // it is exists and the value is not empty
                foreach ($this->urls as $url) {
                    parse_str(parse_url($url, PHP_URL_QUERY), $params);

                    $this->result['campaign_source'] = isset($params['utm_source']) && !empty($params['utm_source'])
                        ? $params['utm_source'] : $this->result['campaign_source'];
                    $this->result['campaign_medium'] = isset($params['utm_medium']) && !empty($params['utm_medium'])
                        ? $params['utm_medium'] : $this->result['campaign_medium'];
                    $this->result['campaign_term'] = isset($params['utm_term']) && !empty($params['utm_term'])
                        ? $params['utm_term'] : $this->result['campaign_term'];
                    $this->result['campaign_content'] = isset($params['utm_content']) && !empty($params['utm_content'])
                        ? $params['utm_content'] : $this->result['campaign_content'];
                    $this->result['campaign_name'] = isset($params['utm_campaign']) && !empty($params['utm_campaign'])
                        ? $params['utm_campaign'] : $this->result['campaign_name'];

                    // exclusion for social sign in
                    $isFbSignIn = strpos($url, 'social-login-callback');
                    $isGoogleSignIn = strpos($url, 'social-google-callback');
                    if ($isFbSignIn !== false || $isGoogleSignIn !== false) {
                        $redirectToUrl = isset($params['redirect_to_url']) ? $params['redirect_to_url'] : '';

                        $frontendUrl = str_replace('#!/', '', urldecode($redirectToUrl));
                        $parsedUrl = parse_url($frontendUrl);
                        $frontendParams = [];
                        if (isset($parsedUrl['query'])) {
                            $frontendParams = $this->parseQueryString($parsedUrl['query']);
                        }
                        $frontendParams['campaign_source'] = isset($params['utm_source']) && !empty($params['utm_source'])
                        ? $params['utm_source'] : $frontendParams['campaign_source'];
                        $frontendParams['campaign_medium'] = isset($params['utm_medium']) && !empty($params['utm_medium'])
                            ? $params['utm_medium'] : $frontendParams['campaign_medium'];
                        $frontendParams['campaign_term'] = isset($params['utm_term']) && !empty($params['utm_term'])
                            ? $params['utm_term'] : $frontendParams['campaign_term'];
                        $frontendParams['campaign_content'] = isset($params['utm_content']) && !empty($params['utm_content'])
                            ? $params['utm_content'] : $frontendParams['campaign_content'];
                        $frontendParams['campaign_name'] = isset($params['utm_campaign']) && !empty($params['utm_campaign'])
                            ? $params['utm_campaign'] : $frontendParams['campaign_name'];
                    }
                }
                break;
        }

        return $this->result;
    }

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

    public function base64UrlDecode($inputStr)
    {
        return base64_decode(strtr($inputStr, '-_,', '+/='));
    }
}