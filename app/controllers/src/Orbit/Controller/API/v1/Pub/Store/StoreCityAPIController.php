<?php namespace Orbit\Controller\API\v1\Pub\Store;
/**
 * An API controller for managing mall geo location.
 */
use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Helper\EloquentRecordCounter as RecordCounter;
use Config;
use Mall;
use Tenant;
use stdClass;
use Orbit\Helper\Util\PaginationNumber;
use DB;
use Validator;
use Activity;
use Orbit\Helper\Database\Cache as OrbitDBCache;

class StoreCityAPIController extends PubControllerAPI
{
    protected $valid_language = NULL;

    /**
     * GET - get city of store
     *
     * @author Irianto Pratama <irianto@dominopos.com>
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string sortby
     * @param string sortmode
     * @param string take
     * @param string skip
     * @param string store_name
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getStoreCity()
    {
        $httpCode = 200;
        $activity = Activity::mobileci()->setActivityType('view');
        $user = null;

        try {
            $user = $this->getUser();

            $sort_by = OrbitInput::get('sortby', 'city');
            $sort_mode = OrbitInput::get('sortmode','asc');
            $merchant_id = OrbitInput::get('merchant_id');
            $store_name = null;

            $validator = Validator::make(
                array(
                    'merchant_id' => $merchant_id,
                ),
                array(
                    'merchant_id' => 'required',
                ),
                array(
                    'required' => 'Merchant id is required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $prefix = DB::getTablePrefix();

            // Get store name base in merchant_id
            $store = Tenant::select('merchant_id', 'name')->where('merchant_id', $merchant_id)->active()->first();
            if (! empty($store)) {
                $store_name = $store->name;
            }

            // Query without searching keyword
            $mall = Mall::select('merchants.city')
                        ->join(DB::raw("(select merchant_id, `name`, parent_id from {$prefix}merchants where name = {$this->quote($store_name)} and status = 'active') as oms"), DB::raw('oms.parent_id'), '=', 'merchants.merchant_id')
                        ->active();

            $mall = $mall->groupBy('merchants.city')->orderBy($sort_by, $sort_mode);

            $_mall = clone $mall;

            $take = PaginationNumber::parseTakeFromGet('retailer');
            $mall->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $mall->skip($skip);

            // moved from generic activity number 40
            if (empty($skip)) {
                $activityNotes = sprintf('Page viewed: Store city list');
                $activity->setUser($user)
                    ->setActivityName('view_city_location')
                    ->setActivityNameLong('View City Location Page')
                    ->setObject(null)
                    ->setObjectDisplayName($store_name)
                    ->setModuleName('Store')
                    ->setNotes($activityNotes)
                    ->responseOK()
                    ->save();
            }

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
