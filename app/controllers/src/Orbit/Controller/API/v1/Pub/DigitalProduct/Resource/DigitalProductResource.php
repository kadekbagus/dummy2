<?php namespace Orbit\Controller\API\v1\Pub\DigitalProduct\Resource;

use DB;
use Orbit\Helper\Resource\ResourceAbstract as Resource;

/**
 * Digital Product resource mapper.
 *
 * @todo  create separate base resource folder on the same level as orbit\controller
 * @author Budi <budi@gotomalls.com>
 */
class DigitalProductResource extends Resource
{
    private $resource = null;

    protected $imagePrefix = 'game_image_';

    public function __construct($resource)
    {
        $this->resource = $resource;
    }

    /**
     * Transform model into array for response.
     *
     * @return [type] [description]
     */
    public function toArray()
    {
        $game = $this->resource->games->first();
        return [
            'id' => $this->resource->digital_product_id,
            'type' => $this->resource->product_type,
            'name' => $this->resource->product_name,
            'code' => $this->resource->code,
            'price' => $this->resource->selling_price,
            'provider_id' => $this->resource->selected_provider_product_id,
            'provider_name' => $this->transformProviderName(),
            'status' => $this->resource->status,
            'displayed' => $this->resource->is_displayed === 'yes',
            'promo' => $this->resource->is_promo === 'yes',
            'description' => $this->resource->description,
            'notes' => $this->resource->notes,
            'extra_field_metadata' => $this->resource->extra_field_metadata,
            'game' => [
                'id' => $game->game_id,
                'name' => $game->game_name,
                'slug' => $game->slug,
                'description' => $game->description,
                'seo_text' => $game->seo_text,
                'images' => $this->transformImages($game),
            ]
        ];
    }

    private function transformProviderName()
    {
        $providerName = $this->resource->provider_name;

        if (empty($providerName)) {
            $providerName = $this->resource->provider_product
                                ? $this->resource->provider_product->provider_name
                                : null;
        }

        return $providerName;
    }
}
