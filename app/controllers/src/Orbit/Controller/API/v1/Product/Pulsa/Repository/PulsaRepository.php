<?php

namespace Orbit\Controller\API\v1\Product\Pulsa\Repository;

use DB;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Orbit\Controller\API\v1\Pub\DigitalProduct\Helper\MediaQuery;
use TelcoOperator;

/**
 * Game repository.
 *
 * @author Budi <budi@gotomalls.com>
 */
class PulsaRepository
{
    use MediaQuery;

    protected $imagePrefix = '';

    public function __construct()
    {
        $this->setupImageUrlQuery();
    }

    /**
     * Get collection based on requested filter.
     *
     * @return Illuminate\Database\Query\Builder
     */
    public function getTelcoList()
    {
        $sortByMapping = [
            'name' => 'telco_operators.name',
            'country_name' => 'country_name',
            'status' => 'telco_operators.status',
        ];

        $status = OrbitInput::get('status');
        $sortBy = $sortByMapping[OrbitInput::get('sortby', 'status')];
        $sortMode = OrbitInput::get('sortmode', 'asc');
        $keyword = OrbitInput::get('keyword');

        return TelcoOperator::select(
                'telco_operator_id',
                'telco_operators.name as name',
                'countries.name as country_name',
                'telco_operators.status'
            )
            ->leftJoin(
                'countries',
                'countries.country_id', '=', 'telco_operators.country_id'
            )
            ->with($this->buildMediaQuery())
            ->when(! empty($status), function($query) use ($status) {
                return $query->where('status', $status);
            })
            ->when(! empty($keyword), function($query) use ($keyword) {
                return $query->where(
                    'telco_operators.name',
                    'like',
                    "%{$keyword}%"
                );
            })
            ->orderBy($sortBy, $sortMode);
    }

    /**
     * Get a single Telco Operator record.
     *
     * @param  string $telcoOperatorId telco operator id.
     *
     * @return Illuminate\Database\Query\Builder
     */
    public function telco($telcoOperatorId)
    {
        return TelcoOperator::select(
                'telco_operator_id',
                'telco_operators.name as name',
                'countries.name as country_name',
                'countries.country_id as country_id',
                'identification_prefix_numbers',
                'telco_operators.status',
                'telco_operators.seo_text'
            )
            ->leftJoin('countries', 'countries.country_id', '=', 'telco_operators.country_id')
            ->with($this->buildMediaQuery())
            ->where('telco_operator_id', $telcoOperatorId)
            ->firstOrFail();
    }
}
