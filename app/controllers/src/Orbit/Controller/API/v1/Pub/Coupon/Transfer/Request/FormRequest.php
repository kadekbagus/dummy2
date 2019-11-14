<?php namespace Orbit\Controller\API\v1\Pub\Coupon\Transfer\Request;

use OrbitShop\API\v1\OrbitShopAPI;
use Request;
use Validator;

/**
 * Base Form Request class.
 *
 * @todo create proper form request helper.
 * @author Budi <budi@gotomalls.com>
 */
class FormRequest implements FormRequestInterface
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
     * @todo implement bail-on-first-validation-error rule.
     * @var boolean
     */
    protected $bail = false;

    /**
     * Authenticate and authorize request.
     *
     * @param  [type] $controller [description]
     * @return self
     */
    public function auth($controller)
    {
        if (! empty($this->roles)) {
            $controller->authorize($this->roles);
        }
        else {
            $controller->checkAuth();
        }

        $this->setUser($controller->user);

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
     * Validate form request.
     *
     * @param  array  $data     [description]
     * @param  array  $rules    [description]
     * @param  array  $messages [description]
     * @return [type]           [description]
     */
    public function validate(array $data = [], array $rules = [], array $messages = [])
    {
        if (method_exists($this, 'registerCustomValidations')) {
            $this->registerCustomValidations();
        }

        $validator = Validator::make(
            array_merge(Request::all(), $data),
            array_merge($this->rules(), $rules),
            array_merge($this->messages(), $messages)
        );

        if ($validator->fails()) {
            $errorMessage = $validator->messages()->first();
            OrbitShopAPI::throwInvalidArgument($errorMessage);
        }
    }
}
