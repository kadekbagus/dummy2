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
    public static function prepareSession($from_query_string = FALSE)
    {   
        // set the session strict to FALSE
        Config::set('orbit.session.strict', FALSE);
        if ($from_query_string) {
            Config::set('orbit.session.availability.query_string', TRUE);
        } else {
            // set the query session string to FALSE, so the CI will depend on session cookie
            Config::set('orbit.session.availability.query_string', FALSE);
        }

        // This user assumed are Consumer, which has been checked at login process
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