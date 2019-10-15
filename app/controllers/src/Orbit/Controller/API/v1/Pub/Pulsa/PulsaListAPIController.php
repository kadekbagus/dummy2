<?php namespace Orbit\Controller\API\v1\Pub\Pulsa;

use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use Helper\EloquentRecordCounter as RecordCounter;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use \Config;
use \Exception;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use \DB;
use Pulsa;
use TelcoOperator;
use PaymentTransaction;
use Orbit\Controller\API\v1\Pub\Partner\PartnerHelper;
use Validator;
use Orbit\Helper\Util\PaginationNumber;
use Orbit\Helper\Database\Cache as OrbitDBCache;

/**
 * Handler for pulsa list request.
 *
 * @author Budi <budi@dominopos.com>
 */
class PulsaListAPIController extends PubControllerAPI
{
    protected $valid_language = NULL;
    /**
     * GET - get pulsa list
     *
     * @author Ahmad <ahmad@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string sortby
     * @param string sortmode
     * @param string take
     * @param string skip
     * @param string keyword
     * @param string filter_name
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getList()
    {
        $httpCode = 200;
        try{

            // $sort_by = OrbitInput::get('sortby', 'name');
            // $sort_mode = OrbitInput::get('sortmode','asc');
            // $language = OrbitInput::get('language', 'id');
            $country = OrbitInput::get('country', 0);
            // $no_total_records = OrbitInput::get('no_total_records', null);

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

            $prefix = DB::getTablePrefix();
            $usingCdn = Config::get('orbit.cdn.enable_cdn', FALSE);
            $defaultUrlPrefix = Config::get('orbit.cdn.providers.default.url_prefix', '');
            $urlPrefix = ($defaultUrlPrefix != '') ? $defaultUrlPrefix . '/' : '';

            $telcoLogo = "CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path) as logo_url";
            if ($usingCdn) {
                $telcoLogo = "CASE WHEN ({$prefix}media.cdn_url is null or {$prefix}media.cdn_url = '') THEN CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path) ELSE {$prefix}media.cdn_url END as logo_url";
            }

            $pulsa = Pulsa::select(
                                'pulsa.*',
                                DB::raw("{$prefix}telco_operators.telco_operator_id as operator_id"),
                                DB::raw("{$prefix}telco_operators.name as operator_name"),
                                DB::raw("count({$prefix}payment_transactions.payment_transaction_id) as sold_quantity")
                            )
                            ->leftJoin('payment_transaction_details', 'pulsa.pulsa_item_id', '=', 'payment_transaction_details.object_id')
                            ->leftJoin('payment_transactions', function($join) use ($prefix) {
                                $success = PaymentTransaction::STATUS_SUCCESS;
                                $pending = PaymentTransaction::STATUS_PENDING;
                                $successNoCoupon = PaymentTransaction::STATUS_SUCCESS_NO_COUPON;
                                $successNoPulsa = PaymentTransaction::STATUS_SUCCESS_NO_PULSA;
                                $join->on('payment_transaction_details.payment_transaction_id', '=', DB::raw("
                                    {$prefix}payment_transactions.payment_transaction_id AND (
                                        {$prefix}payment_transactions.status = '{$success}'
                                        OR {$prefix}payment_transactions.status = '{$pending}'
                                        OR {$prefix}payment_transactions.status = '{$successNoCoupon}'
                                        OR {$prefix}payment_transactions.status = '{$successNoPulsa}')
                                    "));
                            })
                            ->join('telco_operators', 'pulsa.telco_operator_id', '=', 'telco_operators.telco_operator_id')
                            ->join('countries', 'telco_operators.country_id', '=', 'countries.country_id')
                            ->where('countries.name', $country)
                            ->whereIn('pulsa.object_type', ['pulsa', 'data_plan'])
                            //this is added just ensure object_type_status_displayed_idx is used
                            ->whereIn('pulsa.status', ['active', 'inactive'])
                            ->where('pulsa.displayed', 'yes')
                            ->orderBy('pulsa.value')
                            ->groupBy('pulsa_item_id');

            $telcoOperators = TelcoOperator::select(
                    'telco_operators.*',
                    DB::raw("{$prefix}telco_operators.telco_operator_id as operator_id"),
                    DB::raw("{$prefix}telco_operators.name as operator_name"),
                    'telco_operators.country_id',
                    'telco_operators.identification_prefix_numbers as operator_prefixes',
                    DB::raw($telcoLogo)
                )
                ->join('countries', 'telco_operators.country_id', '=', 'countries.country_id')
                ->leftJoin('media', function($join) use ($prefix) {
                    $join->on('telco_operators.telco_operator_id', '=', 'media.object_id')
                         ->on('media.media_name_long', '=', DB::raw("'telco_operator_logo_orig'"));
                })
                ->where('countries.name', $country)
                ->where('status', 'active')
                ->get();

            // Cache the result of database calls
            // OrbitDBCache::create(Config::get('orbit.cache.database', []))->remember($pulsa);

            $listOfRec = $pulsa->get();
            $totalRec = $pulsa->count();

            $data = new \stdclass();
            $data->returned_records = count($listOfRec);
            $data->total_records = $totalRec;
            $data->records = $listOfRec;

            $data->records_operator = $telcoOperators;
            $data->total_records_operator = $telcoOperators->count();

            $this->response->data = $data;
            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Request Ok';

        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;

        } catch (QueryException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            // Only shows full query error when we are in debug mode
            if (Config::get('app.debug')) {
                $this->response->message = $e->getMessage();
            } else {
                $this->response->message = Lang::get('validation.orbit.queryerror');
            }
            $this->response->data = null;
            $httpCode = 500;

        } catch (Exception $e) {
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 500;
        }

        return $this->render($httpCode);
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }
}
