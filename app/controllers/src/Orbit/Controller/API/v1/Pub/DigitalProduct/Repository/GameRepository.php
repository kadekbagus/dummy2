<?php namespace Orbit\Controller\API\v1\Pub\DigitalProduct\Repository;

use DB;
use Game;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Orbit\Controller\API\v1\Pub\DigitalProduct\Helper\MediaQuery;
use Orbit\Controller\API\v1\Pub\DigitalProduct\Resource\GameCollection;
use Orbit\Controller\API\v1\Pub\DigitalProduct\Resource\GameResource;

/**
 * Game repository.
 *
 * @author Budi <budi@gotomalls.com>
 */
class GameRepository
{
    use MediaQuery;

    public function __construct()
    {
        $this->setupImageUrlQuery();
    }

    /**
     * Get collection based on requested filter.
     *
     * @return [type] [description]
     */
    public function findGames()
    {
        $skip = OrbitInput::get('skip', 0);
        $take = OrbitInput::get('take', 10);
        $sortBy = OrbitInput::get('sortby', 'game_name');
        $sortMode = OrbitInput::get('sortmode', 'asc');
        $imageVariants = $this->resolveImageVariants('game_image_', 'mobile_thumb');

        $games = Game::with(['media' => function($query) use ($imageVariants) {
            $query->select(
                'object_id',
                'media_name_long',
                DB::raw($this->imageQuery)
            );

            if (! empty($imageVariants)) {
                $query->whereIn('media_name_long', $imageVariants);
            }
        }])->active()

        //OM-5547, game listing is order by aphabetical name
        ->orderBy($sortBy, $sortMode);

        $total = clone $games;
        $total = $total->count();
        $games = $games->skip($skip)->take($take)->get();
        $games = new GameCollection($games, $total);

        // Call the transform/array method via __invoke,
        // which is a shorthand for $games->toArray();
        return $games();

        // Can also return instance of ResourceInterface (GameCollection)
        // as it will run toArray() when rendering the response via render()
        // return new GameCollection($games, $total);
    }

    /**
     * Find a Game based on the slug.
     *
     * @param  [type] $gameSlug [description]
     * @return [type]           [description]
     */
    public function findGame($gameSlug = null)
    {
        $gameSlug = $gameSlug ?: OrbitInput::get('slug');
        $imageVariants = $this->resolveImageVariants('game_image_', 'mobile_medium');

        $game = Game::with([
            'digital_products' => function($query) {
                $query->select(
                    'digital_products.digital_product_id',
                    'product_type',
                    'selected_provider_product_id',
                    'code', 'product_name', 'selling_price',
                    'digital_products.status', 'is_displayed', 'is_promo',
                    'description',
                    'notes',
                    'extra_field_metadata'
                )->displayed();

                // Isn't it better to just put provider name on digital_products?
                // $query->with(['provider_product' => function($providerProductQuery) {
                //     $providerProductQuery->select(
                //         'provider_product_id', 'provider_name'
                //     )->active();
                // }]);
            },
            'media' => function($query) use ($imageVariants) {
                $query->select(
                    'object_id',
                    'media_name_long',
                    DB::raw($this->imageQuery)
                );

                if (! empty($imageVariants)) {
                    $query->whereIn('media_name_long', $imageVariants);
                }
            }
        ])
        ->active()
        ->where('slug', $gameSlug)
        ->first();

        $game = new GameResource($game);

        // call the transform/toArray method via __invoke()
        return $game();
    }

    private function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }

}
