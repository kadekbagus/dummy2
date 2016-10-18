<?php namespace Orbit\Helper\Net;
/**
 * Simple helper to interact with bitly v3 API
 *
 * based on: https://github.com/Falicon/BitlyPHP/blob/master/bitly.php
 * @author Ahmad <ahmad@dominopos.com>
 */

class BitlyShortener {
	/**
     * The URI of the standard bitly v3 API.
     */
    protected $bitly_api_url = 'http://api.bit.ly/v3/';
    /**
     * The URI of the bitly OAuth endpoints.
     */
    protected $bitly_oauth_api_url = 'https://api-ssl.bit.ly/v3/';
    /**
     * The URI for OAuth access token requests.
     */
    protected $bitly_oauth_access_token_url = 'https://api-ssl.bit.ly/oauth/';
    /**
     * The get parameter
     */
    protected $getParameter = array();

    /**
     * @param array $config
     * @return void
     */
    public function __construct($parameters = [])
    {
    	$this->getParameter = $parameters;
    }

    /**
     * @param array $parameters
     * @return GTMSearchRecorder
     */
    public static function create($parameters=[])
    {
        return new static($parameters);
    }

    /**
     * Returns an OAuth access token as well as API users for a given code.
     *
     * @param $code
     *   The OAuth verification code acquired via OAuth’s web authentication
     *   protocol.
     * @param $redirect
     *   The page to which a user was redirected upon successfully authenticating.
     * @param $client_id
     *   The client_id assigned to your OAuth app. (http://bit.ly/a/account)
     * @param $client_secret
     *   The client_secret assigned to your OAuth app. (http://bit.ly/a/account)
     *
     * @return
     *   An associative array containing:
     *   - login: The corresponding bit.ly users username.
     *   - api_key: The corresponding bit.ly users API key.
     *   - access_token: The OAuth access token for specified user.
     *
     * @see http://code.google.com/p/bitly-api/wiki/ApiDocumentation#/oauth/access_token
     */
    public function bitly_oauth_access_token_call($code, $redirect, $client_id, $client_secret)
    {
        $results = array();
        $url = $this->bitly_oauth_access_token_url . "access_token";
        $params = array();
        $params['client_id'] = $client_id;
        $params['client_secret'] = $client_secret;
        $params['code'] = $code;
        $params['redirect_uri'] = $redirect;
        $output = $this->bitly_post_curl($url, $params);
        $parts = explode('&', $output);
        foreach ($parts as $part) {
	        $bits = explode('=', $part);
	        $results[$bits[0]] = $bits[1];
        }
        return $results;
    }

    /**
     * Returns an OAuth access token via the user's bit.ly login Username and Password
     *
     * @param $username
     *   The user's Bitly username
     * @param $password
     *   The user's Bitly password
     * @param $client_id
     *   The client_id assigned to your OAuth app. (http://bit.ly/a/account)
     * @param $client_secret
     *   The client_secret assigned to your OAuth app. (http://bit.ly/a/account)
     *
     * @return
     *   An associative array containing:
     *   - access_token: The OAuth access token for specified user.
     *
     */
    public function bitly_oauth_access_token_via_password($username, $password, $client_id, $client_secret)
    {
	    $results = array();
	    $url = $this->bitly_oauth_access_token_url . "access_token";
	      
	    $headers = array();
	    $headers[] = 'Authorization: Basic '.base64_encode($client_id . ":" . $client_secret);
	        
	    $params = array();
	    $params['grant_type'] = "password";
	    $params['username'] = $username;
	    $params['password'] = $password;
	      
	    $output = $this->bitly_post_curl($url, $params, $headers);
	      
	    $decoded_output = json_decode($output,1);
	    $results = array(
	        "access_token" => $decoded_output['access_token']
	    );
	      
	    return $results;
    }

    /**
     * Format a GET call to the bit.ly API.
     *
     * @param $endpoint
     *   bit.ly API endpoint to call.
     * @param $params
     *   associative array of params related to this call.
     * @param $complex
     *   set to true if params includes associative arrays itself (or using <php5)
     *
     * @return
     *   associative array of bit.ly response
     *
     * @see http://code.google.com/p/bitly-api/wiki/ApiDocumentation#/v3/validate
     */
    public function bitly_get($endpoint, $complex=false)
    {
	    $result = array();
        if ($complex) {
	        $url_params = "";
	        foreach ($this->getParameter as $key => $val) {
		        if (is_array($val)) {
		            // we need to flatten this into one proper command
		            $recs = array();
		            foreach ($val as $rec) {
		                $tmp = explode('/', $rec);
		                $tmp = array_reverse($tmp);
		                array_push($recs, $tmp[0]);
		            }
		            $val = implode('&' . $key . '=', $recs);
		        }
		        $url_params .= '&' . $key . "=" . $val;
	        }
	        $url = $this->bitly_oauth_api_url . $endpoint . "?" . substr($url_params, 1);
        } else {
        	$url = $this->bitly_oauth_api_url . $endpoint . "?" . http_build_query($this->getParameter);
        }
        //echo $url . "\n";
        $result = json_decode($this->bitly_get_curl($url), true);
	    return $result;
    }

    /**
     * Format a POST call to the bit.ly API.
     *
     * @param $uri
     *   URI to call.
     * @param $fields
     *   Array of fields to send.
     */
    public function bitly_post($endpoint)
    {
        $result = array();
        $url = $this->bitly_oauth_api_url . $api_endpoint;
        $output = json_decode($this->bitly_post_curl($url, $this->params), true);
        $result = $output['data'][str_replace('/', '_', $api_endpoint)];
        $result['status_code'] = $output['status_code'];
        return $result;
    }

    /**
     * Make a GET call to the bit.ly API.
     *
     * @param $uri
     *   URI to call.
     */
    public function bitly_get_curl($uri)
    {
	    $output = "";
	    try {
	        $ch = curl_init($uri);
	        curl_setopt($ch, CURLOPT_HEADER, 0);
	        curl_setopt($ch, CURLOPT_TIMEOUT, 4);
	        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
	        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	        $output = curl_exec($ch);
	    } catch (Exception $e) {
	    }
	    return $output;
    }

    /**
     * Make a POST call to the bit.ly API.
     *
     * @param $uri
     *   URI to call.
     * @param $fields
     *   Array of fields to send.
     */
    public function bitly_post_curl($uri, $fields, $header_array = array())
    {
	    $output = "";
	    $fields_string = "";
	    foreach($fields as $key=>$value) { $fields_string .= $key.'='.urlencode($value).'&'; }
	    rtrim($fields_string,'&');
	    try {
	        $ch = curl_init($uri);
	        
	        if(is_array($header_array) && !empty($header_array)){
	            curl_setopt($ch, CURLOPT_HTTPHEADER, $header_array);
	        }
	        
	        curl_setopt($ch, CURLOPT_HEADER, 0);
	        curl_setopt($ch,CURLOPT_POST,count($fields));
	        curl_setopt($ch,CURLOPT_POSTFIELDS,$fields_string);
	        curl_setopt($ch, CURLOPT_TIMEOUT, 2);
	        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
	        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	        $output = curl_exec($ch);
	    } catch (Exception $e) {
	    }
	    return $output;
    }
}
