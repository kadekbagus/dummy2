<?php namespace Orbit\Controller\API\v1\Pub\DigitalProduct\Resource;

use Orbit\Helper\Resource\ResourceCollection;

/**
 * A collection of Game.
 *
 * @author Budi <budi@gotomalls.com>
 */
class GameCollection extends ResourceCollection
{
    protected $imagePrefix = 'game_image_';

    /**
     * Transform collection to array.
     *
     * @return array
     */
    public function toArray()
    {
        foreach($this->collection as $item) {
            $this->data['records'][] = [
                'id' => $item->game_id,
                'name' => $item->game_name,
                'slug' => $item->slug,
                'status' => $item->status,
                'images' => $this->transformImages($item),
            ];
        }

        return $this->data;
    }
}
