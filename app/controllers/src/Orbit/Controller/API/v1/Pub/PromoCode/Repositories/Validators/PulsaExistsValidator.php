<?php namespace Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Validators;

use Pulsa;

class PulsaExistsValidator extends AbstractValidator
{

    //actual validator. Validator::extend() cannot work with invokable
    //class eventhough can accept anonymous function, so we need to use
    public function validate($attribute, $value, $parameters, $validators)
    {
        $data = $validators->getData();
        $valid = true;
        if ($data['object_type'] === 'pulsa') {
            $pulsa = Pulsa::where('pulsa_item_id', $value)->active()->first();
            $valid = !empty($pulsa);
        }
        return $valid;
    }
    public function __invoke($attribute, $value, $parameters, $validators)
    {
        return $this->validate($attribute, $value, $parameters, $validators);
    }

}
