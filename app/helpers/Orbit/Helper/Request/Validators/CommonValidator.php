<?php

namespace Orbit\Helper\Request\Validators;

/**
 * Common validators.
 *
 * @author Budi <budi@gotomalls.com>
 */
class CommonValidator
{
    /**
     * Trim request input.
     * Supported params are newline and strip_tags.
     *
     * @todo Support replacing request input with the trimmed version?
     *
     * @param  [type] $attribute  [description]
     * @param  [type] $value      [description]
     * @param  [type] $parameters [description]
     * @param  [type] $validator  [description]
     * @return [type]             [description]
     */
    public function trimInput($attribute, $value, $parameters, $validator)
    {
        $data = $validator->getData();
        $value = trim($value);

        if (in_array('newline', $parameters)) {
            $value = str_replace(["\r", "\n"], '', $value);
        }

        if (in_array('strip_tags', $parameters)) {
            $value = strip_tags($value);
        }

        $validator->setData(array_merge($data, [$attribute => $value]));

        return true;
    }
}
