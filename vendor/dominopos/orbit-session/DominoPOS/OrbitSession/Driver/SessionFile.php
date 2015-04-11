<?php namespace DominoPOS\OrbitSession\Driver;
/**
 * Session driver using File based interface.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use Exception;
use DominoPOS\OrbitSession\Session;

class SessionFile implements GenericInterface
{
    /**
     * Config object
     */
    protected $config = NULL;

    /**
     * Constructor
     */
    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * Start the session
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param SessionData $sessionData
     * @return array
     */
    public function start($sessionData)
    {
        $this->writeSession($sessionData);

        return $this;
    }

    /**
     * Update the session
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param array $sessionData
     * @return array
     */
    public function update($sessionData)
    {
        return $this->start($sessionData);
    }

    /**
     * Destroy a session based on session id.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string $sessionId
     * @return boolean
     */
    public function destroy($sessionId)
    {
        $fname = $this->checkSessionExistence($sessionId);
        if (! is_writable($fname))
        {
            $code = Session::ERR_SAVE_ERROR;
            throw new Exception(sprintf('Could not delete the session file %s.', $fname), $code);
        }

        return unlink($fname);
    }

    /**
     * Clear a session based on session id. The difference from destroy are
     * the clear method only clear the value of the session not entire object.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string $sessionId
     * @return SessionFile
     */
    public function clear($sessionId)
    {
        $sessionData = $this->get($sessionId);

        // Empty the session value
        $sessionData->value = array();

        $this->writeSession($sessionData);

        return $this;
    }

    /**
     * Write a value to a session.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string $sessionId
     * @param string $key
     * @param string $value
     * @return SessionFile
     */
    public function write($sessionId, $key, $value)
    {
        $sessionData = $this->get($sessionId);
        $sessionData->value[$key] = $value;

        $this->writeSession($sessionData);

        return $this;
    }

    /**
     * Read a value from a session.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string $sessionId
     * @param string $key
     * @return mixed
     */
    public function read($sessionId, $key=NULL)
    {
        $sessionData = $this->get($sessionId);
        if (is_null($key)) {
            return $sessionData->value;
        }

        if (! array_key_exists($key, $sessionData->value)) {
            return NULL;
        }
        return $sessionData->value[$key];
    }

    /**
     * Remove a value from a session.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string $sessionId
     * @param string $key
     * @return mixed
     */
    public function remove($sessionId, $key=NULL)
    {
        $sessionData = $this->get($sessionId);
        if (is_null($key)) {
            return $sessionData->value;
        }

        if (! array_key_exists($key, $sessionData->value)) {
            return NULL;
        }

        unset($sessionData->value[$key]);

        $this->writeSession($sessionData);

        return $this;
    }

    /**
     * Get a session based on session id.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string $sessionId
     * @return boolean
     */
    public function get($sessionId)
    {
        $fname = $this->checkSessionExistence($sessionId);
        $stringObj = file_get_contents($fname);

        if (FALSE === ($session = unserialize($stringObj)))
        {
            throw new Exception('Could not unserialize the session file.', Session::ERR_SESS_NOT_FOUND);
        }

        return $session;
    }

    /**
     * Delete all expires session files.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return array of session files which expired
     */
    public function deleteExpires()
    {

    }

    /**
     * Write a session data to a file.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param SessionData $sessionData
     * @return string - Path of the session file
     */
    protected function writeSession($sessionData)
    {
        $path = $this->config->getConfig('path');

        if (! file_exists($path))
        {
            if (! mkdir($path, 0775, TRUE)) {
                $code = Session::ERR_SAVE_ERROR;
                throw new Exception(sprintf('Could not write to session directory %s.', $path), $code);
            }
        }

        if (! is_writable($path))
        {
            $code = Session::ERR_SAVE_ERROR;
            throw new Exception(sprintf('Could not write to session file %s.', $fname), $code);
        }

        $this->touch($sessionData);

        $fname = $path . DIRECTORY_SEPARATOR . $sessionData->id;
        $serialized = serialize($sessionData);

        // Write the serialized session to a file
        file_put_contents($fname, $serialized);

        return $fname;
    }

    /**
     * Check the existence of session id
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string $sessionId
     * @return string - file session name
     */
    protected function checkSessionExistence($sessionId)
    {
        $path = $this->config->getConfig('path');
        $fname = $path . DIRECTORY_SEPARATOR . $sessionId;

        if (! file_exists($fname))
        {
            throw new Exception('Could not find the session file.', Session::ERR_SESS_NOT_FOUND);
        }

        return $fname;
    }

    /**
     * Touch the session data to change the expiration time and last activity.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param sessionData &$sessionData
     * @return void
     */
    protected function touch(&$sessionData)
    {
        // Refresh the session
        $expire = $this->config->getConfig('expire');
        if ($expire !== 0) {
            $sessionData->expireAt = time() + $expire;
        }

        // Last activity
        $sessionData->lastActivityAt = time();
    }
}
