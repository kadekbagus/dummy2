<?php

namespace Orbit\Helper\DataBuilder;

/**
 * Base data builder class.
 *
 * @author Budi <budi@gotomalls.com>
 */
abstract class DataBuilder
{
    protected $request;

    public function __construct($request = null)
    {
        $this->setRequest($request);
    }

    public function setRequest($request = null)
    {
        if (! empty($request)) {
            $this->request = $request;
        }

        return $this;
    }

    abstract public function build();

    public function __get($key)
    {
        return $this->{$key};
    }
}
