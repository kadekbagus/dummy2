<?php namespace Orbit\Helper\Request;

use OrbitShop\API\v1\OrbitShopAPI;
use Orbit\Helper\Request\Contracts\ValidateRequestInterface;
use Request;
use Validator;

/**
 * Base Request Validation class.
 *
 * @author Budi <budi@gotomalls.com>
 */
class ValidateRequest implements ValidateRequestInterface
{
    /**
     * Current authenticated User instance.
     * @var [type]
     */
    protected $user;

    /**
     * Allowed roles to access this request.
     * @var array
     */
    protected $roles = ['consumer'];

    /**
     * controller which handle the request (and has auth capability).
     * @var [type]
     */
    protected $controller;

    /**
     * @todo implement bail-on-first-validation-error rule.
     * @var boolean
     */
    protected $bail = false;

    /**
     * Indicate that request must be authenticated.
     * @var boolean
     */
    protected $authRequest = true;

    /**
     * Validator instance.
     * @var null
     */
    protected $validator = null;

    public function __construct($controller = null)
    {
        $this->controller = $controller;

        if ($this->authRequest) {
            $this->auth();
        }
    }

    /**
     * Authenticate and authorize request.
     *
     * @param  [type] $controller [description]
     * @return self
     */
    public function auth($controller = null)
    {
        if (! empty($controller)) {
            $this->controller = $controller;
        }

        if (! empty($this->roles)) {
            $this->controller->authorize($this->roles);
        }
        else {
            $this->controller->checkAuth();
        }

        $this->setUser($this->controller->api->user);

        return $this;
    }

    /**
     * Set user.
     * @param object $user
     * @return  self
     */
    private function setUser($user)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get user.
     * @return User
     */
    public function user()
    {
        return $this->user;
    }

    /**
     * Get validation data.
     * @return array
     */
    public function getData()
    {
        return $this->validator->getData();
    }

    /**
     * Get the validation error message.
     *
     * Can be overriden when we need custom message
     * based on custom validation rule.
     *
     * @return string
     */
    public function getValidationErrorMessage()
    {
        return $this->validator->messages()->first();
    }

    /**
     * Do something when there's validation error.
     *
     * @return [type] [description]
     */
    protected function handleValidationFails()
    {
        OrbitShopAPI::throwInvalidArgument($this->getValidationErrorMessage());
    }

    /**
     * Get the validation data.
     * @return [type] [description]
     */
    public function validationData()
    {
        return Request::all();
    }

    /**
     * Validate form request.
     *
     * @param  array  $data     [description]
     * @param  array  $rules    [description]
     * @param  array  $messages [description]
     * @return self
     */
    public function validate(array $data = [], array $rules = [], array $messages = [])
    {
        // In case we have custom validation rules, register
        // in inside following method.
        if (method_exists($this, 'registerCustomValidations')) {
            $this->registerCustomValidations();
        }

        // Make validator with request data, rules, and custom messages.
        $this->validator = Validator::make(
            array_merge($this->validationData(), $data),
            array_merge($this->rules(), $rules),
            array_merge($this->messages(), $messages)
        );

        // If validation fails, then throw exception so controller
        // can handle it properly.
        if ($this->validator->fails()) {
            $this->handleValidationFails();
        }
    }

    /**
     * Try to get item from request param.
     *
     * @param  [type] $property [description]
     * @return [type]           [description]
     */
    public function __get($property)
    {
        return Request::input($property);
    }
}
