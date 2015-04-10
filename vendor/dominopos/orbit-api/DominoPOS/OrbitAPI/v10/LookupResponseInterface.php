<?php
/**
 * The interface for looking up the client secret key
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
namespace DominoPOS\OrbitAPI\v10;

interface LookupResponseInterface {
    const LOOKUP_STATUS_OK = 0;
    const LOOKUP_STATUS_NOT_FOUND = 1;
    const LOOKUP_STATUS_ACCESS_DENIED = 2;
    
    public function getStatus();
    public function getClientID();
    public function getClientSecretKey();
}
