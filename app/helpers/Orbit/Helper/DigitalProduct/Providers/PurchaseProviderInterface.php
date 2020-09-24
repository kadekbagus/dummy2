<?php namespace Orbit\Helper\DigitalProduct\Providers;

interface PurchaseProviderInterface {

    public function purchase($purchaseData = []);

    public function confirm($params = []);

    public function status($requestParam = []);
}
