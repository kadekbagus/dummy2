<?php namespace DominoPOS\OrbitSession\Driver;
/**
 * Session driver using database via pdo as backend
 *
 * @author Yudi Rahono <yudi.rahono@dominopos.com>
 *
 */

use Exception;
use DominoPOS\OrbitSession\Helper;
use DominoPOS\OrbitSession\Session;

class SessionDatabase implements GenericInterface
{
    /**
     * config object
     *
     * @var \DominoPOS\OrbitSession\SessionConfig
     */
    protected $config = NULL;

    /**
     * pdo connection
     *
     * @var \PDO
     */
    protected $pdo;


    /**
     * dirty sessions object
     *
     * @var array
     */
    protected $dirty;

    /**
     * Constructor
     *
     * @param \DominoPOS\OrbitSession\SessionConfig $config
     * @throws Exception
     */
    public function __construct($config)
    {
        if (!extension_loaded('pdo'))
        {
            throw new Exception("Extensions PDO Not Loaded", Session::ERR_UNKNOWN);
        }

        $this->config = $config;
        $this->dirty  = [];
        $this->pdo    = $this->createConnection($config->getConfig('connection'));
    }

    /**
     * Destructor
     *
     */
    public function __destruct()
    {
        foreach ($this->dirty as $id=>$dirty) {
            $value = $dirty->value;
            if (array_key_exists('__dirty', $value))
            {
                unset($dirty->value['__dirty']);
                $this->__updateSession($id, $dirty);
                continue;
            }

            if (array_key_exists('__delete', $value))
            {
                $this->__deleteSession($id);
                continue;
            }
        }

        $this->cleanupSomeSessions();
    }

    /**
     * Start a session
     *
     *
     * @param \DominoPOS\OrbitSession\SessionData $sessionData
     * @return SessionDatabase
     */
    public function start($sessionData)
    {
        $this->__insertSession($sessionData);

        return $this;
    }

    /**
     * Update a session
     *
     * @param \DominoPOS\OrbitSession\SessionData $sessionData
     * @return SessionDatabase
     */
    public function update($sessionData)
    {
        return $this->__updateSession($sessionData->id, $sessionData);
    }

    /**
     * Destroy a session
     * @param string $sessionId
     */
    public function destroy($sessionId)
    {
        $current = $this->getCurrent($sessionId);
        $current->value = ["__delete" => true];

        $this->dirty[$sessionId] = $current;
    }

    /**
     * Clear a session
     * @param string $sessionId
     */
    public function clear($sessionId)
    {
        $current = $this->get($sessionId);
        $current->value = ['__dirty' => true];
        $this->dirty[$sessionId] = $current;
    }

    /**
     * Get a session
     * @param string $sessionId
     * @return \DominoPOS\OrbitSession\SessionData
     */
    public function get($sessionId)
    {
        return $this->getCurrent($sessionId);
    }

    /**
     * Write a value to a session.
     * @param integer $sessionId
     * @param mixed $key
     * @param mixed $value
     * @return array
     */
    public function write($sessionId, $key, $value)
    {
        $current    = $this->get($sessionId);
        $current->value[$key]       = $value;
        $current->value['__dirty'] = true;

        $this->dirty[$sessionId] = $current;

        return $current;
    }

    /**
     * Read a value from a session
     * @param string $sessionId
     * @param mixed $key
     * @return mixed
     */
    public function read($sessionId, $key)
    {
        $current = $this->get($sessionId);

        if (is_null($key)) {
            return $current->value;
        }

        if (! array_key_exists($key, $current->value)) {
            return NULL;
        }

        return $current->value[$key];
    }

    /**
     * Remove a value from a session
     * @param $sessionId
     * @param $key
     * @return array
     */
    public function remove($sessionId, $key)
    {
        $current = $this->get($sessionId);

        unset($current->value[$key]);
        $current->value['__dirty'] = true;

        $this->dirty[$sessionId] = $current;

        return $current;
    }

    /**
     * Delete expire session
     */
    public function deleteExpires()
    {
        $this->__deleteSession(NULL, true);
    }

    /**
     * Get Current session from cache or database
     *
     * @param string $sessionId
     * @return \DominoPOS\OrbitSession\SessionData
     */
    protected function getCurrent($sessionId)
    {
        if (array_key_exists($sessionId, $this->dirty))
        {
            $current = $this->dirty[$sessionId];
        } else {
            $current = $this->__getSession($sessionId);
            $this->dirty[$sessionId] = $current;
        }

        return $current;
    }

