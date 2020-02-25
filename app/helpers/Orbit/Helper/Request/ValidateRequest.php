<?php namespace Orbit\Helper\Request;

use App;
use DominoPOS\OrbitACL\ACL;
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
    protected $roles = [];

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

    public function __construct()
    {
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
    }

    /**
     * Determine if this request need authorization.
     *
     * @return bool
     */
    protected function needAuthorization()
    {
        return $this->authRequest && ! empty($this->roles);
    }

    /**
     * Check if User is authorized for this request
     * based on their role.
     *
     * @return self|Illuminate\Http\Response on exception
     */
    protected function authorize()
    {
        if ($this->needAuthorization()) {
            $userRole = $this->user->role->role_name;
            if (! in_array(strtolower($userRole), $this->roles)) {
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
     * Proxy request input so it is accessible directly from $this.
     *
     * @param  [type] $property [description]
     * @return [type]           [description]
     */
    public function __get($property)
    {
        return Request::input($property);
    }
}
