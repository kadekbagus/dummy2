<?php namespace Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Validators;


abstract class AbstractValidator
{
    //actual validator. Validator::extend() cannot work with invokable
    //class eventhough can accept anonymous function, so we need to use
    abstract public function validate($attribute, $value, $parameters, $validators);

    public function __invoke($attribute, $value, $parameters, $validators)
    {
        return $this->validate($attribute, $value, $parameters, $validators);
    }
}
