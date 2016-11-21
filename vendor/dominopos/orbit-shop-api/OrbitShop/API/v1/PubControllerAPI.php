<?php namespace OrbitShop\API\v1;

/**
 * Base Pub API Controller.
 * This is an extension of ControllerAPI with additional user property
 * @author Rio Astamal <me@rioastamal.net>
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
        if (empty($this->user)) {
            // accessing directly not via IntermediatePubAuthController
            $this->checkAuth();
            $this->user = $this->api->user;
        }

        return $this->user;
    }
}
