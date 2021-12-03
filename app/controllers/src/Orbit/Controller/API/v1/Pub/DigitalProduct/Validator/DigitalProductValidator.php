<?php

namespace Orbit\Controller\API\v1\Pub\DigitalProduct\Validator;

use App;
use DigitalProduct;
use ProviderProduct;

/**
 * List of custom validator related to Digital Product
 *
 * @author Budi <budi@gotomalls.com>
 */
class DigitalProductValidator
{
    /**
     * Validate that DigitalProduct with id $digitalProductId is exists.
     *
     * @param  [type] $attributes       [description]
     * @param  [type] $digitalProductId [description]
     * @param  [type] $parameters       [description]
     * @param  [type] $validator        [description]
     * @return [type]                   [description]
     */
    public function exists($attributes, $digitalProductId, $parameters)
    {
        $digitalProduct = DigitalProduct::where(
            'digital_product_id',
            $digitalProductId
        )->first();

        App::instance('digitalProduct', $digitalProduct);

        return ! empty($digitalProduct);
    }

    public function existsWithGame($attributes, $gameSlugOrId, $parameters)
    {
        if (null === App::make('digitalProduct')) {
            return false;
        }

        return DigitalProduct::whereHas(
            'games',
            function($gameQuery) use ($gameSlugOrId)
            {
                $gameQuery->active()->where(
                    function($query) use ($gameSlugOrId)
                    {
                        $query->where('games.slug', $gameSlugOrId)
                            ->orWhere('games.game_id', $gameSlugOrId);
                    }
                );
            }
        )->available()->first() !== null;
    }

    /**
     * Validate that DigitalProduct is available for purchase.
     *
     * @param  [type] $attributes       [description]
     * @param  [type] $digitalProductId [description]
     * @param  [type] $parameters       [description]
     * @return [type]                   [description]
     */
    public function available($attributes, $digitalProductId, $parameters)
    {
        $digitalProduct = App::make('digitalProduct');

        if (empty($digitalProduct)) {
            return false;
        }

        return $digitalProduct->status === 'active'
            && $digitalProduct->is_displayed === 'yes';
    }

    /**
     * Validate that Provider Product with $providerProductId exists.
     *
     * @param  [type] $attributes        [description]
     * @param  [type] $providerProductId [description]
     * @param  [type] $parameters        [description]
     * @return [type]                    [description]
     */
    public function providerProductExists($attributes, $value, $parameters)
    {
        $digitalProduct = App::make('digitalProduct');

        if (empty ($digitalProduct)) {
            return false;
        }

        $providerProduct = ProviderProduct::where(
            'provider_product_id',
            $digitalProduct->selected_provider_product_id
        )->first();

        App::instance('providerProduct', $providerProduct);

        return ! empty($providerProduct);
    }

    public function existsWithProviderProduct($attr, $value, $params)
    {
        return $this->exists($attr, $value, $params)
            && $this->providerProductExists($attr, $value, $params);
    }

    /**
     * Determine if given provider product id exists in database.
     *
     * @param  [type] $attributes [description]
     * @param  [type] $id         [description]
     * @param  [type] $parameters [description]
     * @return [type]             [description]
     */
    public function existsIfChanged($attributes, $id, $parameters)
    {
        $providerProduct = ProviderProduct::where('provider_product_id', $id)
            ->first();

        App::instance('providerProduct', $providerProduct);

        return ! empty($providerProduct);
    }
}
