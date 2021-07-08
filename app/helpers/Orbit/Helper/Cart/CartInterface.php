<?php

namespace Orbit\Helper\Cart;

interface CartInterface
{
    public function addItem($request);

    public function updateItem($itemId, $updateData);

    public function removeItem($itemId = []);
}
