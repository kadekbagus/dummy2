<?php namespace Orbit\Controller\API\v1\Pub;
/**
 * API to get pulsa vendor price
 * @author ahmad <ahmad@dominopos.com>
 *
 * Nov 18th: Added PLN Token to the list
 */

use OrbitShop\API\v1\PubControllerAPI;
use Config;
use Illuminate\Support\Facades\Response;
use \DB;
use Pulsa;
use ProviderProduct;
use OrbitShop\API\v1\Helper\Input as OrbitInput;

class CheckPulsaListAPIController extends PubControllerAPI
{
    public function getList()
    {
        $httpCode = 200;
        try {
            $country = OrbitInput::get('country', 0);

            if (empty($country) || $country == '0') {
                // return empty result if country filter is not around
                $data = new \stdClass;
                $data->records = [];
                $data->returned_records = 0;
                $data->total_records = 0;
                $data->records_operator = [];
                $data->total_records_operator = 0;

                $this->response->data = null;
                $this->response->code = 0;
                $this->response->status = 'success';
                $this->response->message = 'Request Ok';

                return $this->render($httpCode);
            }


            $pulsa = Pulsa::select(
                    'pulsa.pulsa_code',
                    'pulsa.status',
                    'pulsa.vendor_price'
                )
                ->join('telco_operators', 'pulsa.telco_operator_id', '=', 'telco_operators.telco_operator_id')
                ->join('countries', 'telco_operators.country_id', '=', 'countries.country_id')
                ->where('countries.name', $country)
                ->where('pulsa.status', '<>', 'deleted')
                ->orderBy('pulsa.pulsa_code')
                ->get();

            $pln = ProviderProduct::select(
                    'provider_products.code as pulsa_code',
                    'provider_products.status',
                    'provider_products.price as vendor_price'
                )
                ->where('provider_name', 'mcash')
                ->where('product_type', 'electricity')
                ->orderBy('provider_products.code')
                ->get();

            $listOfRec = [];

            foreach ($pulsa as $pulsaItem) {
                $listOfRec[] = $pulsaItem;
            }

            foreach ($pln as $plnItem) {
                $listOfRec[] = $plnItem;
            }

            $data = new \stdclass();
            $data->returned_records = count($listOfRec);
            $data->total_records = count($listOfRec);

            $data->records = $listOfRec;

            $this->response->data = $data;
            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Request Ok';

            return $this->render($httpCode);

        } catch (\Exception $e) {
            return;
        }
    }
}
