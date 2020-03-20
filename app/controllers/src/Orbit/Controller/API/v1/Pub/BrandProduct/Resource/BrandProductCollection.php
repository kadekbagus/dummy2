<?php

namespace Orbit\Controller\API\v1\Pub\BrandProduct\Resource;

use Orbit\Helper\Resource\ResourceCollection;

/**
 * Brand Product collection class.
 *
 * @author Budi <budi@gotomalls.com>
 */
class BrandProductCollection extends ResourceCollection
{
    public function toArray()
    {
        foreach($this->collection as $item) {
            $this->data[] = [
                'id' => '',
                'name' => '',
                'description' => '',
                'images' => $this->transformImages($item),
            ];
        }

        return $this->data;
    }

    protected function transformImages($item, $imagePrefix = '')
    {

    }
}
