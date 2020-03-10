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
            'country_name' => 'countries.name',
            'status' => 'telco_operators.status',
            'updated_at' => 'telco_operators.updated_at',
        ];

        $sortBy = $sortByMapping[OrbitInput::get('sortby', 'updated_at')];
        $sortMode = OrbitInput::get('sortmode', 'asc');

        return TelcoOperator::select(
                'telco_operator_id',
                'telco_operators.name as name',
                'countries.name as country_name',
                'telco_operators.status',
                'telco_operators.updated_at'
            )
            ->leftJoin(
                'countries',
                'countries.country_id', '=', 'telco_operators.country_id'
            )
            ->with($this->buildMediaQuery())
            ->whenHas('status', function($query, $status) {
                return $query->where('status', $status);
            })
            ->whenHas('keyword', function($query, $keyword) {
                return $query->where('telco_operators.name', 'like', "%{$keyword}%");
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

    /**
     * Toggle telco operator status.
     *
     * @param  string $id      telco id
     * @return Model          model
     */
    public function telcoToggleStatus($id)
    {
        DB::beginTransaction();

        $telco = TelcoOperator::findOrFail($id);

        $telco->status = $telco->status === 'active' ? 'inactive' : 'active';

        $telco->save();

        DB::commit();

        return $telco;
    }
}
