<?php namespace Orbit\Helper\Request\Validators;

use Mall;

/**
 * Mall exists validator.
 *
 * @author Budi <budi@gotomalls.com>
 */
class MallExistsValidator
{
    function validate($attributes, $mallId, $parameters, $validator)
    {
        return Mall::excludeDeleted()->select('merchant_id')
            ->active()->where('merchant_id', $mallId)->first() !== null;
    }
}
