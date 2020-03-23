<?php

namespace Orbit\Controller\API\v1\Product\Pulsa\Repository;

use DB;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Pulsa;


class PulsaDetailRepository {

    private $pulsa = null;

    public function __construct()
    {

    }

    public function getDetailPulsa()
    {
        $pulsaItemId = OrbitInput::get('pulsa_item_id');

        $prefix = DB::getTablePrefix();
        $pulsa = Pulsa::select('pulsa.*',
                               DB::raw("(SELECT COUNT(DISTINCT {$prefix}payment_transactions.payment_transaction_id)
                                        FROM {$prefix}payment_transactions
                                        LEFT JOIN {$prefix}payment_transaction_details ON {$prefix}payment_transaction_details.payment_transaction_id = {$prefix}payment_transactions.payment_transaction_id
                                        WHERE {$prefix}payment_transactions.status = 'success' AND {$prefix}payment_transaction_details.object_id = {$this->quote($pulsaItemId)}) as sold"))
                            ->with('telcoOperator')
                            ->where('pulsa_item_id', $pulsaItemId)
                            ->firstOrFail();

        return $pulsa;
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }
}