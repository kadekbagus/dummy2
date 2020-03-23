<?php namespace Orbit\Controller\API\v1\Pub\DigitalProduct\Resource;

use Orbit\Helper\Resource\Resource;

/**
 * Digital Product resource mapper.
 *
 * @author Budi <budi@gotomalls.com>
 */
class DigitalProductResource extends Resource
{
    protected $imagePrefix = 'game_image_';

    /**
     * Transform model into array for response.
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
            'provider_product_id' => $this->selected_provider_product_id,
            'provider_name' => $this->getProviderName(),
            'status' => $this->status,
            'displayed' => $this->is_displayed === 'yes',
            'promo' => $this->is_promo === 'yes',
            'description' => $this->description,
            'notes' => $this->notes,
            'extra_field_metadata' => $this->extra_field_metadata,
            'game' => $this->transformGame(),
        ];
    }

    /**
     * Transform provider name.
     *
     * @return string
     */
    private function getProviderName()
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
     * Get the game that linked to current digital product.
     *
     * @return Illuminate\Database\Eloquent\Model|null the game instance.
     */
    private function getGame()
    {
        return ! $this->games->isEmpty() ? $this->games->first() : null;
    }

    /**
     * Transform game that linked to digital product.
     *
     * @return array
     */
    private function transformGame()
    {
        $game = $this->getGame();

        if (! empty($game)) {
            $game = [
                'id' => $game->game_id,
                'name' => $game->game_name,
                'slug' => $game->slug,
                'description' => $game->description,
                'seo_text' => $game->seo_text,
                'images' => $this->transformImages($game),
            ];
        }

        return $game;
    }
}
