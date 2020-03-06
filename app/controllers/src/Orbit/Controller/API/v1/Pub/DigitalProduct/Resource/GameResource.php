<?php namespace Orbit\Controller\API\v1\Pub\DigitalProduct\Resource;

use Game;
use Orbit\Helper\Resource\Resource;

/**
 * A Game resource.
 *
 * @author Budi <budi@gotomalls.com>
 */
class GameResource extends Resource
{
    protected $imagePrefix = 'game_image_';

    /**
     * Transform a Game.
     *
     * @return array
     */
    public function toArray()
    {
        if ( empty($this->resource)) {
            return [];
        }

        return [
            'id' => $this->game_id,
            'name' => $this->game_name,
            'slug' => $this->slug,
            'status' => $this->status,
            'description' => $this->description,
            'seo_text' => $this->seo_text,
            'images' => $this->transformImages($this->resource),
            'products' => $this->transformProducts($this->digital_products),
        ];
    }

    /**
     * Transform available digital products for this Game.
     *
     * @param  [type] $resource [description]
     * @return [type]           [description]
     */
    protected function transformProducts($digitalProducts)
    {
        $products = null;

        foreach($digitalProducts as $product) {
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
            ];
        }

        return $products;
    }
}
