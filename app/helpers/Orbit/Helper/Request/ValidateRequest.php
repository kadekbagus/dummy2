<?php

namespace Orbit\Helper\Request;

use App;
use DominoPOS\OrbitACL\ACL;
use OrbitShop\API\v1\OrbitShopAPI;
use Orbit\Helper\Request\Contracts\RequestWithUpload;
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
     * @var Model
     */
    protected $user;

    /**
     * Allowed User's roles to access this request.
     * @var array
     */
    protected $roles = [];

    /**
     * Allowed User's status to access current request. Empty means granting
     * access to User with any status.
     * @var array
     */
    protected $userStatus = [];

    /**
     * @todo implement bail-on-first-validation-error rule.
     * @var bool
     */
    protected $bail = false;

    /**
     * Indicate that request must be authenticated.
     * @var bool
     */
    protected $authRequest = true;

    /**
     * Validator instance.
     * @var Validator
     */
    protected $validator = null;

    public function __construct()
    {
        $this->loadPaginationConfig();

        // New behaviour:
        // Current User instance should be available on container,
        // because we **should** do authentication/user fetching
        // on the intermediate controller beforeFilter() hooks.
        $this->user = App::make('currentUser');

        // Check if user authorized to make this request.
        $this->authorize();

        // Optionally, we can call validate() directly,
        // so we don't have to call it on each controller@method.
        $this->validate();

        // Handle uploaded files if we implement RequestWithUpload interface.
        if ($this instanceof RequestWithUpload) {
            $this->handleUpload();
        }
    }

    /**
     * Load pagination config if property is defined.
     *
     * @return array
     */
    protected function loadPaginationConfig()
    {
        if (isset($this->pagination)) {
            $this->pagination = ! empty($this->pagination)
                ? Config::get($this->pagination)
                : ['max_record' => 100, 'per_page' => 10];
        }
    }

    /**
     * Get the max take value for listing request.
     *
     * @return int $take the requested (or max) take value.
     */
    public function getTake()
    {
        if (! isset($this->pagination)) {
            return $this->take ?: 20;
        }

        $take = $this->take ?: $this->pagination['per_page'];

        if ($take > $this->pagination['max_record']) {
            $take = $this->pagination['max_record'];
        }

        return $take;
    }

    /**
     * Determine if this request need authorization.
     *
     * @return bool
     */
    protected function needAuthorization()
    {
        return $this->authRequest
            && (! empty($this->roles) || ! empty($this->userStatus));
    }

    /**
     * Check if User is authorized for this request
     * based on their role and status.
     *
     * @throws ACLForbiddenException on failed authorization
     * @return self
     */
    protected function authorize()
    {
        if ($this->needAuthorization()) {

            // If User role not allowed, then throw fails.
            if (! empty($this->roles) && ! $this->user->roleIs($this->roles)) {
                return $this->handleAuthorizationFails();
            }

            // If User role allowed, then check for user status.
            // Some request like Rating/Review require user status 'active'
            // to proceed.
            if (
                ! empty($this->userStatus)
                && ! $this->user->statusIs($this->userStatus)
            ) {
                return $this->handleAuthorizationFails();
            }
        }

        return $this;
    }

    /**
     * Set user.
     * @param object $user
     * @return  self
     */
    public function setUser($user)
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
     * Default implementation, just return empty message.
     *
     * @return array
     */
    public function messages()
    {
        return [];
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
     * Handle failed authorization.
     *
     * @param  string $message [description]
     * @return [type]          [description]
     */
    protected function handleAuthorizationFails()
    {
        ACL::throwAccessForbidden();
    }

    /**
     * Get the validation data.
     * @return [type] [description]
     */
    protected function validationData()
    {
        return Request::all();
    }

    /**
     * An after validation hook.
     */
    protected function afterValidation()
    {
        // Do nothing.
    }

    /**
     * Validate form request.
     *
     * @param  array  $data     [description]
     * @param  array  $rules    [description]
     * @param  array  $messages [description]
     * @return self
     */
    public function validate($data = [], $rules = [], $messages = [])
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

        // Run an after validation hook. Can be overridden when needed.
        $this->afterValidation();
    }

    /**
     * Extend default Request::has ability with a callback that will be run
     * if request has the given $key.
     *
     * @param  string  $key      the key
     * @param  Closure $callback the callback that will be run
     */
    public function has($key, $callback)
    {
        if (Request::has($key)) {
            $callback(Request::input($key));
        }
    }

    /**
     * Proxy Request input so it is accessible directly from $this.
     *
     * @param  string $key request property.
     * @return mixed
     */
    public function __get($key)
    {
        return Request::input($key);
    }

    /**
     * Proxy Request methods so it is accessible from $this.
     *
     * @param  string $method
     * @param  mixed $args
     * @return mixed
     */
    public function __call($method, $args)
    {
        return Request::{$method}($args);
    }
}
