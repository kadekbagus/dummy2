<?php
namespace OAuth\Common\Storage;

use OAuth\Common\Token\TokenInterface;
use OAuth\Common\Storage\Exception\TokenNotFoundException;
use OAuth\Common\Storage\Exception\AuthorizationStateNotFoundException;
use DominoPOS\OrbitSession\Session;
use DominoPOS\OrbitSession\SessionConfig;
use Orbit\Helper\Session\AppOriginProcessor;
use Config;

class OrbitSession implements TokenStorageInterface
{
    private $session;
    private $sessionVariableName;
    private $stateVariableName;

    /**
     * @param Session $session
     * @param bool $startSession
     * @param string $sessionVariableName
     * @param string $stateVariableName
     */
    public function __construct(
        Session $session = null,
        $startSession = true,
        $sessionVariableName = 'lusitanian_oauth_token',
        $stateVariableName = 'lusitanian_oauth_state'
    ) {
    	if (isset($session)) {
	        $this->session = $session;
    	} else {
    		// TODO should fix this
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

            $this->session = new Session($config);
            $this->session->start();
    	}
        $this->sessionVariableName = $sessionVariableName;
        $this->stateVariableName = $stateVariableName;
    }

    /**
     * {@inheritDoc}
     */
    public function retrieveAccessToken($service)
    {
        if ($this->hasAccessToken($service)) {
            // get from session
            $tokens = $this->session->read($this->sessionVariableName);

            // one item
            return $tokens[$service];
        }

        throw new TokenNotFoundException('Token not found in session, are you sure you stored it?');
    }

    /**
     * {@inheritDoc}
     */
    public function storeAccessToken($service, TokenInterface $token)
    {
        // get previously saved tokens
        $tokens = $this->session->read($this->sessionVariableName);

        if (!is_array($tokens)) {
            $tokens = array();
        }

        $tokens[$service] = $token;

        // save
        $this->session->write($this->sessionVariableName, $tokens);

        // allow chaining
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function hasAccessToken($service)
    {
        // get from session
        $tokens = $this->session->read($this->sessionVariableName);

        return is_array($tokens)
            && isset($tokens[$service])
            && $tokens[$service] instanceof TokenInterface;
    }

    /**
     * {@inheritDoc}
     */
    public function clearToken($service)
    {
        // get previously saved tokens
        $tokens = $this->session->read($this->sessionVariableName);

        if (is_array($tokens) && array_key_exists($service, $tokens)) {
            unset($tokens[$service]);

            // Replace the stored tokens array
            $this->session->write($this->sessionVariableName, $tokens);
        }

        // allow chaining
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function clearAllTokens()
    {
        $this->session->remove($this->sessionVariableName);

        // allow chaining
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function retrieveAuthorizationState($service)
    {
        if ($this->hasAuthorizationState($service)) {
            // get from session
            $states = $this->session->read($this->stateVariableName);

            // one item
            return $states[$service];
        }

        throw new AuthorizationStateNotFoundException('State not found in session, are you sure you stored it?');
    }

    /**
     * {@inheritDoc}
     */
    public function storeAuthorizationState($service, $state)
    {
        // get previously saved tokens
        $states = $this->session->read($this->stateVariableName);

        if (!is_array($states)) {
            $states = array();
        }

        $states[$service] = $state;

        // save
        $this->session->write($this->stateVariableName, $states);

        // allow chaining
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function hasAuthorizationState($service)
    {
        // get from session
        $states = $this->session->read($this->stateVariableName);

        return is_array($states)
        && isset($states[$service])
        && null !== $states[$service];
    }

    /**
     * {@inheritDoc}
     */
    public function clearAuthorizationState($service)
    {
        // get previously saved tokens
        $states = $this->session->read($this->stateVariableName);

        if (is_array($states) && array_key_exists($service, $states)) {
            unset($states[$service]);

            // Replace the stored tokens array
            $this->session->write($this->stateVariableName, $states);
        }

        // allow chaining
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function clearAllAuthorizationStates()
    {
        $this->session->remove($this->stateVariableName);

        // allow chaining
        return $this;
    }

    /**
     * @return Session
     */
    public function getSession()
    {
        return $this->session;
    }
}
