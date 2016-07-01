<?php namespace Orbit\Helper\Net;
use \Config;
use DominoPOS\OrbitSession\Session;
use DominoPOS\OrbitSession\SessionConfig;
use \Exception;
use Orbit\Helper\Session\AppOriginProcessor;

class SessionPreparer
{
    /**
     * Prepare session.
     *
     * @return session
     */
    public static function prepareSession()
    {   
        // set the session strict to FALSE
        Config::set('orbit.session.strict', FALSE);

        // Return mall_portal, cs_portal, pmp_portal etc
        $appOrigin = AppOriginProcessor::create(Config::get('orbit.session.app_list'))
                                       ->getAppName();

        // Session Config
        $orbitSessionConfig = Config::get('orbit.session.origin.' . $appOrigin);
        $applicationId = Config::get('orbit.session.app_id.' . $appOrigin);

        // Instantiate the OrbitSession object
        $config = new SessionConfig(Config::get('orbit.session'));
        $config->setConfig('session_origin', $orbitSessionConfig);
        $config->setConfig('application_id', $applicationId);

        try {
            $session = new Session($config);
            $session->start(array(), 'no-session-creation');
        } catch (Exception $e) {
            $session->start();
        }

        return $session;
    }
}