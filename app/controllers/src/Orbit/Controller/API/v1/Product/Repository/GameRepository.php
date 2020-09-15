<?php

namespace Orbit\Controller\API\v1\Product\Repository;

use DB;
use Game;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Orbit\Controller\API\v1\Pub\DigitalProduct\Helper\MediaQuery;

/**
 * Game repository.
 *
 * @author Budi <budi@gotomalls.com>
 */
class GameRepository
{
    use MediaQuery;

    protected $imagePrefix = 'game_image_';

    public function __construct()
    {
        $this->setupImageUrlQuery();
    }

    /**
     * Get collection based on requested filter.
     *
     * @return Illuminate\Database\Eloquent\Builder
     */
    public function findGames()
    {
        $sortBy = OrbitInput::get('sortby', 'game_name');
        $sortMode = OrbitInput::get('sortmode', 'asc');
        $gameName = OrbitInput::get('game_name');

        $games = Game::with($this->buildMediaQuery())
            ->active()
            //OM-5547, game listing is order by aphabetical name
            ->orderBy($sortBy, $sortMode);

        if (! empty($gameName)) {
            $games->where('game_name', 'like', "%{$gameName}%");
        }

        return $games;
    }

    /**
     * Find a Game based on the slug.
     *
     * @param  string $gameSlug the game slug
     * @return Illuminate\Database\Eloquent\Builder
     */
    public function findGame($gameSlug)
    {
        return Game::with(array_merge(
                [
                    'digital_products' => function($query) {
                        $query->select(
                            'digital_products.digital_product_id',
                            'digital_products.product_type', 'digital_products.selected_provider_product_id',
                            'digital_products.code', 'digital_products.product_name', 'digital_products.selling_price',
                            'digital_products.status', 'digital_products.is_displayed', 'digital_products.is_promo',
                            'digital_products.description', 'digital_products.notes', 'provider_products.extra_field_metadata'
                        )->leftJoin('provider_products', 'provider_products.provider_product_id', '=', 'digital_products.selected_provider_product_id')->displayed();
                    }
                ],
                $this->buildMediaQuery()
            ))
            ->active()
            ->where('slug', $gameSlug)
            ->firstOrFail();
    }
}
