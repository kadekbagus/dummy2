<?php namespace Orbit\Controller\API\v1\Pub\DigitalProduct\Request;

use Orbit\Helper\Request\ValidateRequest;
use Validator;

/**
 * Game detail Request
 *
 * @author Budi <budi@gotomalls.com>
 */
class GameDetailRequest extends ValidateRequest
{
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
