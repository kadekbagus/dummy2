<?php

namespace Orbit\Controller\API\v1\Pub\BrandProduct\SearchableFilters;

/**
 * Implement filter by keyword specific for brand product.
 */
trait KeywordFilter
{
    /**
     * Set keyword priority/weight against each field.
     *
     * @param [type] $objType [description]
     * @param [type] $keyword [description]
     */
    protected function setPriorityForQueryStr($objType, $keyword, $logic = 'must')
    {
        $priorityProductName = isset($this->esConfig['priority'][$objType]['product_name']) ?
            $this->esConfig['priority'][$objType]['product_name'] : '^10';

        $priorityBrandName = isset($this->esConfig['priority'][$objType]['brand_name']) ?
            $this->esConfig['priority'][$objType]['brand_name'] : '^4';

        $priorityDescription = isset($this->esConfig['priority'][$objType]['description']) ?
            $this->esConfig['priority'][$objType]['description'] : '^4';

        $this->{$logic}([
            'bool' => [
                'should' => [
                    [
                        'query_string' => [
                            'query' => '*' . $keyword . '*',
                            'fields' => [
                                'product_name' . $priorityProductName,
                                'description' . $priorityDescription,
                                'brand_name' . $priorityBrandName
                            ]
                        ]
                    ],
                ]
            ]
        ]);
    }

    /**
     * Filter by keyword.
     *
     * @param  [type] $keyword [description]
     * @return [type]          [description]
     */
    public function filterByKeyword($keyword = '', $logic = 'must')
    {
        $keyword = $this->escape($keyword);

        $this->setPriorityForQueryStr($this->objectType, $keyword, $logic);
    }
}