    /**
     * Get Session from database
     *
     * @param string $sessionId
     * @return array
     * @throws Exception
     */
    protected function __getSession($sessionId)
    {
        $query = $this->pdo->query("
            SELECT * FROM `{$this->getConfig('path')}`
             WHERE session_id = {$this->pdo->quote($sessionId)}
        ");

        if (FALSE === $query)
        {
            throw new Exception($this->pdo->errorInfo()[2], Session::ERR_UNKNOWN);
        }

        $result = $query->fetchObject();

        if (FALSE === $result)
        {
            throw new Exception("Session Not Found", Session::ERR_SESS_NOT_FOUND);
        }

        if (FALSE === ($result = unserialize($result->session_data)))
        {
            throw new Exception('Could not unserialize the session file.', Session::ERR_SESS_NOT_FOUND);
        }

        return $result;
    }

    /**
     * @param string $sessionId
     * @param \DominoPOS\OrbitSession\SessionData $sessionData
     * @return bool
     * @throws Exception
     */
    protected function __updateSession($sessionId, $sessionData)
    {
        Helper::touch($sessionData, $this->getConfig('expire'));

        $data         = serialize($sessionData);

        $query = $this->pdo->prepare("
            UPDATE {$this->getConfig('path')}
            SET
              session_data   = ?,
              last_activity  = ?,
              expire_at      = ?
            WHERE session_id = ?
        ");

        if (FALSE === $query)
        {
            throw new Exception($this->pdo->errorInfo()[2], Session::ERR_UNKNOWN);
        }

        return $query->execute([$data, $sessionData->lastActivityAt, $sessionData->expireAt, $sessionId]);
    }

    /**
     * Delete sessions with optional clean stale sessions
     *
     * @param string $sessionId
     * @param bool $clean
     * @return bool
     * @throws Exception
     */
    protected function __deleteSession($sessionId, $clean = false)
    {

        $id = $this->pdo->quote($sessionId);
        $cleanStatement = '';
        $params = [];
        $params[] = $sessionId;

        if ($clean)
        {
            $cleanStatement = "OR expire_at < ?";
            $params[] = time();
        }

        $query = $this->pdo->prepare("
            DELETE FROM `{$this->getConfig('path')}`
            WHERE session_id = ? {$cleanStatement}
        ");

        if (FALSE === $query)
        {
            throw new Exception($this->pdo->errorInfo()[2], Session::ERR_UNKNOWN);
        }

        return $query->execute($params);
    }

    protected function cleanupSomeSessions()
    {
        $stmt = $this->pdo->prepare("DELETE FROM `{$this->getConfig('path')}` WHERE expire_at < ? LIMIT 10");
        $stmt->execute([time()]);
    }

    /**
     * Insert Initial Session to Database
     *
     * @param \DominoPOS\OrbitSession\SessionData
     * @return bool
     */
    protected function __insertSession($sessionData)
    {

        Helper::touch($sessionData, $this->getConfig('expire'));

        $data         = serialize($sessionData);

        $query =$this->pdo->prepare("
            INSERT INTO `{$this->getConfig('path')}` (session_id, session_data, expire_at, last_activity)
            VALUES (?, ?, ?, ?)
        ");

        return $query->execute([$sessionData->id, $data, $sessionData->expireAt, $sessionData->lastActivityAt]);
    }

    /**
     * Config Proxy Method
     *
     * @param string $name
     * @param null $default
     * @return mixed
     */
    protected function getConfig($name, $default = null)
    {
        return $this->config->getConfig($name, $default);
    }

    /**
     * @param array $connection
     * @return \PDO
     * @throws Exception
     */
    protected function createConnection($connection)
    {
        if ($connection instanceof \PDO)
        {
            return $connection;
        }

        if (! is_array($connection))
        {
            throw new Exception("Unknown connection configuration", Session::ERR_UNKNOWN);
        }

        $host      = Helper::array_get($connection, 'host');
        $port      = Helper::array_get($connection, 'port', '');
        $driver    = Helper::array_get($connection, 'driver');
        $password  = Helper::array_get($connection, 'password');
        $database  = Helper::array_get($connection, 'database', '');
        $username  = Helper::array_get($connection, 'username');
        $charset   = Helper::array_get($connection, 'charset', 'utf8');

        // mysql:host=localhost;port=;dbname=;charset=utf8;
        $dsn  = "{$driver}:host={$host};port={$port};dbname={$database};charset={$charset}";

        return new \PDO($dsn, $username, $password);
    }
}
