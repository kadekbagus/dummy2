<?php namespace Orbit\Helper\Request\Contracts;

/**
 * Form Request Interface.
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
    public function validate(array $data = [], array $rules = [], array $messages = []);
}
