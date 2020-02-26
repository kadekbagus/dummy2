<?php

namespace Orbit\Controller\API\v1\Pub\DigitalProduct\Repository;

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

        return Game::with($this->buildMediaQuery())
            ->active()
            //OM-5547, game listing is order by aphabetical name
            ->orderBy($sortBy, $sortMode);
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
                            'product_type', 'selected_provider_product_id',
                            'code', 'product_name', 'selling_price',
                            'digital_products.status', 'is_displayed', 'is_promo',
                            'description', 'notes', 'extra_field_metadata'
                        )->displayed();
                    }
                ],
                $this->buildMediaQuery()
            ))
            ->active()
            ->where('slug', $gameSlug)
            ->firstOrFail();
    }
}
