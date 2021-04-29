<?php

namespace Orbit\Controller\API\v1\Pub\Cart;

class CartAddItemAPIController extends PubControllerAPI
{
    public function handle(AddItemRequest $request)
    {
        try {

            $this->response->data = new CartItemResource(
                Cart::add(
                    $request->user(),
                    App::make('brandProductVariant'),
                    $request->quantity,
                    $request->pickup_location
                )
            );

        } catch (Exception $e) {
            return $this->handleException($e);
        }

        return $this->render();
    }
}
