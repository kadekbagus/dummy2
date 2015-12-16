<?php
/**
 * An API controller for managing Inbox and Alert.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use Helper\EloquentRecordCounter as RecordCounter;

class InboxAPIController extends ControllerAPI
{   
    /**
     * GET - List of inboxes
     *
     * @author Ahmad <ahmad@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchInbox()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.inbox.getsearchinbox.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.inbox.getsearchinbox.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            $mall_id = App::make('orbitSetting')->getSetting('current_retailer');

            $alerts = Inbox::excludeDeleted()
                            ->where('user_id', $user->user_id)
                            ->where('merchant_id', $mall_id);

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_alerts = clone $alerts;

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.inbox.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.inbox.per_page');
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
            $alerts->take($take);

            $skip = 0;
            OrbitInput::get('skip', function($_skip) use (&$skip, $alerts)
            {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            if (($take > 0) && ($skip > 0)) {
                $alerts->skip($skip);
            }

            $alerts->orderBy('created_at', 'desc');

            $totalAlerts = RecordCounter::create($_alerts)->count();
            $listOfAlerts = $alerts->get();

            $data = new stdclass();
            $data->total_records = $totalAlerts;
            $data->returned_records = count($listOfAlerts);
            $data->records = $listOfAlerts;

            if ($totalAlerts === 0) {
                $data->records = null;
                $this->response->message = 'No new alert';
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.inbox.getsearchinbox.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.inbox.getsearchinbox.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.inbox.getsearchinbox.query.error', array($this, $e));

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
            Event::fire('orbit.inbox.getsearchinbox.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();

            if (Config::get('app.debug')) {
                $this->response->data = $e->__toString();
            } else {
                $this->response->data = null;
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.inbox.getsearchinbox.before.render', array($this, &$output));

        return $output;
    }

    /**
     * GET - List of alert
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @return Illuminate\Support\Facades\Response
     */
    public function getPollAlert()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.inbox.getalert.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.inbox.getalert.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            $mall_id = App::make('orbitSetting')->getSetting('current_retailer');

            $alerts = Inbox::where('user_id', $user->user_id)
                            ->where('merchant_id', $mall_id)
                            ->isNotRead();

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_alerts = clone $alerts;

            $totalAlerts = RecordCounter::create($_alerts)->count();
            $listOfAlerts = $alerts->count();

            $data = new stdclass();
            $data->total_records = $totalAlerts;
            $data->returned_records = count($listOfAlerts);
            $data->records = $listOfAlerts;

            if ($totalAlerts === 0) {
                $data->records = null;
                $this->response->message = 'No new alert';
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.inbox.getalert.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.inbox.getalert.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.inbox.getalert.query.error', array($this, $e));

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
            Event::fire('orbit.inbox.getalert.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();

            if (Config::get('app.debug')) {
                $this->response->data = $e->__toString();
            } else {
                $this->response->data = null;
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.inbox.getalert.before.render', array($this, &$output));

        return $output;
    }

    /**
     * POST - Change flag of the alert
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @return Illuminate\Support\Facades\Response
     */
    public function postReadAlert()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.inbox.postreadalert.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.inbox.postreadalert.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;

            $this->registerCustomValidation();

            $alertId = OrbitInput::post('inbox_id');
            $validator = Validator::make(
                array(
                    'inbox_id'             => $alertId,
                ),
                array(
                    'inbox_id'             => 'required|orbit.empty.alert',
                )
            );

            Event::fire('orbit.inbox.postreadalert.before.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.inbox.postreadalert.after.validation', array($this, $validator));

            $inbox = App::make('orbit.empty.alert');
            $inbox->is_read = 'Y';
            $inbox->save();

            Event::fire('orbit.inbox.postreadalert.after.save', array($this, $inbox));
            $this->response->message = 'Message has been flagged as read';
            $this->response->data = NULL;

            // Commit the changes
            $this->commit();

            Event::fire('orbit.inbox.postreadalert.after.commit', array($this, $inbox));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.inbox.postreadalert.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.inbox.postreadalert.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (QueryException $e) {
            Event::fire('orbit.inbox.postreadalert.query.error', array($this, $e));

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

            // Rollback the changes
            $this->rollBack();
        } catch (Exception $e) {
            Event::fire('orbit.inbox.postreadalert.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();

            if (Config::get('app.debug')) {
                $this->response->data = $e->__toString();
            } else {
                $this->response->data = null;
            }

            // Rollback the changes
            $this->rollBack();
        }

        return $this->render($httpCode);
    }

    /**
     * POST - Change status of the alert to deleted
     *
     * @author Ahmad <ahmad@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeleteAlert()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.inbox.postdeletedalert.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.inbox.postdeletedalert.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;

            $this->registerCustomValidation();

            $alertId = OrbitInput::post('inbox_id');
            $validator = Validator::make(
                array(
                    'inbox_id'             => $alertId,
                ),
                array(
                    'inbox_id'             => 'required|orbit.empty.alert',
                )
            );

            Event::fire('orbit.inbox.postdeletedalert.before.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.inbox.postdeletedalert.after.validation', array($this, $validator));

            $inbox = App::make('orbit.empty.alert');
            $inbox->status = 'deleted';
            $inbox->save();

            Event::fire('orbit.inbox.postdeletedalert.after.save', array($this, $inbox));
            $this->response->message = 'Message has been deleted.';
            $this->response->data = NULL;

            // Commit the changes
            $this->commit();

            Event::fire('orbit.inbox.postdeletedalert.after.commit', array($this, $inbox));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.inbox.postdeletedalert.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.inbox.postdeletedalert.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (QueryException $e) {
            Event::fire('orbit.inbox.postdeletedalert.query.error', array($this, $e));

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

            // Rollback the changes
            $this->rollBack();
        } catch (Exception $e) {
            Event::fire('orbit.inbox.postdeletedalert.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();

            if (Config::get('app.debug')) {
                $this->response->data = $e->__toString();
            } else {
                $this->response->data = null;
            }

            // Rollback the changes
            $this->rollBack();
        }

        return $this->render($httpCode);
    }

    protected function registerCustomValidation()
    {
        $user = $this->api->user;
        Validator::extend('orbit.empty.alert', function ($attribute, $value, $parameters) use ($user) {
            $alert = Inbox::active()
                          ->where('inbox_id', $value)
                          ->where('user_id', $user->user_id)
                          ->first();

            if (empty($alert)) {
                $errorMessage = sprintf('Alert not found.');
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            App::instance('orbit.empty.alert', $alert);

            return TRUE;
        });
    }
}
