<?php namespace Orbit\Controller\API\v1\Product\DigitalProduct\Resource;

use DigitalProduct;
use Orbit\Helper\Resource\Resource;

/**
 * Single Digital Product resource.
 *
 * @author Budi <budi@gotomalls.com>
 */
class DigitalProductResource extends Resource
{
    /**
     * Transform Digital Product object into response data array.
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'id' => $this->digital_product_id,
            'type' => $this->product_type,
            'name' => $this->product_name,
            'code' => $this->code,
            'price' => $this->selling_price,
            'status' => $this->status,
            'displayed' => $this->is_displayed,
            'promo' => $this->is_promo,
            'description' => $this->description,
            'notes' => $this->notes,
            'extra_field_metadata' => $this->extra_field_metadata,
            'provider_product' => [
                'id' => $this->selected_provider_product_id,
                'provider_name' => $this->getProviderName(),
                'product_name' => $this->getProviderProductName(),
            ],
            'games' => $this->transformGames(),
        ];
    }

    /**
     * Transform related games.
     *
     * @return array
     */
    protected function transformGames()
    {
        if (! isset($this->games)) {
            $this->resource->load(['games' => function($query) {
                $query->select('games.game_id', 'game_name');
            }]);
        }

        $games = null;
        foreach($this->games as $game) {
            $games[] = [
                'id' => $game->game_id,
                'name' => $game->game_name,
            ];
        }

        return $games;
    }

    /**
     * Get provider name (e.g. ayopay, unipin, etc)
     * @return string
     */
    protected function getProviderName()
    {
        $providerName = $this->provider_name;

        if (empty($providerName)) {
            $providerName = $this->provider_product
                                ? $this->provider_product->provider_name
                                : null;
        }

        return $providerName;
    }

    /**
     * Get provider product name.
     * @return string
     */
    protected function getProviderProductName()
    {
        $providerProductName = $this->provider_product_name;

        if (empty($providerProductName)) {
            $providerProductName = $this->provider_product
                                    ? $this->provider_product->provider_product_name
                                    : null;
        }

        return $providerProductName;
    }
}
