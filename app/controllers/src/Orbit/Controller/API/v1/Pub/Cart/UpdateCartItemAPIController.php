<?php

namespace Orbit\Controller\API\v1\Pub\Cart;

use Exception;
use OrbitShop\API\v1\PubControllerAPI;
use Orbit\Controller\API\v1\Pub\Cart\Request\UpdateCartItemRequest;
use Orbit\Helper\Cart\CartInterface as Cart;

/**
 * Update an item in the Cart.
 *
 * @author Budi <budi@gotomalls.com>
 */
class UpdateCartItemAPIController extends PubControllerAPI
{
    public function handle(UpdateCartItemRequest $request, Cart $cart)
    {
        try {
            // $this->enableQueryLog();

            $this->response->data = $cart->updateItem(
                $request->cart_item_id, $request
            );

        } catch (Exception $e) {
            return $this->handleException($e);
        }

        return $this->render();
    }
}
