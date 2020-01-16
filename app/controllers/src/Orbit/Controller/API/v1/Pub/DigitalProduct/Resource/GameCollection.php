<?php namespace Orbit\Controller\API\v1\Pub\DigitalProduct\Resource;

use Game;
use Orbit\Helper\Resource\ResourceAbstract as Resource;

/**
 * Resource Collection of Game.
 *
 * @author Budi <budi@gotomalls.com>
 */
class GameCollection extends Resource
{
    private $collection = null;

    private $total = 0;

    public function __construct($collection, $total = 0)
    {
        $this->collection = $collection;
        $this->total = $total;
        $this->setImagePrefix('game_image_');
    }

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
