<?php namespace Orbit\Controller\API\v1\Pub\Rating;
/**
 * @author firmansyah <firmansyah@dominopos.com>
 * @desc Controller for news list and search in landing page
 */

use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use Helper\EloquentRecordCounter as RecordCounter;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use \Config;
use \Exception;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use \DB;
use \URL;
use Language;
use Validator;
use Orbit\Helper\Util\PaginationNumber;
use Activity;
use Carbon\Carbon as Carbon;
use Orbit\Helper\MongoDB\Client as MongoClient;
use stdClass;

class RatingListAPIController extends PubControllerAPI
{
    protected $valid_language = NULL;
    protected $withoutScore = FALSE;

    /**
     * GET - get active news in all mall, and also provide for searching
     *
     * @author Firmansyayh <firmansyah@dominopos.com>
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
    public function getRatingList()
    {
        $httpCode = 200;

        try {
            $user = $this->getUser();
            $objectId = OrbitInput::get('object_id', null);
            $objectType = OrbitInput::get('object_type', null);
            $take = PaginationNumber::parseTakeFromGet('news');
            $skip = PaginationNumber::parseSkipFromGet();
            $mongoConfig = Config::get('database.mongodb');

            $userTake = OrbitInput::get('user_take', $take);
            $userSkip = OrbitInput::get('user_skip', $skip);

            // search by key word or filter or sort by flag
            $searchFlag = FALSE;

            $validator = Validator::make(
                array(
                    'object_id'   => $objectId,
                    'object_type' => $objectType,
                ),
                array(
                    'object_id' => 'required',
                    'object_type' => 'required'
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $prefix = DB::getTablePrefix();

            $queryString = [
                'object_id'   => $objectId,
                'object_type' => $objectType,
                'take'        => $take,
                'skip'        => $skip,
                'sortBy'      => 'updated_at',
                'sortMode'    => 'desc'
            ];

            $mongoClient = MongoClient::create($mongoConfig);
            $endPoint = "reviews";
            $response = $mongoClient->setQueryString($queryString)
                                    ->setEndPoint($endPoint)
                                    ->request('GET');

            $listOfRec = $response->data;

            $data = new \stdclass();
            $data->returned_records = count($listOfRec->returned_records);
            $data->total_records = count($listOfRec->total_records);
            $data->records = $listOfRec->records;
            $data->user_rating = [];

            // if user login get user review
            $role = $user->role->role_name;
            if (strtolower($role) === 'consumer') {
                $queryString['user_id'] = $user->user_id;
                $queryString['take'] = $userTake;
                $queryString['skip'] = $userSkip;
                $userEndPoint = "reviews";

                $userRating = $mongoClient->setQueryString($queryString)
                                        ->setEndPoint($userEndPoint)
                                        ->request('GET');

                if (! empty($userRating->data)) {
                    $data->user_rating = $userRating->data->records;
                }
            }

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

    /**
     * Force $withScore value to FALSE, ignoring previously set value
     * @param $bool boolean
     */
    public function setWithOutScore()
    {
        $this->withoutScore = TRUE;

        return $this;
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }
}