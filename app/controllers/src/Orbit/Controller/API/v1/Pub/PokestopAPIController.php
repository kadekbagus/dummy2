<?php namespace Orbit\Controller\API\v1\Pub;
/**
 * An API controller for managing mall which have pokestop.
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
use News;
use stdClass;
use Orbit\Helper\Util\PaginationNumber;
use DB;
use Validator;

class PokestopAPIController extends PubControllerAPI
{
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
            $sort_mode = OrbitInput::get('sortmode', 'asc');
            $usingDemo = Config::get('orbit.is_demo', FALSE);

            $prefix = DB::getTablePrefix();

            $mall = News::select('merchants.merchant_id', 'merchants.name', 'merchants.city', 'merchants.description', DB::raw("CONCAT({$prefix}merchants.ci_domain, '/customer/pokestopdetail') pokestopdetail_url") )
                         ->join('merchants', 'merchants.merchant_id', '=', 'news.mall_id')
                         ->where('news.object_type', 'pokestop')
                         ->where('news.status', '!=', 'deleted');

            OrbitInput::get('filter_name', function ($filterName) use ($mall, $prefix) {
                if (! empty($filterName)) {
                    if ($filterName === '#') {
                        $mall->whereRaw("SUBSTR({$prefix}merchants.name,1,1) not between 'a' and 'z'");
                    } else {
                        $filter = explode("-", $filterName);
                        $mall->whereRaw("SUBSTR({$prefix}merchants.name,1,1) between {$this->quote($filter[0])} and {$this->quote($filter[1])}");
                    }
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
