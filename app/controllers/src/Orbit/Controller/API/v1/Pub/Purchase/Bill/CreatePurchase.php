<?php

namespace Orbit\Controller\API\v1\Pub\Purchase\Bill;

use Orbit\Controller\API\v1\Pub\Purchase\BaseCreatePurchase;
use Setting;

/**
 * Brand Product Order Purchase
 *
 * @author Budi <budi@gotomalls.com>
 */
class CreatePurchase extends BaseCreatePurchase
{
    protected function buildPurchaseData()
    {
        return array_merge(parent::buildPurchaseData(), [
            'user_email' => $this->user->user_email,
            'user_name' => $this->user->getFullName(),
            'currency' => $this->request->currency
                ? $this->request->currency
                : 'IDR',
        ]);
    }

    protected function buildPurchaseDetailData()
    {
        $mdr = Setting::select('setting_value')
            ->where('setting_name', 'gtm_mdr_value')
            ->active()
            ->first();

        return array_merge(parent::buildPurchaseDetailData(), [
            'object_name' => $this->item->product_name,
            'mdr_percentage' => empty($mdr) ? 0 : $mdr->setting_value,
        ]);
    }

    protected function getTotalAmount()
    {
        return 0; // update later after getting billing information
    }

    protected function getItemPrice()
    {
        return 0;
    }

    protected function getVendorPrice()
    {
        return 0;
    }

    protected function getExtraData($request)
    {
        return $request->bill_id;
    }
}
