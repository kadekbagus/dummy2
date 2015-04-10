<?php
/**
 * A Controller for returning the API version number.
 */
use OrbitShop\API\v1\ControllerAPI;

class OrbitVersionAPIController extends ControllerAPI
{
    /**
     * Return the application version.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return ResponseProvider
     */
    public function getVersion()
    {
        $info = new stdClass();
        $info->version = ORBIT_APP_VERSION;
        $info->build_number = ORBIT_APP_BUILD_NUMBER;
        $info->release_date = ORBIT_APP_RELEASE_DATE;
        $info->build_date = ORBIT_APP_BUILD_DATE;
        $info->codename = ORBIT_APP_CODENAME;

        $this->formatInfo($info);

        $this->response->data = $info;

        if (! isset($info->plain)) {
            return $this->render();
        }

        return $info->output;
    }
}
