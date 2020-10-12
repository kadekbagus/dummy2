<?php

namespace Orbit\Helper\Loggable;

use Log;

/**
 * A simple helper that wrap logging based on $shouldLog value.
 * Useful when the log condition is not based on global config [app.debug],
 * and especially when we want to do the same log if the same condition is met
 * on multiple places in the class.
 *
 * @author Budi <budi@gotomalls.com>
 */
trait Loggable
{
    protected $shouldLog = false;

    protected function shouldLogAction()
    {
        return $this->shouldLog;
    }

    protected function log($message, $type = 'info')
    {
        if ($this->shouldLogAction()) {
            Log::{$type}($message);
        }
    }
}