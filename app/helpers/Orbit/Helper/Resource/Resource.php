<?php namespace Orbit\Helper\Resource;

/**
 * Base class for a single resource.
 *
 * @author Budi <budi@gotomalls.com>
 */
class Resource extends ResourceAbstract
{
    /**
     * The resource/eloquent model.
     * @var Illuminate\Database\Eloquent\Model
     */
    protected $resource;

    public function __construct($resource)
    {
        $this->resource = $resource;
    }

    /**
     * Proxy resource properties so it can be accessed directly through $this.
     *
     * @param  [type] $key [description]
     * @return [type]      [description]
     */
    public function __get($key)
    {
        return $this->resource->{$key};
    }
}
