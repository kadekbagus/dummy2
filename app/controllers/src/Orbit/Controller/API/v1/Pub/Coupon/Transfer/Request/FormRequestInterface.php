<?php namespace Orbit\Controller\API\v1\Pub\Coupon\Transfer\Request;

/**
 * Base Form Request.
 *
 * @todo  create proper form request helper.
 * @author Budi <budi@dominopos.com>
 */
interface FormRequestInterface
{
    /**
     * Validate form request.
     *
     * @param  array  $data     [description]
     * @param  array  $rules    [description]
     * @param  array  $messages [description]
     * @return [type]           [description]
     */
    public function validate(array $data = [], array $rules = [], array $messages = []);
}
