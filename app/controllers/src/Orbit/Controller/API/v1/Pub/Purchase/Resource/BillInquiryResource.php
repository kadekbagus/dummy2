<?php

namespace Orbit\Controller\API\v1\Pub\Purchase\Resource;

use Orbit\Helper\Resource\Resource;

/**
 * Bill inquiry purchase resource mapper.
 *
 * @author Budi <budi@gotomalls.com>
 */
class BillInquiryResource extends Resource
{
    public function toArray()
    {
        $this->resource->bill_information = $this->resource->getBillInformation();

        return $this->resource;
    }
}
