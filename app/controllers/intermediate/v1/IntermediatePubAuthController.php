<?php
/**
 * Intermediate Controller for all pub controller which need authentication.
 *
 * @author Ahmad <ahmad@dominopos.com>
 */
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use OrbitShop\API\v1\ResponseProvider;
use DominoPOS\OrbitSession\Session;
use DominoPOS\OrbitSession\SessionConfig;
use Orbit\Helper\Net\UrlChecker;
use Orbit\Helper\Net\SessionPreparer;
use Orbit\Helper\Session\UserGetter;

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
                // Check for blocked url
                UrlChecker::checkBlockedUrl($user);
            } catch (ACLForbiddenException $e) {
                $response = new ResponseProvider();
                $response->code = $e->getCode();
                $response->status = 'error';
                $response->message = $e->getMessage();

                return $this->render($response);
            } catch (Exception $e) {
                $response = new ResponseProvider();
                $response->code = $e->getCode();
                $response->status = 'error';
                $response->message = $e->getMessage();

                return $this->render($response);
            }
        });
    }
}
