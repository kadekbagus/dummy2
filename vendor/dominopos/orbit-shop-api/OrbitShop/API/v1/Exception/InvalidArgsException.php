<?php namespace OrbitShop\API\v1\Exception;
/**
 * Exception class for Access Forbidden.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;

class InvalidArgsException extends \Exception
{
    public function __construct($message)
    {
        if (empty($message)) {
            $message = Status::INVALID_ARGUMENT_MSG;
        }

        parent::__construct($message, Status::INVALID_ARGUMENT);
    }
}
