<?php namespace Orbit\Helper\Resource;

/**
 * Resource helper.
 *
 * @author Budi <budi@gotomalls.com>
 */
class ResourceCollection extends ResourceAbstract
{
    protected $collection = null;

    protected $total = 0;

    protected $imagePrefix = null;

    public function __construct($collection, $total = 0)
    {
        $this->collection = $collection;
        $this->total = $total;

        if (! empty($this->imagePrefix)) {
            $this->setImagePrefix($this->imagePrefix);
        }
    }
}
