<?php namespace OrbitShop\API\v1;

use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Lang;
use OrbitShop\API\v1\ExceptionResponseProvider;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use OrbitShop\API\v1\OrbitShopAPI;

/**
 * Base Pub API Controller.
 * This is an extension of ControllerAPI with additional user property
 *
 * @author Ahmad <ahmad@dominopos.com>
 */

class PubControllerAPI extends ControllerAPI
{
    public $user = null;

    public function setUser($user)
    {
        $this->user = $user;

        return $this;
    }

    public function getUser()
    {
        $this->checkAuth();

        return $this->api->user;
    }

    public function checkAuth($forbiddenUserStatus=['deleted'])
    {
        if (! empty($this->user)) {
            // accessing via IntermediatePubAuthController
            // use user set on IntermediateBaseController
            $this->api = new \stdClass();
            $this->api->user = $this->user;
        } else {
            // Get the api key from query string
            $clientkey = (isset($_GET['apikey']) ? $_GET['apikey'] : '');

            // Instantiate the OrbitShopAPI
            $this->api = new OrbitShopAPI($clientkey, $forbiddenUserStatus);

            // Set the request expires time
            $this->api->expiresTimeFrame = $this->expiresTime;

            // Run the signature check routine
            $this->api->checkSignature();
        }
    }

    /**
     * Authorize specific user roles.
     *
     * @todo currentUser binding should be a dedicated service (registered in service provider)
     * @param  array  $roles [description]
     * @return [type]        [description]
     */
    public function authorize($roles = [])
    {
        $user = $this->getUser();

        $role = $user->role->role_name;
        if (! in_array(strtolower($role), $roles)) {
            $message = 'You have to login to continue';
            OrbitShopAPI::throwInvalidArgument($message);
        }

        // Bind current user into container so it is accessible
        // from anywhere.
        App::instance('currentUser', $user);
    }

    /**
     * Build response when we catch exception.
     *
     * @param  \Exception $e                    [description]
     * @param  boolean    $withDatabaseRollback [description]
     * @return [type]                           [description]
     */
    protected function buildExceptionResponse($e, $withDatabaseRollback = true)
    {
        if ($withDatabaseRollback) {
            $this->rollBack();
        }

        $httpCode = 500;
        $body = new ExceptionResponseProvider($e);

        if ($e instanceof ACLForbiddenException) {
            $httpCode = 403;
            // set custom code/message
        }
        else if ($e instanceof InvalidArgsException) {
            $httpCode = 422;
            // set custom code/message
        }
        else if ($e instanceof QueryException) {
            if (Config::get('app.debug')) {
                $body->message = $e->getMessage();
            } else {
                $body->message = Lang::get('validation.orbit.queryerror');
            }
        }
        else {
            // set other code/message...
        }

        return compact('httpCode', 'body');
    }
}
