<?php

namespace Orbit\Controller\API\v1\Pub\BrandProduct\SearchableFilters;

trait AvailableStoreFilter
{
    /**
     * Override default filter by keyword to only search for store/mall name.
     *
     * @param [type] $objType [description]
     * @param [type] $keyword [description]
     */
    protected function filterAvailableStoreByKeyword($keyword)
    {
        $queryStrings = array_map(function($keyword) {
            return [
                'query_string' => [
                    'query' => '*' . $keyword . '*',
                    'fields' => [
                        'link_to_stores.store_name^8',
                        'link_to_stores.mall_name^10',
                    ],
                ],
            ];
        }, explode(' ', $keyword));

        $this->must([
            'nested' => [
                'path' => 'link_to_stores',
                'query' => [
                    'bool' => [
                        'should' => $queryStrings
                    ]
                ],
                // Sort link to stores based on relevancy,
                // so we show best match store-mall first.
                'inner_hits' => [
                    'sort' => [
                        [
                            "_score" => [
                                "order" => "desc",
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }
}