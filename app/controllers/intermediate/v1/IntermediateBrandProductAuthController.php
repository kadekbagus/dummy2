<?php
/**
 * Base Intermediate Controller for Brand Product Portal controller which need authentication.
 *
 * @author kadek <kadek@dominopos.com>
 */
use DominoPOS\OrbitACL\ACL;
use Orbit\Helper\Session\UserGetter;
use OrbitShop\API\v1\Helper\Input as OrbitInput;

class IntermediateBrandProductAuthController extends IntermediateBaseController
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
                $user = UserGetter::getLoggedInUserBpp($this->session);

                if (empty($user)) {
                    ACL::throwUnauthenticatedRequest();
                }

                // Register User instance in the container,
                // so it will be accessible from anywhere
                // without doing any re-check/DB call.
                App::instance('currentUser', $user);

                // check session
                $sessionKey = Config::get('orbit.session.app_list.bpp_portal', 'X-OMS-BPP');
                $sessionString = OrbitInput::get($sessionKey);

                if (empty($sessionKey) || empty($sessionString)) {
                    throw new Exception("Error Processing Request", 1);
                }

                $session = DB::table('sessions')
                    ->where('session_id', $sessionString)
                    ->first();

                if (! is_object($session)) {
                    throw new Exception("You need to login to continue.", 1);
                }

            } catch (Exception $e) {
                return $this->handleException($e);
            }
        });
    }
}
