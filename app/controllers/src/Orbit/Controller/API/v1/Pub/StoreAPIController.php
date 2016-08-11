<?php namespace Orbit\Controller\API\v1\Pub;
/**
 * An API controller for managing mall geo location.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Text\Util\LineChecker;
use Helper\EloquentRecordCounter as RecordCounter;
use Config;
use Mall;
use News;
use Tenant;
use stdClass;
use Orbit\Helper\Util\PaginationNumber;
use DB;
use Validator;

class StoreAPIController extends ControllerAPI
{
    /**
     * GET - get all store in all mall, group by name
     *
     * @author Shelgi Prasetyo <shelgi@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string sortby
     * @param string sortmode
     * @param string take
     * @param string skip
     * @param string filter_name
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getStoreList()
    {
        $httpCode = 200;
        try {
            $sort_by = OrbitInput::get('sortby', 'name');
            $sort_mode = OrbitInput::get('sortmode','asc');
            $usingDemo = Config::get('orbit.is_demo', FALSE);

            $store = Tenant::select('merchant_id', 'name');

            OrbitInput::get('filter_name', function ($filterName) use ($store) {
                if (! empty($filterName)) {
                    if ($filterName === '#') {
                        $filterName = '^a-zA-Z';
                    }
                    $store->whereRaw("name REGEXP '^[{$filterName}]'");
                }
            });

            $store = $store->groupBy('name')->orderBy($sort_by, $sort_mode);

            if ($usingDemo) {
                $store->excludeDeleted();
            } else {
                // Production
                $store->active();
            }

            $_store = clone $store;

            $take = PaginationNumber::parseTakeFromGet('retailer');
            $store->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $store->skip($skip);

            $liststore = $store->get();
            $count = RecordCounter::create($_store)->count();

            $this->response->data = new stdClass();
            $this->response->data->total_records = $count;
            $this->response->data->returned_records = count($liststore);
            $this->response->data->records = $liststore;
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

        $output = $this->render($httpCode);

        return $output;
    }

    /**
     * GET - get mall list after click store name
     *
     * @author Shelgi Prasetyo <shelgi@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string sortby
     * @param string sortmode
     * @param string take
     * @param string skip
     * @param string filter_name
     * @param string store_name
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getMallStoreList()
    {
        $httpCode = 200;
        try {
            $sort_by = OrbitInput::get('sortby', 'merchants.name');
            $sort_mode = OrbitInput::get('sortmode','asc');
            $usingDemo = Config::get('orbit.is_demo', FALSE);
            $storename = OrbitInput::get('store_name');

            $prefix = DB::getTablePrefix();

            $validator = Validator::make(
                array(
                    'store_name' => $storename,
                ),
                array(
                    'store_name' => 'required',
                ),
                array(
                    'required' => 'Store name is required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $mall = Mall::select('merchants.merchant_id', 'merchants.name', 'merchants.ci_domain', DB::raw("CONCAT({$prefix}merchants.ci_domain, '/customer/tenant?id=', oms.merchant_id) as store_url"))
                    ->join(DB::raw("(select merchant_id, `name`, parent_id from {$prefix}merchants where name = {$this->quote($storename)}) as oms"), DB::raw('oms.parent_id'), '=', 'merchants.merchant_id');

            OrbitInput::get('filter_name', function ($filterName) use ($mall, $prefix) {
                if (! empty($filterName)) {
                    if ($filterName === '#') {
                        $filterName = '^a-zA-Z';
                    }
                    $mall->whereRaw("merchants.name REGEXP '^[{$filterName}]'");
                }
            });

            $mall = $mall->groupBy('merchants.merchant_id')->orderBy($sort_by, $sort_mode);

            if ($usingDemo) {
                $mall->excludeDeleted();
            } else {
                // Production
                $mall->active();
            }

            $_mall = clone $mall;

            $take = PaginationNumber::parseTakeFromGet('retailer');
            $mall->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $mall->skip($skip);

            $listmall = $mall->get();
            $count = RecordCounter::create($_mall)->count();

            $this->response->data = new stdClass();
            $this->response->data->total_records = $count;
            $this->response->data->returned_records = count($listmall);
            $this->response->data->records = $listmall;
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

        $output = $this->render($httpCode);

        return $output;
    }


    /**
     * GET - get mall list after click pokestop menu
     *
     * @author Firmansyayh <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string sortby
     * @param string sortmode
     * @param string take
     * @param string skip
     * @param string filter_name
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getMallPokestopList()
    {
        $httpCode = 200;
        try {
            $sort_by = OrbitInput::get('sortby', 'merchants.name');
            $sort_mode = OrbitInput::get('sortmode','asc');
            $usingDemo = Config::get('orbit.is_demo', FALSE);

            $prefix = DB::getTablePrefix();

            $mall = News::select('merchants.merchant_id', 'merchants.name', 'merchants.address_line1', 'merchants.city', 'merchants.description', DB::raw("CONCAT({$prefix}merchants.ci_domain, '/customer/pokestopdetail') pokestopdetail_url") )
                         ->join('merchants', 'merchants.merchant_id', '=', 'news.mall_id')
                         ->where('news.object_type','pokestop');

            OrbitInput::get('filter_name', function ($filterName) use ($mall, $prefix) {
                if (! empty($filterName)) {
                    if ($filterName === '#') {
                        $filterName = '^a-zA-Z';
                    }
                    $mall->whereRaw("merchants.name REGEXP '^[{$filterName}]'");
                }
            });

            $mall = $mall->groupBy('merchants.merchant_id')->orderBy($sort_by, $sort_mode);

            if ($usingDemo) {
                $mall->where('merchants.status', '!=', 'deleted');;
            } else {
                // Production
                $mall->where('merchants.status', 'active');
            }

            $_mall = clone $mall;

            $take = PaginationNumber::parseTakeFromGet('retailer');
            $mall->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $mall->skip($skip);

            $listmall = $mall->get();
            $count = RecordCounter::create($_mall)->count();

            $this->response->data = new stdClass();
            $this->response->data->total_records = $count;
            $this->response->data->returned_records = count($listmall);
            $this->response->data->records = $listmall;
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

        $output = $this->render($httpCode);

        return $output;
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }
}