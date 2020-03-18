<?php namespace Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Validators;

use DigitalProduct;

class GameVoucherExistsValidator extends AbstractValidator
{

    //actual validator. Validator::extend() cannot work with invokable
    //class eventhough can accept anonymous function, so we need to use
    public function validate($attribute, $value, $parameters, $validators)
    {
        $data = $validators->getData();
        $valid = true;
        if ($data['object_type'] === 'game_voucher') {
            $gameVoucher = DigitalProduct::where('digital_product_id', $value)->active()->first();
            $valid = !empty($gameVoucher);
        }
        return $valid;
    }
    public function __invoke($attribute, $value, $parameters, $validators)
    {
        return $this->validate($attribute, $value, $parameters, $validators);
    }

}
