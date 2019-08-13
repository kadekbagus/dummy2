<?php namespace Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Validators;

use Pulsa;

class PulsaExistsValidator
{

    private function __invoke($attribute, $value, $parameters, $validators)
    {
        $data = $validators->getData();
        $valid = true;
        if ($data['object_type'] === 'pulsa') {
            $pulsa = Pulsa::where('pulsa_item_id', $value)->active()->first();
            $valid = !empty($pulsa);
        }
        return $valid;
    }

}
