<?php namespace DominoPOS\OrbitACL\Exception;
/**
 * Exception class for Unauthenticated request (401).
 *
 * Unauthenticated here means no User information can be resolved
 * from headers/query strings.
 *
 * @author Budi <budi@gotomalls.com>
 */
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;

class ACLUnauthenticatedException extends \Exception
{
    public function __construct($message = null)
    {
        if (empty($message)) {
            $message = Status::ACCESS_DENIED_MSG;
        }

        parent::__construct($message, Status::ACCESS_DENIED);
    }
}
