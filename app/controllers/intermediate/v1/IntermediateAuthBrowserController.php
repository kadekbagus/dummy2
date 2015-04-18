<?php
/**
 * Base Intermediate Controller for all controller which need authentication.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use OrbitShop\API\v1\Helper\Generator;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;

class IntermediateAuthBrowserController extends IntermediateBaseController
{
    /**
     * Data which will be passed to the view.
     *
     * @var array
     */
    protected $viewData = [];

    /**
     * Check the authenticated user on constructor
     *
     * @author Rio Astamal <me@rioastamal.net>
     */
    public function __construct()
    {
        parent::__construct();

        $this->beforeFilter(function()
        {
            try
            {
                $this->session->start();

                if (! ($userId = $this->authCheck())) {
                    $message = Lang::get('validation.orbit.access.needtologin');
                    ACL::throwAccessForbidden($message);
                }

                $user = User::find($userId);

                // This will query the database if the apikey has not been set up yet
                $apikey = $user->apikey;

                if (empty($apikey)) {
                    // Create new one
                    $apikey = $user->createAPiKey();
                }

                // Generate the signature
                $_GET['apikey'] = $apikey->api_key;
                $_GET['apitimestamp'] = time();
                $signature = Generator::genSignature($apikey->api_secret_key);
                $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = $signature;

            } catch (ACLForbiddenException $e) {
                return Redirect::to('/?forbidden');
            } catch (Exception $e) {
                return Redirect::to('/?unknown-exception');
            }
        });
    }
}
