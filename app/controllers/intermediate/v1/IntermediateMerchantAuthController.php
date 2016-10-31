<?php
/**
 * Base Intermediate Controller for Merchant Database Manager controller which need authentication.
 *
 * @author Ahmad <ahmad@dominopos.com>
 */
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use OrbitShop\API\v1\ResponseProvider;
use DominoPOS\OrbitSession\Session;
use DominoPOS\OrbitSession\SessionConfig;

class IntermediateMerchantAuthController extends IntermediateBaseController
{
    /**
     * Check the authenticated user on constructor
     *
     * @author Ahmad <ahmad@dominopos.com>
     */
    public function __construct()
    {
        parent::__construct();

        $this->beforeFilter(function()
        {
            try
            {
                $this->session->start();

                if (! $this->authCheck()) {
                    $message = Lang::get('validation.orbit.access.needtologin');
                    ACL::throwAccessForbidden($message);
                }
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
