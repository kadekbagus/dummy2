<?php namespace OrbitShop\API\v1;
/**
 * Base response provider for Controller API.
 *
 * @author Budi <budi@gotomalls.com>
 */
class ExceptionResponseProvider extends ResponseProvider
{
    public function __construct($e)
    {
        parent::__construct();
        // Set the default value for the response.
        $this->code     = $e->getCode();
        $this->status   = 'error';
        $this->message  = $e->getMessage();
    }
}
