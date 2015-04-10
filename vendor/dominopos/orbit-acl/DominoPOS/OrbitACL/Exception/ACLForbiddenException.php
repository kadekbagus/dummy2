<?php namespace DominoPOS\OrbitACL\Exception;
/**
 * Exception class for Access Forbidden.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;

class ACLForbiddenException extends \Exception
{
    public function __construct($message)
    {
        if (empty($message)) {
            $message = Status::ACCESS_DENIED_MSG;
        }

        parent::__construct($message, Status::ACCESS_DENIED);
    }
}
