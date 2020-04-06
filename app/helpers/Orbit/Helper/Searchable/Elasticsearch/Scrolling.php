<?php

namespace Orbit\Helper\Searchable\Elasticsearch;

/**
 * A helper that provide scrolling flag (indicator) support for host classes.
 *
 * @author Budi <budi@gotomalls.com>
 */
trait Scrolling
{
    protected $useScroll = false;

    protected $scrollDuration = '20s';

    public function setScrollDuration($duration = '20s')
    {
        $this->scrollDuration = $duration;
    }

    public function getScrollDuration()
    {
        return $this->scrollDuration;
    }

    public function setUseScroll($useScroll = false)
    {
        $this->useScroll = $useScroll;
    }

    public function useScrolling()
    {
        return $this->useScroll;
    }
}