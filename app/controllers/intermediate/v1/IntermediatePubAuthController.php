<?php

use DominoPOS\OrbitACL\ACL;
use Orbit\Helper\Net\SessionPreparer;
use Orbit\Helper\Net\UrlChecker;
use Orbit\Helper\Session\UserGetter;

/**
 * Intermediate Controller for all pub controller which need authentication.
 *
 * @author Ahmad <ahmad@dominopos.com>
 */
class IntermediatePubAuthController extends IntermediateBaseController
{
    const APPLICATION_ID = 1;

    /**
     * Check the authenticated user on constructor
     *
     * @author Ahmad <ahnad@dominopos.com>
     */
    public function __construct()
    {
        parent::__construct();

        $this->beforeFilter(function()
        {
            try
            {
                $this->session = SessionPreparer::prepareSession();

                // Get user, or generate guest user for new session
                $user = UserGetter::getLoggedInUserOrGuest($this->session);

                if (empty($user)) {
                    ACL::throwUnauthenticatedRequest();
                }

                // Register User instance in the container,
                // so it will be accessible from anywhere
                // without doing any re-check/DB call.
                App::instance('currentUser', $user);

                // Check for blocked url
                UrlChecker::checkBlockedUrl($user);
            } catch (Exception $e) {
                return $this->handleException($e);
            }
        });
    }
}
