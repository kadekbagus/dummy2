<?php
/**
 * An API controller for mall location (country,city,etc).
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Carbon\Carbon as Carbon;
use Orbit\Helper\MongoDB\Client as MongoClient;
use Orbit\Helper\OneSignal\OneSignal;

class TargetAudienceAPIController extends ControllerAPI
{
    protected $viewRoles = ['super admin', 'mall admin', 'mall owner', 'campaign owner', 'campaign employee', 'campaign admin'];
    /**
     * POST - post new target audience
     * @author kadek <kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string        `target_audience_name`
     * @param string        `target_audience_description`
     * @param string        `status`
     * @param array         `notification_tokens`
     * @param array         `notification_user_id`
     *
     * @return Illuminate\Support\Facades\Response
     *
     */
    public function postNewTargetAudience()
    {
        $mongoConfig = Config::get('database.mongodb');
        $mongoClient = MongoClient::create($mongoConfig);
        $mongoNotifId = '';

        try {
            $httpCode = 200;

            // Require authentication
            $this->checkAuth();

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->viewRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            $targetAudienceName = OrbitInput::post('target_audience_name');
            $targetAudienceDescription = OrbitInput::post('target_audience_description');
            $notificationTokens = OrbitInput::post('notification_token');
            $notificationTokens = (array) $notificationTokens;
            $notificationUserId = OrbitInput::post('notification_user_id');
            $notificationUserId = (array) $notificationUserId;
            $status = OrbitInput::post('status');

            $validator = Validator::make(
                array(
                    'target_audience_name'        => $targetAudienceName,
                    'target_audience_description' => $targetAudienceDescription,
                    'status'                      => $status,
                ),
                array(
                    'target_audience_name'        => 'required',
                    'target_audience_description' => 'required',
                    'status'                      => 'in:active,inactive'
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            if (count($notificationTokens) !== count(array_unique($notificationTokens))) {
                OrbitShopAPI::throwInvalidArgument('Duplicate token in Notification Tokens');
            }

            $timestamp = date("Y-m-d H:i:s");
            $date = Carbon::createFromFormat('Y-m-d H:i:s', $timestamp, 'UTC');
            $dateTime = $date->toDateTimeString();

            $body = [
                'target_name'        => $targetAudienceName,
                'target_description' => $targetAudienceDescription,
                'tokens'             => $notificationTokens,
                'user_ids'           => $notificationUserId,
                'status'             => $status,
                'created_at'         => $dateTime
            ];

            $response = $mongoClient->setFormParam($body)
                                    ->setEndPoint('target-audience-notifications') // express endpoint
                                    ->request('POST');

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
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            //$this->response->data = $result;
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
            $message = $e->getMessage();
            if ($e->getCode() == 8701 && strpos($message, 'Incorrect player_id format in include_player_ids') !== false) {
                $message = 'Notification token is not valid';
            }

            // rollback
            if (! empty($mongoNotifId)) {
                $deleteNotif = $mongoClient->setEndPoint("notifications/$mongoNotifId")->request('DELETE');
            }

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $message;
            $this->response->data = null;
        }

        $output = $this->render($httpCode);

        return $output;
    }


    public function getSearchTargetAudience()
    {
        try {
            $httpCode = 200;

            // Require authentication
            $this->checkAuth();

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->viewRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.mall_country.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }

            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.mall_country.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            // Get the take args
            $take = $perPage;
            OrbitInput::get('take', function ($_take) use (&$take, $maxRecord) {
                if ($_take > $maxRecord) {
                    $_take = $maxRecord;
                }
                $take = $_take;

                if ((int)$take <= 0) {
                    $take = $maxRecord;
                }
            });

            $skip = 0;
            OrbitInput::get('skip', function ($_skip) use (&$skip) {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });

            // Default sort by
            $sortBy = 'created_at';
            // Default sort mode
            $sortMode = 'desc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'target_audience_name'        => 'target_name',
                    'target_audience_description' => 'target_description',
                    'created_at'                  => 'created_at',
                    'status'                      => 'status'
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) === 'asc') {
                    $sortMode = 'asc';
                }
            });

            $queryString = [
                'take'        => $take,
                'skip'        => $skip,
                'sortBy'      => $sortBy,
                'sortMode'    => $sortMode
            ];

            $mongoConfig = Config::get('database.mongodb');
            $mongoClient = MongoClient::create($mongoConfig);
            $response = $mongoClient->setQueryString($queryString)
                                    ->setEndPoint('target-audience-notifications')
                                    ->request('GET');

            $listOfRec = $response->data;

            $data = new \stdclass();
            $data->returned_records = $listOfRec->returned_records;
            $data->total_records = $listOfRec->total_records;
            $data->records = $listOfRec->records;

            $this->response->data = $data;
            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Request Ok';
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.mall.getsearchmallcountry.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.mall.getsearchmallcountry.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            //$this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.mall.getsearchmallcountry.query.error', array($this, $e));

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
            Event::fire('orbit.mall.getsearchmallcountry.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.mall.getsearchmallcountry.before.render', array($this, &$output));

        return $output;
    }

    /**
     * POST - post update target audience
     * @author kadek <kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string        `target_audience_name`
     * @param string        `target_audience_description`
     * @param string        `status`
     * @param array         `notification_tokens`
     * @param array         `notification_user_id`
     *
     * @return Illuminate\Support\Facades\Response
     *
     */
    public function postUpdateTargetAudience()
    {
        try {
            $httpCode = 200;

            // Require authentication
            $this->checkAuth();

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->viewRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            $targetAudienceId = OrbitInput::post('target_audience_id');
            $targetAudienceName = OrbitInput::post('target_audience_name');
            $targetAudienceDescription = OrbitInput::post('target_audience_description');
            $notificationTokens = OrbitInput::post('notification_token');
            $notificationTokens = (array) $notificationTokens;
            $notificationUserId = OrbitInput::post('notification_user_id');
            $notificationUserId = (array) $notificationUserId;
            $status = OrbitInput::post('status');

            $validator = Validator::make(
                array(
                    'target_audience_id'          => $targetAudienceId,
                    'target_audience_name'        => $targetAudienceName,
                    'target_audience_description' => $targetAudienceDescription,
                    'status'                      => $status,
                ),
                array(
                    'target_audience_id'          => 'required',
                    'target_audience_name'        => 'required',
                    'target_audience_description' => 'required',
                    'status'                      => 'in:active,inactive'
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            if (count($notificationTokens) !== count(array_unique($notificationTokens))) {
                OrbitShopAPI::throwInvalidArgument('Duplicate token in Notification Tokens');
            }

            $mongoClient = MongoClient::create($mongoConfig);
            $oldNotification = $mongoClient->setEndPoint("target-audience-notifications/$targetAudienceId")->request('GET');

            if (empty($oldNotification)) {
                $errorMessage = 'Target Audience ID is not found';
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $timestamp = date("Y-m-d H:i:s");
            $date = Carbon::createFromFormat('Y-m-d H:i:s', $timestamp, 'UTC');
            $dateTime = $date->toDateTimeString();

            $body = [
                '_id'                => $targetAudienceId,
                'target_name'        => $targetAudienceName,
                'target_description' => $targetAudienceDescription,
                'tokens'             => $notificationTokens,
                'user_ids'           => $notificationUserId,
                'status'             => $status,
                'created_at'         => $dateTime
            ];

            $mongoClient = MongoClient::create($mongoConfig)->setFormParam($body);
            $response = $mongoClient->setEndPoint('target-audience-notifications') // express endpoint
                                    ->request('PUT');

            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->data = $response->data;;
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

            //$this->response->data = $result;
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
            $message = $e->getMessage();
            if ($e->getCode() == 8701 && strpos($message, 'Incorrect player_id format in include_player_ids') !== false) {
                $message = 'Notification token is not valid';
            }

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $message;
            $this->response->data = null;
        }

        $output = $this->render($httpCode);

        return $output;
    }

}