<?php namespace DominoPOS\OrbitSession;
/**
 * Structure of Session Data
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
class SessionData
{
    /**
     * Session ID
     *
     * @var string
     */
    public $id = '';

    /**
     * Created time
     *
     * @var int
     */
    public $createdAt = 0;

    /**
     * Last activity time
     *
     * @var int
     */
    public $lastActivityAt = 0;

    /**
     * Expire time
     *
     * @var int
     */
    public $expireAt = 0;

    /**
     * Session value
     *
     * @var string
     */
    public $value = '';

    /**
     * Client User Agent
     *
     * @var string
     */
    public $userAgent = '';

    /**
     * Client IP Address
     *
     * @var string
     */
    public $ipAddress = '';

    /**
     * Application ID or null
     * @var int
     */
    public $applicationId = null;


    /**
     * Constructor
     */
    public function __construct(array $value, $applicationId = null, $sessionId = null)
    {
        $this->id = ! empty($sessionId) ? $sessionId : $this->genSessionId();
        $this->applicationId = $applicationId;
        $this->userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Unknown UA/?';
        $this->ipAddress = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
        $this->value = $value;
    }

    /**
     * Generate session id
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param array $data
     * @return string
     */
    public function genSessionId()
    {
        $source = serialize($this->value) . microtime(TRUE) . mt_rand();
        $source = $source . $this->userAgent;
        $source = $source . $this->ipAddress;

        // Take the first 14
        $ipHash = substr(md5($this->ipAddress .  microtime(TRUE)), 0, 14);
        $sessionHash = sha1($source);

        return sprintf('%s%s', $ipHash, $sessionHash);
    }
}
