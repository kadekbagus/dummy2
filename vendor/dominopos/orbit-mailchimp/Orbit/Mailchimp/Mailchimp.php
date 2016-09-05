<?php namespace Orbit\Mailchimp;
/**
 * Class for interacting with Mailchimp API.
 *
 * @author Rio Astamal <rio@dominopos.com>
 */
use CurlWrapper;
use Log;
use stdClass;
use Exception;

class Mailchimp
{
    protected $config = [
        /**
         * API USER (not used)
         */
        'api_user' => NULL,

        /**
         * API KEY (not used)
         */
        'api_key' => NULL,

        /**
         * Should be a path to the directory
         * which stores various endpoints
         */
        'api_url' => NULL
    ];

    /**
     * Object which used to call Mailchimp HTTPS API. Default would be set
     * to CurlWrapper
     */
    protected $poster = NULL;

    public function __construct($config)
    {
        $this->config = $config;
        $this->poster = new CurlWrapper();
    }

    public static function create($config)
    {
        return new static($config);
    }

    public function setConfig(array $config)
    {
        $this->config = $config;

        return $this;
    }

    public function getConfig()
    {
        return $this->config;
    }

    public function setPoster($poster)
    {
        $this->poster = $poster;

        return $this;
    }

    public function getPoster()
    {
        return $this->poster;
    }

    /**
     * Add subscriber email address to the Mailchimp list.
     *
     * @param string $listId
     * @param array $params Subsriber data
     * @return boolean
     */
    public function postMembers($listId, array $params=[])
    {
        // Set HTTP Basic authentication
        $httpUser = $this->config['api_user'];
        $httpPassword = $this->config['api_key'];
        $apiBaseUrl = rtrim($this->config['api_url'], '/');
        $apiPostMemberUrl = sprintf($apiBaseUrl . '/lists/%s/members', $listId);

        try {
            Log::info('MAILCHIMP API -- Post Members -- Going to call ' . $apiPostMemberUrl);
            $this->poster->setAuthType();
            $this->poster->setAuthCredentials($httpUser, $httpPassword);
            $this->poster->addHeader('Accept', 'application/json');
            $this->poster->addHeader('Content-type', 'application/json');

            $postData = new stdClass();
            $postData->email_address = isset($params['email']) ? $params['email'] : '';
            $postData->status = isset($params['status']) ? $params['status'] : 'subscribed';
            $postData->merge_fields = new stdClass();
            $postData->merge_fields->FNAME = isset($params['first_name']) ? $params['first_name'] : '';
            $postData->merge_fields->LNAME = isset($params['last_name']) ? $params['last_name'] : '';
            $encodedJsonData = json_encode($postData);

            Log::info('MAILCHIMP API -- Post Members -- Post data ' . $encodedJsonData);
            $response = $this->poster->rawPost($apiPostMemberUrl, $encodedJsonData);
            $httpCode = $this->poster->getTransferInfo('http_code');

            if ($httpCode !== 200) {
                throw new Exception ($response->detail, $httpCode);
            }

            $message = sprintf('Email %s has been added to lists %s', $postData->email_address, $listId);
            Log::info(sprintf('MAILCHIMP API -- Post Members -- Status: OK -- Message: %s', $message));

            return TRUE;
        } catch (CurlWrapperException $e) {
            $message = sprintf('Email %s failed added to lists %s', $params['email'], $listId);
            Log::info(sprintf('MAILCHIMP API -- Post Members -- Status: FAIL -- Message: %s -- Details: %s', $message, $e->getMessage()));
        } catch (Exception $e) {
            $message = sprintf('Email %s failed added to lists %s', $params['email'], $listId);
            Log::info(sprintf('MAILCHIMP API -- Post Members -- Status: FAIL -- Message: %s -- Details: %s', $message, $e->getMessage()));
        }

        return FALSE;
    }
}