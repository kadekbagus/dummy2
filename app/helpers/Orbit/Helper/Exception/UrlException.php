<?php
namespace Orbit\Helper\Exception;
/**
 * Exception class for Url Checker.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
class UrlException extends \Exception {
    protected $redirectRoute;

    public function __construct($redirectRoute = 'ci-home', $message, $code = 0)
    {
        $this->redirectRoute = $redirectRoute;

        parent::__construct($message, $code);
    }

    public function getRedirectRoute()
    {
        return $this->redirectRoute;
    }
}
