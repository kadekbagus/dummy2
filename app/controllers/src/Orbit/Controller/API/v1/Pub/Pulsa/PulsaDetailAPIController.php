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
use Orbit\Controller\API\v1\Pub\Partner\PartnerHelper;
use Validator;
use Orbit\Helper\Util\PaginationNumber;
use Orbit\Helper\Database\Cache as OrbitDBCache;

/**
 * Handler for pulsa list request.
 *
 * @author Budi <budi@dominopos.com>
 */
class PulsaDetailAPIController extends PubControllerAPI
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
    public function getDetail()
    {
        $httpCode = 200;
        try{

            // $sort_by = OrbitInput::get('sortby', 'name');
            // $sort_mode = OrbitInput::get('sortmode','asc');
            // $language = OrbitInput::get('language', 'id');
            $country = OrbitInput::get('country', 0);
            $pulsaId = OrbitInput::get('pulsa_item_id');
            // $no_total_records = OrbitInput::get('no_total_records', null);

            // if (empty($country) || $country == '0') {
            //     // return empty result if country filter is not around
            //     $data = new \stdClass;
            //     $data->records = [];
            //     $data->returned_records = 0;
            //     $data->total_records = 0;
            //     $data->records_operator = [];
            //     $data->total_records_operator = 0;

            //     $this->response->data = null;
            //     $this->response->code = 0;
            //     $this->response->status = 'success';
            //     $this->response->message = 'Request Ok';

            //     return $this->render($httpCode);
            // }

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
                                'telco_operators.country_id',
                                'telco_operators.identification_prefix_numbers as operator_prefixes',
                                DB::raw($telcoLogo)
                            )
                            ->join('telco_operators', 'pulsa.telco_operator_id', '=', 'telco_operators.telco_operator_id')
                            ->join('countries', 'telco_operators.country_id', '=', 'countries.country_id')
                            ->leftJoin('media', function($join) use ($prefix) {
                                $join->on('telco_operators.telco_operator_id', '=', 'media.object_id')
                                     ->on('media.media_name_long', '=', DB::raw("'telco_operator_logo_orig'"));
                            })
                            // ->where('countries.name', $country)
                            ->where('telco_operators.status', 'active')
                            ->where('pulsa.pulsa_item_id', $pulsaId)
                            ->first();

            // Cache the result of database calls
            // OrbitDBCache::create(Config::get('orbit.cache.database', []))->remember($pulsa);

            $this->response->data = $pulsa;
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

    private function resolveOperator($pulsa, &$data)
    {
        $data->records_operator = [];
        $data->total_records_operator = 0;
        foreach($pulsa as $pulsaItem) {
            if (! isset($data->records_operator[$pulsaItem->operator_id])) {
                $data->records_operator[$pulsaItem->operator_id] = (object) [
                    'operator_id' => $pulsaItem->operator_id,
                    'operator_name' => $pulsaItem->operator_name,
                    'logo_url' => $pulsaItem->logo_url,
                    'operator_prefixes' => $pulsaItem->operator_prefixes,
                ];
            }
        }

        $data->records_operator = array_values($data->records_operator);
        $data->total_records_operator = count($data->records_operator);
    }
}
