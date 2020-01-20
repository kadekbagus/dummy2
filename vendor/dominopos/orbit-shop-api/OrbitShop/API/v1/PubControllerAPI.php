<?php namespace OrbitShop\API\v1;

use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
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
}
