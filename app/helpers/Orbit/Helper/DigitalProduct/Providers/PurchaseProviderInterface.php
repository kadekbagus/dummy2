<?php namespace Orbit\Helper\DigitalProduct\Providers;

interface PurchaseProviderInterface {

    public function purchase($purchaseData = []);

    public function status($requestParam = []);
}
