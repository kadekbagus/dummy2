<?php namespace Orbit\Controller\API\v1\Pub\UserFollow;
/**
 * An API controller for managing feedback.
 */
use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use Carbon\Carbon as Carbon;
use Orbit\Helper\MongoDB\Client as MongoClient;
use Orbit\Helper\OneSignal\OneSignal;
use Config;
use Validator;
use Activity;
use Mall;
use Tenant;

class FollowAPIController extends PubControllerAPI
{
    /**
     * POST - follow and unfollow
     *
     * @author kadek <kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string feedback
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postFollow()
    {
        $user = NULL;
        $httpCode = 200;
        $mongoConfig = Config::get('database.mongodb');
        $mongoClient = MongoClient::create($mongoConfig);

        try {
            $user = $this->getUser();

            $object_id = OrbitInput::post('object_id');
            $object_type = OrbitInput::post('object_type');
            $city = OrbitInput::post('city');
            $country_id = OrbitInput::post('country_id');

            $validator = Validator::make(
                array(
                    'object_id'   => $object_id,
                    'object_type' => $object_type,
                ),
                array(
                    'object_id'   => 'required',
                    'object_type' => 'required|in:mall,store',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            switch($object_type) {
                case "mall":
                    // check already follow or not
                    $queryString = [
                        'user_id'     => $user->user_id,
                        'object_id'   => $object_id,
                        'object_type' => 'mall'
                    ];

                    $existingData = $mongoClient->setQueryString($queryString)
                                         ->setEndPoint('user-follows')
                                         ->request('GET');

                    if (count($existingData->data->records) === 0) {
                        // follow
                        $timestamp = date("Y-m-d H:i:s");
                        $date = Carbon::createFromFormat('Y-m-d H:i:s', $timestamp, 'UTC');
                        $dateTime = $date->toDateTimeString();
                        $city = null;
                        $country_id = null;

                        $mall = Mall::excludeDeleted('merchants')
                                     ->where('merchant_id', '=', $object_id)
                                     ->first();

                        if (is_object($mall)) {
                            $city = $mall->city;
                            $country_id = $mall->country_id;
                        }

                        $dataInsert = [
                            'user_id'     => $user->user_id,
                            'object_id'   => $object_id,
                            'object_type' => 'mall',
                            'city'        => $city,
                            'country_id'  => $country_id,
                            'created_at'  => $dateTime
                        ];

                        $response = $mongoClient->setFormParam($dataInsert)
                                                ->setEndPoint('user-follows')
                                                ->request('POST');

                    } else {
                        // unfollow
                        $id = $existingData->data->records[0]->_id;
                        $response = $mongoClient->setEndPoint("user-follows/$id")
                                                ->request('DELETE');
                    }

                    break;
                case "store":

                    break;
            }

            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->data = $response->data;

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
            $this->response->data = null;
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
        } catch (\Exception $e) {
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