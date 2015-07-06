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
     * Constructor
     */
    public function __construct(array $value)
    {
        $this->id = $this->genSessionId();
        $this->userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Unknown UA/?';
        $this->ipAddress = $_SERVER['REMOTE_ADDR'];
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

        return sha1( $source );
    }
}
