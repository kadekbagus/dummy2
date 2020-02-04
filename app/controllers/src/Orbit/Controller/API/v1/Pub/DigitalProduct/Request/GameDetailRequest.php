<?php namespace Orbit\Controller\API\v1\Pub\DigitalProduct\Request;

use Validator;
use Orbit\Helper\Request\ValidateRequest;

/**
 * Game detail Request
 *
 * @todo  find a way to properly inject current user into request (might be a service)
 * @author Budi <budi@gotomalls.com>
 */
class GameDetailRequest extends ValidateRequest
{
    /**
     * Allowed roles to access this request.
     * @var array
     */
    protected $roles = ['guest', 'consumer'];

    /**
     * Get validation rules.
     *
     * @return array array of rules for this request.
     */
    public function rules()
    {
        return [
            'slug' => 'required|game_exists',
            'image_variant' => 'sometimes|required',
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'game_exists' => 'GAME_NOT_FOUND',
        ];
    }

    protected function registerCustomValidations()
    {
        Validator::extend('game_exists', 'Orbit\Controller\API\v1\Pub\DigitalProduct\Validator\GameValidator@exists');
    }
}
