<?php
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

class MallGeolocAPIController extends ControllerAPI
{
    /**
     * GET - Search mall by location
     *
     * @author Shelgi Prasetyo <shelgi@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string latitude
     * @param string longitude
     * @param string distance
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchMallNearby()
    {
        $httpCode = 200;
        try {

            $lat = OrbitInput::get('latitude', null);
            $long = OrbitInput::get('longitude', null);
            $distance = OrbitInput::get('distance');

            if (empty($distance)) {
                $distance = Config::get('orbit.geo_location.distance', 10);
            }
            $malls = Mall::excludeDeleted()->select('merchants.*')->nearBy($lat, $long, $distance);

            // Filter mall by name
            OrbitInput::get('mall_name_like', function ($mallname) use ($malls) {
                $malls->where('merchants.name', 'like', "%$mallname%");
            });

            $_malls = clone $malls;

            $listmalls = $malls->get();
            $count = RecordCounter::create($_malls)->count();

            $this->response->data = new stdClass();
            $this->response->data->total_records = $count;
            $this->response->data->returned_records = $count;
            $this->response->data->records = $listmalls;
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
     * GET - check if user inside mall area
     *
     * @author Shelgi Prasetyo <shelgi@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string latitude
     * @param string longitude
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getMallFence()
    {
        $httpCode = 200;
        try {

            $lat = OrbitInput::get('latitude', null);
            $long = OrbitInput::get('longitude', null);

            $malls = Mall::excludeDeleted()->select('merchants.*')->InsideArea($lat, $long);

            $_malls = clone $malls;

            $listmalls = $malls->get();
            $count = RecordCounter::create($_malls)->count();

            $this->response->data = new stdClass();
            $this->response->data->total_records = $count;
            $this->response->data->returned_records = $count;
            $this->response->data->records = $listmalls;
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