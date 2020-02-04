<?php namespace Orbit\Controller\API\v1\Pub\DigitalProduct\Resource;

use Game;
use Orbit\Helper\Resource\ResourceAbstract as Resource;

/**
 * Resource Collection of Game.
 *
 * @author Budi <budi@gotomalls.com>
 */
class GameResource extends Resource
{
    private $resource = null;

    public function __construct($resource)
    {
        $this->resource = $resource;
        $this->setImagePrefix('game_image_');
    }

    /**
     * Transform Game into response data array.
     *
     * @return [type] [description]
     */
    public function toArray()
    {
        return [
            'id' => $this->resource->game_id,
            'name' => $this->resource->game_name,
            'slug' => $this->resource->slug,
            'status' => $this->resource->status,
            'description' => $this->resource->description,
            'seo_text' => $this->resource->seo_text,
            'images' => $this->transformImages($this->resource),
            'products' => $this->transformProducts($this->resource),
        ];
    }

    /**
     * Transform available digital products for this Game.
     *
     * @param  [type] $resource [description]
     * @return [type]           [description]
     */
    protected function transformProducts($resource)
    {
        $products = null;

        foreach($resource->digital_products as $product) {
            $products[] = [
                'id' => $product->digital_product_id,
                'type' => $product->product_type,
                'code' => $product->code,
                'name' => $product->product_name,
                'price' => $product->selling_price,
                'displayed' => $product->is_displayed === 'yes',
                'promo' => $product->is_promo === 'yes',
                'status' => $product->status,
                'description' => $product->description,
                'notes' => $product->notes,
                'extra_field_metadata' => $product->extra_field_metadata,
                // 'provider' => $product->provider_product
                //     ? $product->provider_product->provider_name
                //     : null,
            ];
        }

        return $products;
    }
}
