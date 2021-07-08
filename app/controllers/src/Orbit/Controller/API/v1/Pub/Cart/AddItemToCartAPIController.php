<?php

namespace Orbit\Controller\API\v1\Pub\Cart;

use Exception;
use OrbitShop\API\v1\PubControllerAPI;
use Orbit\Controller\API\v1\Pub\Cart\Request\AddItemToCartRequest;
use Orbit\Helper\Cart\CartInterface as Cart;

/**
 * Add an item into cart.
 *
 * @author Budi <budi@gotomalls.com>
 */
class AddItemToCartAPIController extends PubControllerAPI
{
    public function handle(AddItemToCartRequest $request, Cart $cart)
    {
        try {

            // $this->enableQueryLog();

            $this->response->data = $cart->addItem($request);

        } catch (Exception $e) {
            return $this->handleException($e);
        }

        return $this->render();
    }
}
