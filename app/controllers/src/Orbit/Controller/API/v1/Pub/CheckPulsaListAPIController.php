<?php namespace Orbit\Controller\API\v1\Pub;
/**
 * API to get pulsa vendor price
 * @author ahmad <ahmad@dominopos.com>
 */

use OrbitShop\API\v1\PubControllerAPI;
use Config;
use Illuminate\Support\Facades\Response;
use \DB;
use Pulsa;
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
                                'pulsa.vendor_price'
                            )
                            ->join('telco_operators', 'pulsa.telco_operator_id', '=', 'telco_operators.telco_operator_id')
                            ->join('countries', 'telco_operators.country_id', '=', 'countries.country_id')
                            ->where('countries.name', $country)
                            ->orderBy('pulsa.pulsa_code');

            $listOfRec = $pulsa->get();
            $totalRec = $pulsa->count();

            $data = new \stdclass();
            $data->returned_records = count($listOfRec);
            $data->total_records = $totalRec;

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
