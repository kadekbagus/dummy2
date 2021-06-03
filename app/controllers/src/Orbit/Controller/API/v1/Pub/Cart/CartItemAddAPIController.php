<?php

namespace Orbit\Controller\API\v1\Pub\Cart;

use Orbit\Helper\Cart\Cart;

/**
 * Add an item into cart.
 *
 * @author Budi <budi@gotomalls.com>
 */
class CartAddItemAPIController extends PubControllerAPI
{
    public function handle(AddItemRequest $request)
    {
        try {

            $this->response->data = new CartItemResource(
                Cart::addItem($request)
            );

        } catch (Exception $e) {
            return $this->handleException($e);
        }

        return $this->render();
    }
}
