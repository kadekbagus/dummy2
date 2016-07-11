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
use stdClass;
use Orbit\Helper\Util\PaginationNumber;

class QuestionerAPIController extends ControllerAPI
{
    /**
     * GET - check if user inside mall area
     *
     * @author Firmansyah <firmansyah@dominopos.com>
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

            $usingDemo = Config::get('orbit.is_demo', FALSE);

            $malls = Mall::select('merchants.*')->includeLatLong()->InsideArea($lat, $long);

            if ($usingDemo) {
                $malls->excludeDeleted();
            } else {
                // Production
                $malls->active();
            }

            // Filter by mall_id
            OrbitInput::get('mall_id', function ($mallid) use ($malls) {
                $malls->where('merchants.merchant_id', $mallid);
            });

            $_malls = clone $malls;

            $take = PaginationNumber::parseTakeFromGet('geo_location');
            $malls->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $malls->skip($skip);

            $listmalls = $malls->get();
            $count = RecordCounter::create($_malls)->count();

            $this->response->data = new stdClass();
            $this->response->data->total_records = $count;
            $this->response->data->returned_records = count($listmalls);
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
     * POST -  User answer
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string latitude
     * @param string longitude
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postUserAnswer()
    {
        $httpCode = 200;
        try {

            $lat = OrbitInput::get('latitude', null);
            $long = OrbitInput::get('longitude', null);

            $usingDemo = Config::get('orbit.is_demo', FALSE);

            $malls = Mall::select('merchants.*')->includeLatLong()->InsideArea($lat, $long);

            if ($usingDemo) {
                $malls->excludeDeleted();
            } else {
                // Production
                $malls->active();
            }

            // Filter by mall_id
            OrbitInput::get('mall_id', function ($mallid) use ($malls) {
                $malls->where('merchants.merchant_id', $mallid);
            });

            $_malls = clone $malls;

            $take = PaginationNumber::parseTakeFromGet('geo_location');
            $malls->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $malls->skip($skip);

            $listmalls = $malls->get();
            $count = RecordCounter::create($_malls)->count();

            $this->response->data = new stdClass();
            $this->response->data->total_records = $count;
            $this->response->data->returned_records = count($listmalls);
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





}