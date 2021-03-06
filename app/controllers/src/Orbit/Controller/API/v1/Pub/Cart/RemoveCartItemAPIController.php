<?php

namespace Orbit\Controller\API\v1\Pub\Cart;

use Exception;
use Illuminate\Support\Facades\DB;
use OrbitShop\API\v1\PubControllerAPI;
use Orbit\Controller\API\v1\Pub\Cart\Request\RemoveCartItemRequest;
use Orbit\Helper\Cart\CartInterface as Cart;

/**
 * Remove item(s) from the Cart.
 *
 * @author Budi <budi@gotomalls.com>
 */
class RemoveCartItemAPIController extends PubControllerAPI
{
    public function handle(RemoveCartItemRequest $request, Cart $cart)
    {
        try {
            // $this->enableQueryLog();
            DB::beginTransaction();

            $this->response->data = $cart->removeItem(
                $request->cart_item_id
            );

            DB::commit();

        } catch (Exception $e) {
            return $this->handleException($e);
        }

        return $this->render();
    }
}
