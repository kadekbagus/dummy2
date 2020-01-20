<?php namespace Orbit\Controller\API\v1\Product\DigitalProduct\Resource;

use DigitalProduct;
use Orbit\Helper\Resource\ResourceAbstract as Resource;

/**
 * Single Digital Product resource.
 *
 * @author Budi <budi@gotomalls.com>
 */
class DigitalProductResource extends Resource
{
    private $resource = null;

    public function __construct($resource)
    {
        $this->resource = $resource;
    }

    /**
     * Transform Digital Product object into response data array.
     *
     * @return [type] [description]
     */
    public function toArray()
    {
        if ( empty($this->resource)) {
            return [];
        }

        return [
            'id' => $this->resource->digital_product_id,
            'type' => $this->resource->product_type,
            'name' => $this->resource->product_name,
            'code' => $this->resource->code,
            'price' => $this->resource->selling_price,
            'provider_id' => $this->resource->selected_provider_product_id,
            'provider_name' => $this->transformProviderName(),
            'status' => $this->resource->status,
            'displayed' => $this->resource->is_displayed,
            'promo' => $this->resource->is_promo,
            'description' => $this->resource->description,
            'notes' => $this->resource->notes,
            'extra_field_metadata' => $this->resource->extra_field_metadata,
            'games' => $this->transformGames(),
        ];
    }

    /**
     * Transform related games info.
     *
     * @return [type] [description]
     */
    private function transformGames()
    {
        if (! isset($this->resource->games)) {
            $this->resource->load(['games' => function($query) {
                $query->select('games.game_id', 'game_name');
            }]);
        }

        $games = null;
        foreach($this->resource->games as $game) {
            $games[] = [
                'id' => $game->game_id,
                'name' => $game->game_name,
            ];
        }

        return $games;
    }

    private function transformProviderName()
    {
        return $this->resource->provider_product
                ? $this->resource->provider_product->provider_name
                : null;
    }
}
