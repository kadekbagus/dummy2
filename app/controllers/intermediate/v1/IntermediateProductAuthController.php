<?php
/**
 * Base Intermediate Controller for Product Manager Portal controller which need authentication.
 *
 * @author kadek <kadek@dominopos.com>
 */
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use DominoPOS\OrbitACL\Exception\ACLUnauthenticatedException;
use DominoPOS\OrbitSession\Session;
use DominoPOS\OrbitSession\SessionConfig;
use OrbitShop\API\v1\ResponseProvider;
use Orbit\Helper\Session\UserGetter;

class IntermediateProductAuthController extends IntermediateBaseController
{
    /**
     * Check the authenticated user on constructor
     *
     * @author kadek <kadek@dominopos.com>
     */
    public function __construct()
    {
        parent::__construct();

        $this->beforeFilter(function()
        {
            try
            {
                $this->session->start();

                // Get user, or generate guest user for new session
                $user = UserGetter::getLoggedInUser($this->session, $this->allowedRoles);

                if (empty($user)) {
                    ACL::throwUnauthenticatedRequest();
                }

                // Register User instance in the container,
                // so it will be accessible from anywhere
                // without doing any re-check/DB call.
                App::instance('currentUser', $user);

            } catch (Exception $e) {
                return $this->handleException($e);
            }
        });
    }
}
