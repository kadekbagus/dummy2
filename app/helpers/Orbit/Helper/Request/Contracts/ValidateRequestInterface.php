<?php namespace Orbit\Helper\Request\Contracts;

/**
 * Interface for any class that implement request validation.
 *
 *
 * @author Budi <budi@gotomalls.com>
 */
interface ValidateRequestInterface
{
    /**
     * Authenticate user.
     * @param  [type] $controller [description]
     * @return [type]             [description]
     */
    public function auth($controller = null);

    /**
     * Get data being validated.
     * @return [type] [description]
     */
    public function getData();

    /**
     * Get validation error message.
     * @return [type] [description]
     */
    public function getValidationErrorMessage();

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
