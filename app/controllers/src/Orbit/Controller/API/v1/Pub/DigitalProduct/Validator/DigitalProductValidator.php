<?php namespace Orbit\Controller\API\v1\Pub\DigitalProduct\Validator;

use App;
use DigitalProduct;

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
        $digitalProduct = DigitalProduct::where('digital_product_id', $digitalProductId)->first();

        App::instance('digitalProduct', $digitalProduct);

        return ! empty($digitalProduct);
    }

    public function existsWithGame($attributes, $gameSlug, $parameters)
    {
        if (null === App::make('digitalProduct')) {
            return false;
        }

        return null !== DigitalProduct::whereHas('games', function($gameQuery) use ($gameSlug) {
            $gameQuery->where('games.slug', $gameSlug);
        })->first();
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
}
