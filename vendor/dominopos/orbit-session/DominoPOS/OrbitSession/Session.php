<?php namespace DominoPOS\OrbitSession;
/**
 * Simple session class for orbit.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use Exception;

class Session
{
    /**
     * Hold the SessionConfig object
     *
     * @var SessionConfig
     */
    protected $config = NULL;

    /**
     * The session driver interface
     *
     * @var GenericInterface
     */
    protected $driver = NULL;

    /**
     * The current ID of the session
     *
     * @var string
     */
    protected $sessionId = NULL;

    /**
     * Force creation of new session even session id are supplied.
     *
     * @var boolean
     */
    protected $forceNew = FALSE;

    protected $byPassExpiryCheck = FALSE;

    /**
     * List of static error codes
     */
    const ERR_UNKNOWN = 51;
    const ERR_IP_MISS_MATCH = 52;
    const ERR_UA_MISS_MATCH = 53;
    const ERR_SESS_NOT_FOUND = 54;
    const ERR_SESS_EXPIRE = 55;
    const ERR_SAVE_ERROR = 56;
    const ERR_READ_ERROR = 57;
    const ERR_DELETE_ERROR = 58;

    /**
     * Constructor
     */
    public function __construct($config)
    {
        $this->config = $config;

        // Example: if the driver 'file' then the driver name would be 'SessionFile'
        $driverName = 'DominoPOS\\OrbitSession\\Driver\\Session' . ucwords($config->getConfig('driver'));
        $this->driver = new $driverName($config);
    }

    /**
     * Start the session
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param mixed $data - Data which will be stored on session
     * @param string $mode - mode of start, valid: 'default', 'no-session-creation'
     * @return SessionData
     */
    public function start(array $data=array(), $mode='default')
    {
        // Check if we got session id
        $availabilities = $this->config->getConfig('availability');
        $now = time();

        // Check session from header first
        $headerName = $this->config->getConfig('session_origin.header.name');
        $headerName = strtoupper($headerName);
        $headerName = 'HTTP_' . str_replace('-', '_', $headerName);

        $queryStringName = $this->config->getConfig('session_origin.query_string.name');
        $cookieName = $this->config->getConfig('session_origin.cookie.name');

        $applicationId = $this->config->getConfig('application_id');

        if (array_key_exists('header', $availabilities)
            && $availabilities['header'] === TRUE
            && isset($_SERVER[$headerName])) {

            if (! empty($_SERVER[$headerName])) {
                $this->sessionId = $_SERVER[$headerName];
            }

        // Check session from query string
        } elseif (array_key_exists('query_string', $availabilities)
            && $availabilities['query_string'] === TRUE
            && isset($_GET[$queryStringName])) {

            if (! empty($_GET[$queryStringName])) {
                $this->sessionId = $_GET[$queryStringName];
            }

        // Check session from cookie
        } elseif (array_key_exists('cookie', $availabilities)
            && $availabilities['cookie'] === TRUE
            && isset($_COOKIE[$cookieName])) {

            if (! empty($_COOKIE[$cookieName])) {
                $this->sessionId = $_COOKIE[$cookieName];
            }
        }

        if ($this->forceNew === TRUE || empty($this->sessionId)) {
            if ($mode === 'no-session-creation') {
                throw new Exception ('No session found.', static::ERR_SESS_NOT_FOUND);
            }

            $sessionData = new SessionData($data, $applicationId);
            $sessionData->createdAt = $now;
            $this->driver->start($sessionData);

            $this->sessionId = $sessionData->id;

            // Send the session id via cookie to the client
            $this->sendCookie($sessionData->id);
        } else {
            try {
                $sessionData = $this->driver->get($this->sessionId);

                // We got the session, check if we use strict checking
                if ($this->config->getConfig('strict') === TRUE) {
                    if ($sessionData->userAgent !== $_SERVER['HTTP_USER_AGENT']) {
                        throw new Exception ('User agent miss match.', static::ERR_UA_MISS_MATCH);
                    }

                    if ($sessionData->ipAddress !== $_SERVER['REMOTE_ADDR']) {
                        throw new Exception ('IP address miss match.', static::ERR_IP_MISS_MATCH);
                    }
                }

                // Does the session already expire?
                $expireAt = $sessionData->expireAt;
                if ($this->byPassExpiryCheck) {
                    if ($expireAt < $now) {
                        throw new Exception ('Your session has expired.', static::ERR_SESS_EXPIRE);
                    }
                }

                $this->driver->update($sessionData);
            } catch (Exception $e) {
                switch ($e->getCode()) {
                    // User agent or IP miss match, sesion hijacking?
                    case static::ERR_IP_MISS_MATCH:
                    case static::ERR_UA_MISS_MATCH:
                        throw new Exception($e->getMessage(), $e->getCode());
                        break;

                    // Clear the session value
                    case static::ERR_SESS_EXPIRE:
                        throw new Exception($e->getMessage(), $e->getCode());
                        break;

                    // The session file probably does not exists, so create new session
                    default:
                        $sessionData = new SessionData($data=array());
                        $sessionData->createdAt = time();
                        $this->driver->start($sessionData);

                        $this->sessionId = $sessionData->id;

                        // Send the session id via cookie to the client
                        $this->sendCookie($sessionData->id);
                }
            }
        }

        return $this;
    }

    /**
     * Update the session
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param array $data
     * @return Session
     */
    public function update(array $data)
    {
        if (! $this->sessionId) {
            $this->driver->start($data);
        } else {
            $sessionData = $this->driver->get($this->sessionId);
            $sessionData->value = $data;
            $this->driver->update($sessionData);
        }

        return $this;
    }

    /**
     * Update the session using raw SessionData object
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param SessionData $sessionData - Instance of SessionData object
     * @return Session
     */
    public function rawUpdate(SessionData $sessionData)
    {
        $this->driver->update($sessionData);

        return $this;
    }

    /**
     * Manually set the session id.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string $sid - Session ID
     * @return Session
     */
    public function setSessionId($sid)
    {
        $this->sessionId = $sid;

        return $this;
    }

    /**
     * Set a data on session.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string $key
     * @param string $value
     * @return Session
     */
    public function write($key, $value)
    {
        $this->driver->write($this->sessionId, $key, $value);

        return $this;
    }

    /**
     * Read a data on session.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string $key
     * @return mixed
     */
    public function read($key)
    {
        return $this->driver->read($this->sessionId, $key);
    }

    /**
     * Remove a data on session.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string $key
     * @return mixed
     */
    public function remove($key)
    {
        return $this->driver->remove($this->sessionId, $key);
    }

    /**
     * Clear a data on session.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return mixed
     */
    public function clear()
    {
        return $this->driver->clear($this->sessionId);
    }

    /**
     * Destroy a session.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return boolean
     */
    public function destroy()
    {
        // Destroy the cookie
        $this->sendCookie($this->sessionId, TRUE);

        return $this->driver->destroy($this->sessionId);
    }

    /**
     * Get the session data object
     *
     * @author Rio Astamal <me@rioastamal.ne>
     * @param string id - Session Id
     * @return SessionData
     */
    public function getSession()
    {
        return $this->driver->get($this->sessionId);
    }

    /**
     * Get the session id
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return string
     */
    public function getSessionId()
    {
        return $this->sessionId;
    }

    /**
     * Get session config
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return SessionConfig
     */
    public function getSessionConfig()
    {
        return $this->config;
    }

    /**
     * Set the force new flag.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return Session
     */
    public function enableForceNew()
    {
        $this->forceNew = TRUE;

        return $this;
    }

    /**
     * disable the force new flag.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return Session
     */
    public function disableForceNew()
    {
        $this->forceNew = FALSE;

        return $this;
    }

    public function setByPassExpiryCheck($check=false)
    {
        $this->byPassExpiryCheck = $check;

        return $this;
    }

    /**
     * Send session id to the client via cookie.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param boolean $makeItExpire
     * @return void
     */
    protected function sendCookie($sessionId, $makeItExpire=FALSE)
    {
        $availabilities = $this->config->getConfig('availability');

        if (array_key_exists('cookie', $availabilities) && $availabilities['cookie'] === TRUE) {
            $cookieConfig = $this->config->getConfig('session_origin.cookie');
            $expire = $cookieConfig['expire'] + time();

            if ($makeItExpire) {
                $expire = time() - 3600;
                unset($_COOKIE[$cookieConfig['name']]);
            } else {
                $_COOKIE[$cookieConfig['name']] = $sessionId;
            }

            setcookie(
                $cookieConfig['name'],
                $sessionId,
                $expire,
                $cookieConfig['path'],
                $cookieConfig['domain'],
                $cookieConfig['secure'],
                $cookieConfig['httponly']
            );

        }

        if (array_key_exists('query_string', $availabilities)
            && $availabilities['query_string'] === TRUE) {
            $queryStringName = $this->config->getConfig('session_origin.query_string.name');
            if ($makeItExpire) {
                unset($_GET[$queryStringName]);
            } else {
                $_GET[$queryStringName] = $sessionId;
            }
        }
    }
}
