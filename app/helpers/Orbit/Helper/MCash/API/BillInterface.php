<?php

namespace Orbit\Helper\MCash\API;

/**
 * Provide a set of feature needed by bill service.
 *
 * @author Budi <budi@gotomalls.com>
 */
interface BillInterface
{
    /**
     * Inquiry bill information from MCash.
     * @param  array  $params [description]
     * @return [type]         [description]
     */
    public function inquiry($params = []);

    /**
     * Pay the bill.
     *
     * @param  array  $params [description]
     * @return [type]         [description]
     */
    public function pay($params = []);
}
