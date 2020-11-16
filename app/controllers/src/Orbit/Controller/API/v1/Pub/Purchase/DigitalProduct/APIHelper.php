<?php namespace Orbit\Controller\API\v1\Pub\Purchase\DigitalProduct;

use PaymentTransaction;

trait APIHelper
{
    use UPointHelper,
        WoodoosHelper;

    protected function buildAPIParams(PaymentTransaction $purchase)
    {
        $params = [];

        if ($purchase->forUPoint()) {
            $params = $this->buildUPointParams($purchase);
        }
        else if ($purchase->forWoodoos()) {
            $params = $this->buildWoodoosParams($purchase);
        }
        // else if ($purchase->forMCashElectricity()) {
        //     $params = $this->buildMCashParams($purchase);
        // }

        return $params;
    }
}
