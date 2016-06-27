<?php namespace Orbit\Helper\Net;
use \Config;
use DominoPOS\OrbitSession\Session;
use DominoPOS\OrbitSession\SessionConfig;
use \Exception;

class SessionPreparer
{
    const APPLICATION_ID = 1;
    /**
     * Prepare session.
     *
     * @return session
     */
    public static function prepareSession()
    {   
        // set the session strict to FALSE
        Config::set('orbit.session.strict', FALSE);

        $config = new SessionConfig(Config::get('orbit.session'));
        $config->setConfig('application_id', static::APPLICATION_ID);
        try {
            $session = new Session($config);
            $session->start(array(), 'no-session-creation');
        } catch (Exception $e) {
            $session->start();
        }

        return $session;
    }
}