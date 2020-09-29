<?php namespace Orbit\Controller\API\v1\Pub\Purchase\DigitalProduct;

use Log;

trait APIHelper
{
    use UPointHelper,
        WoodoosHelper;

    protected function buildAPIParams($purchase)
    {
        Log::info("Building api params for queue data...");

        $params = [];

        if ($purchase->forUPoint()) {
            $params = $this->buildUPointParams($purchase);
        }
        else if ($purchase->forWoodoos()) {
            $params = $this->buildWoodoosParams($purchase);
        }

        Log::info(serialize($params));

        return $params;
    }
}
