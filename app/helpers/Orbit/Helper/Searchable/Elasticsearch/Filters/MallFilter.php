<?php

namespace Orbit\Helper\Searchable\Elasticsearch\Filters;

trait MallFilter
{
    public function filterByMall($mallId)
    {
        $this->must([
            'nested' => [
                'path' => 'link_to_malls',
                'query' => [
                    'match' => [
                        'link_to_malls.mall_id' => $mallId
                    ]
                ],
            ]
        ]);
    }
}
