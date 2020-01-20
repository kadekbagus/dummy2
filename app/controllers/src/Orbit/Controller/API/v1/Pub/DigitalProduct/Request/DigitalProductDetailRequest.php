<?php namespace Orbit\Controller\API\v1\Pub\DigitalProduct\Request;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Validator;
use Orbit\Controller\API\v1\Pub\DigitalProduct\Repository\DigitalProductRepository;
use Orbit\Helper\Request\ValidateRequest;

/**
 * Digital Product Detail Request
 *
 * @todo  find a way to properly inject current user into request (might be a service)
 * @author Budi <budi@gotomalls.com>
 */
class DigitalProductDetailRequest extends ValidateRequest
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
            'product_id' => 'required|product_exists',
            'game_slug' => 'required|product_with_game_exists',
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
            'product_exists' => 'PRODUCT_DOES_NOT_EXISTS',
            'product_with_game_exists' => 'PRODUCT_FOR_GAME_DOES_NOT_EXISTS',
        ];
    }

    protected function registerCustomValidations()
    {
        Validator::extend('product_exists', 'Orbit\Controller\API\v1\Pub\DigitalProduct\Validator\DigitalProductValidator@exists');

        Validator::extend('product_with_game_exists', 'Orbit\Controller\API\v1\Pub\DigitalProduct\Validator\DigitalProductValidator@existsWithGame');
    }
}
