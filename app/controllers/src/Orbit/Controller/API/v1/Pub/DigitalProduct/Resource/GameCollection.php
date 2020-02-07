<?php namespace Orbit\Controller\API\v1\Pub\DigitalProduct\Resource;

use Orbit\Helper\Resource\ResourceCollection;

/**
 * Resource Collection of Game.
 *
 * @author Budi <budi@gotomalls.com>
 */
class GameCollection extends ResourceCollection
{
    protected $imagePrefix = 'game_image_';

    /**
     * Transform collection to array as response data.
     *
     * @return [type] [description]
     */
    public function toArray()
    {
        $data = [
            'returned_records' => $this->collection->count(),
            'total_records' => $this->total,
            'records' => [],
        ];

        foreach($this->collection as $item) {
            $data['records'][] = [
                'id' => $item->game_id,
                'name' => $item->game_name,
                'slug' => $item->slug,
                'status' => $item->status,
                'images' => $this->transformImages($item),
            ];
        }

        return $data;
    }
}
