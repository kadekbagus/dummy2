<?php namespace Orbit\Helper\Resource;

/**
 * Resource helper.
 * A helper which maps/format an Eloquent Model/Collection into
 * orbit api response data. This helps limiting what data/properties
 * should be returned to the client.
 *
 * @author Budi <budi@gotomalls.com>
 */
abstract class ResourceAbstract implements ResourceInterface
{
    use ImageTransformer;

    public function __invoke()
    {
        return $this->toArray();
    }
}
