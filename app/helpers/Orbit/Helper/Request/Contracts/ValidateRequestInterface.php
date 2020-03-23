<?php

namespace Orbit\Helper\Request\Contracts;

/**
 * Interface for any class that implement request validation.
 *
 * @author Budi <budi@gotomalls.com>
 */
interface ValidateRequestInterface
{
    /**
     * Validate form request.
     *
     * @param  array  $data     [description]
     * @param  array  $rules    [description]
     * @param  array  $messages [description]
     * @return [type]           [description]
     */
    public function validate($data = [], $rules = [], $messages = []);
}
