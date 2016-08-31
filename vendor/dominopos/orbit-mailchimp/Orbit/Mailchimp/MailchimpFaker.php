<?php namespace Orbit\Mailchimp;
/**
 * The class which fake Mailchimp implementation. This faker
 * will fake the calls to Mailchimp API. Instead of calling
 * the mailchimp API it open a file for the result set.
 *
 * @author Rio Astamal <rio@dominopos.com>
 */
use stdClass;
use Log;

class MailchimpFaker implements MailchimpInterface
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

    public function __construct($config)
    {
        $this->config = $config;
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

    /**
     * Add subscriber email address to the Mailchimp list.
     *
     * @param string $listId
     * @param array $params Subsriber data
     * @return boolean
     */
    public function postMembers($listId, array $params=[])
    {
        $baseDirectory = $this->config['api_url'];
        $membersDirectory = $baseDirectory . '/lists/' . $listId . '/members';

        Log::info('MAILCHIMP FAKER -- Post Members -- Going to write ' . $membersDirectory);
        if (! file_exists($membersDirectory)) {
            mkdir($membersDirectory, 0755, TRUE);
        }

        $postData = new stdClass();
        $postData->email_address = isset($params['email']) ? $params['email'] : '';
        $postData->status = isset($params['status']) ? $params['status'] : 'subscribed';
        $postData->merge_fields = new stdClass();
        $postData->merge_fields->FNAME = isset($params['first_name']) ? $params['first_name'] : '';
        $postData->merge_fields->LNAME = isset($params['last_name']) ? $params['last_name'] : '';
        $encodedJsonData = json_encode($postData);

        $memberId = substr(md5($postData->email_address), 0, 10);
        $filename = $membersDirectory . '/' . $memberId;

        if (file_put_contents($filename, $encodedJsonData)) {
            Log::info('MAILCHIMP FAKER -- Post Members -- Status: OK -- Write data to: ' . $filename . '; -- Contents: ' . $encodedJsonData . ';');
            return TRUE;
        }

        Log::info('MAILCHIMP FAKER -- Post Members -- Status: FAIL -- Write data to: ' . $filename . ' -- Contents: ' . $encodedJsonData . ';');

        return FALSE;
    }
}