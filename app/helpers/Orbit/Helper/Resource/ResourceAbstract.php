<?php namespace Orbit\Helper\Resource;

/**
 * Resource helper.
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
