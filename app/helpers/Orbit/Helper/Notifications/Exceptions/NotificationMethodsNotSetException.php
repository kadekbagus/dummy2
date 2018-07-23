<?php namespace Orbit\Helper\Notifications\Exceptions;

/**
 * Exception if notification method is not set by user.
 *
 * @author Budi <budi@dominopos.com>
 */
class NotificationMethodsNotSetException extends \Exception
{
    public function getMessage()
    {
        return "Please set at least 1 method for notification!";
    }

}
