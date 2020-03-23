<?php

namespace Orbit\Controller\API\v1\Pub\BrandProduct\SearchableFilters;

/**
 * Implement filter by categories specific for brand product.
 */
trait CategoryFilter
{
    /**
     * Filter by categories.
     *
     * @param  [type] $cities [description]
     * @return [type]         [description]
     */
    public function filterByCategories($categories, $logic = 'must')
    {
        $arrCategories = [];

        foreach($categories as $category) {
            $arrCategories[] = ['match' => ['link_to_categories.category_id' => $category]];
        }

        $this->{$logic}([
            'nested' => [
                'path' => 'link_to_categories',
                'query' => [
                    'bool' => [
                        'should' => $arrCategories,
                        // 'minimum_should_match' => 1,
                    ]
                ]
            ],
        ]);
    }
}
