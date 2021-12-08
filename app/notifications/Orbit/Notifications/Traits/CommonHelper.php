<?php

namespace Orbit\Notifications\Traits;

trait CommonHelper
{
    protected function formatCurrency($amount, $currency)
    {
        return trim($currency . ' ' . number_format($amount, 0, ',', '.'));
    }
}
