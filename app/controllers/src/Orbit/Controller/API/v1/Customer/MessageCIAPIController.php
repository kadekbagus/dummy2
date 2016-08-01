<?php namespace Orbit\Controller\API\v1\Customer;

/**
 * @author Firmansyah <firmansyah@dominopos.com>
 * @desc Controller for get listing, detail and message
 */

use Orbit\Controller\API\v1\Customer\BaseAPIController;
use Orbit\Helper\Net\UrlChecker as UrlBlock;
use OrbitShop\API\v1\ResponseProvider;
use Helper\EloquentRecordCounter as RecordCounter;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use DominoPOS\OrbitSession\Session;
use DominoPOS\OrbitSession\SessionConfig;
use \Config;
use \Exception;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use \DB;
use \Carbon\Carbon as Carbon;
use \Validator;
use Mall;
use OrbitShop\API\v1\OrbitShopAPI;
use Activity;
use Setting;
use URL;
use App;
use User;
use Inbox;
use Language;
use Coupon;
use Event;
use \stdclass;


class MessageCIAPIController extends BaseAPIController
{

    /**
     * GET - Message list
     *
     * @param integer    `mall_id`        (required) - The Mall ID
     *
     * @return Illuminate\Support\Facades\Response
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     */

    protected $validRoles = ['super admin', 'consumer', 'guest'];
    protected $mall_id = null;

    public function getMessage()
    {
        $activity = Activity::mobileci()->setActivityType('view');
        $user = null;
        $mallId = null;
        $this->response = new ResponseProvider();

        try {
            $httpCode = 200;

            // Require authentication
            $this->registerCustomValidation();

            $mallId = OrbitInput::get('mall_id', null);

            $validator = Validator::make(
                array(
                    'mall_id' => $mallId,
                ),
                array(
                    'mall_id' => 'required|orbit.empty.mall',
                )
            );

            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }


            $user = $this->getLoggedInUser($mallId);

            $myMessage = Inbox::excludeDeleted()
                            ->select('inbox_id','subject','is_read','created_at')
                            ->where('user_id', $user->user_id)
                            ->where('merchant_id', $mallId)
                            ->isNotAlert()
                            ;

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_myMessage = clone $myMessage;

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
            $myMessage->take($take);

            $skip = 0;
            OrbitInput::get('skip', function($_skip) use (&$skip, $myMessage)
            {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            if (($take > 0) && ($skip > 0)) {
                $myMessage->skip($skip);
            }

            $myMessage->orderBy('created_at', 'desc');

            $totalAlerts = RecordCounter::create($_myMessage)->count();
            $listOfAlerts = $myMessage->get();

            $data = new stdclass();
            $data->total_records = $totalAlerts;
            $data->returned_records = count($listOfAlerts);
            $data->records = $listOfAlerts;


            if (empty($skip)) {
                $activityPageNotes = sprintf('Page viewed: %s', 'Notification List Page');
                $activity->setUser($user)
                    ->setActivityName('view_notification_list')
                    ->setActivityNameLong('View Notification List')
                    ->setObject(null)
                    ->setModuleName('Inbox')
                    ->setNotes($activityPageNotes)
                    ->responseOK()
                    ->save();
            }

            if ($totalAlerts === 0) {
                $data->records = null;
                $this->response->message = 'No results found';
            }

            $this->response->data = $data;
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
            $this->response->data = [$e->getFile(), $e->getLine(), $e->getMessage()];
            $httpCode = 500;
        }

        $output = $this->render($httpCode);

        return $output;
    }

    /**
     * GET - Message detail page
     *
     * @param integer    `mall_id`        (required) - The Mall ID
     * @param integer    `id`        (required) - The inbox ID
     *
     * @return Illuminate\View\View
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     */
    public function getMessageDetail()
    {
        $user = null;
        $activityPage = Activity::mobileci()
                        ->setActivityType('search');

        try {
            $httpCode = 200;

            $this->registerCustomValidation();

            $mallId = OrbitInput::get('mall_id', null);
            $inboxId = OrbitInput::get('id');

            $validator = Validator::make(
                array(
                    'mall_id' => $mallId,
                ),
                array(
                    'mall_id' => 'required|orbit.empty.mall',
                )
            );

            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $user = $this->getLoggedInUser($mallId);
            UrlBlock::checkBlockedUrl($user);
            $retailer = Mall::excludeDeleted()->where('merchant_id', $mallId)->first();
            // $languages = $this->getListLanguages($retailer);

            $inbox = Inbox::excludeDeleted()
                        ->where('user_id', $user->user_id)
                        ->where('merchant_id', $retailer->merchant_id)
                        ->where('inbox_id', $inboxId)
                        ->first();

            if (!empty($inbox)) {
                $inbox->is_read = 'Y';
                $inbox->save();

                switch ($inbox->inbox_type) {
                    case 'activation':
                        $activityPageNotes = sprintf('Page viewed: %s', 'Activation Notification Detail Page');
                        $activityPage->setUser($user)
                            ->setActivityName('read_notification')
                            ->setActivityNameLong('Read Activation Notification')
                            ->setObject($inbox)
                            ->setModuleName('Inbox')
                            ->setNotes($activityPageNotes)
                            ->responseOK()
                            ->save();
                        break;

                    case 'lucky_draw_issuance':
                        $activityPageNotes = sprintf('Page viewed: %s', 'Lucky Draw Number Issuance Notification Detail Page');
                        $activityPage->setUser($user)
                            ->setActivityName('read_notification')
                            ->setActivityNameLong('Read Lucky Draw Number Issuance Notification')
                            ->setObject($inbox)
                            ->setModuleName('Inbox')
                            ->setNotes($activityPageNotes)
                            ->responseOK()
                            ->save();
                        break;

                    case 'lucky_draw_blast':
                        $activityPageNotes = sprintf('Page viewed: %s', 'Lucky Draw Number Issuance Notification Detail Page');
                        $activityPage->setUser($user)
                            ->setActivityName('read_notification')
                            ->setActivityNameLong('Read Winner Announcement Notification')
                            ->setObject($inbox)
                            ->setModuleName('Inbox')
                            ->setNotes($activityPageNotes)
                            ->responseOK()
                            ->save();
                        break;

                    case 'coupon_issuance':
                        $activityPageNotes = sprintf('Page viewed: %s', 'Coupon Issuance Notification Detail Page');
                        $activityPage->setUser($user)
                            ->setActivityName('read_notification')
                            ->setActivityNameLong('Read Coupon Issuance Notification')
                            ->setObject($inbox)
                            ->setModuleName('Inbox')
                            ->setNotes($activityPageNotes)
                            ->responseOK()
                            ->save();
                        break;

                    default:
                        break;
                }
            }

            $data = new stdclass();
            if (count($inbox) === 0) {
                $data->records = null;
                $this->response->message = 'No results found';
            }

            $data = $inbox;
            $this->response->data = $data;
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
            $this->response->data = [$e->getFile(), $e->getLine(), $e->getMessage()];
            $httpCode = 500;
        }

        $output = $this->render($httpCode);

        return $output;
    }

    /**
     * POST - Change status of the message to deleted
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeleteMessage()
    {
        $user = null;
        $activityPage = Activity::mobileci()
                        ->setActivityType('search');

        try {
            $httpCode = 200;

            $this->registerCustomValidation();

            $mallId = OrbitInput::post('mall_id', null);
            $inboxId = OrbitInput::post('inbox_id');

            // validation mall id
            $validator = Validator::make(
                array('mall_id' => $mallId,),
                array('mall_id' => 'required|orbit.empty.mall',)
            );

            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $user = $this->getLoggedInUser($mallId);

            // validation mall id
            $validatorInboxId = Validator::make(
                array('inbox_id' => $inboxId),
                array('inbox_id' => 'required|orbit.empty.alert:' . $user->user_id)
            );

            if ($validatorInboxId->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            Event::fire('orbit.inbox.postdeletedalert.before.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.inbox.postdeletedalert.after.validation', array($this, $validator));

            $retailer = Mall::excludeDeleted()->where('merchant_id', $mallId)->first();

            $inbox = Inbox::where('user_id', $user->user_id)
                        ->where('merchant_id', $retailer->merchant_id)
                        ->where('inbox_id', $inboxId)
                        ->first();

            $inbox->is_read = 'Y';
            $inbox->status = 'deleted';
            $inbox->save();

            Event::fire('orbit.inbox.postdeletedalert.after.save', array($this, $inbox));
            $this->response->code = 0;
            $this->response->status = 'success';
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


    /**
     * POST -  Change flag of the alert on/off
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @return Illuminate\Support\Facades\Response
     */
    public function postReadUnreadMessage()
    {
        $user = null;
        $activityPage = Activity::mobileci()
                        ->setActivityType('search');

        try {
            $httpCode = 200;

            $this->registerCustomValidation();

            $mallId = OrbitInput::post('mall_id', null);
            $inboxId = OrbitInput::post('inbox_id');

            // validation mall id
            $validator = Validator::make(
                array('mall_id' => $mallId,),
                array('mall_id' => 'required|orbit.empty.mall',)
            );

            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $user = $this->getLoggedInUser($mallId);

            // validation mall id
            $validatorInboxId = Validator::make(
                array('inbox_id' => $inboxId),
                array('inbox_id' => 'required|orbit.empty.alert:' . $user->user_id)
            );

            if ($validatorInboxId->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            Event::fire('orbit.inbox.postreadalert.before.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.inbox.postreadalert.after.validation', array($this, $validator));

            $retailer = Mall::excludeDeleted()->where('merchant_id', $mallId)->first();

            $inbox = Inbox::where('user_id', $user->user_id)
                        ->where('merchant_id', $retailer->merchant_id)
                        ->where('inbox_id', $inboxId)
                        ->first();

            if ($inbox->is_read === 'Y') {
                $inbox->is_read = 'N';
            } else {
                $inbox->is_read = 'Y';
            }
            $inbox->save();

            $inbox = Inbox::where('user_id', $user->user_id)
                        ->where('merchant_id', $retailer->merchant_id)
                        ->where('inbox_id', $inboxId)
                        ->first();

            Event::fire('orbit.inbox.postreadalert.after.save', array($this, $inbox));

            $this->response->message = $inbox->is_read === 'Y' ? 'Message has been flagged as read' : 'Message has been flagged as unread';
            $this->response->data = $inbox->is_read === 'Y' ? 'read' : 'unread';

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
     * GET -  Get total unread message
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @return Illuminate\Support\Facades\Response
     */
    public function getPollMessages()
    {
        $user = null;
        $activityPage = Activity::mobileci()
                        ->setActivityType('search');

        try {

            $httpCode = 200;

            $this->registerCustomValidation();

            $mallId = OrbitInput::get('mall_id', null);

            // validation mall id
            $validator = Validator::make(
                array('mall_id' => $mallId,),
                array('mall_id' => 'required|orbit.empty.mall',)
            );

            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $user = $this->getLoggedInUser($mallId);
            $retailer = Mall::excludeDeleted()->where('merchant_id', $mallId)->first();

            $alerts = Inbox::where('user_id', $user->user_id)
                            ->where('merchant_id', $retailer->merchant_id)
                            ->isNotDeleted()
                            ->isNotRead()
                            ->isNotAlert();

            $untoastedAlerts = Inbox::where('user_id', $user->user_id)
                            ->where('merchant_id', $retailer->merchant_id)
                            ->isNotDeleted()
                            ->isNotNotified()
                            ->isNotAlert()
                            ->get();

            foreach ($untoastedAlerts as $untoastedAlert) {
                $untoastedAlert->url = URL::to('customer/message/detail?id=' . $untoastedAlert->inbox_id);
            }
            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_alerts = clone $alerts;

            $totalAlerts = RecordCounter::create($_alerts)->count();
            $listOfAlerts = $alerts->count();

            $data = new stdclass();
            $data->total_records = $totalAlerts;
            $data->returned_records = $listOfAlerts;
            $data->records = $listOfAlerts;
            $data->untoasted_records = $untoastedAlerts;

            if ($listOfAlerts > 9) {
                $data->records = '9+';
            }

            if ($totalAlerts === 0) {
                $data->records = null;
                $this->response->message = 'No new alert';
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.inbox.getpollmessage.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.inbox.getpollmessage.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (QueryException $e) {
            Event::fire('orbit.inbox.getpollmessage.query.error', array($this, $e));

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
            Event::fire('orbit.inbox.getpollmessage.general.exception', array($this, $e));

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
        // Check the existance of id_language_default
        Validator::extend('orbit.empty.language_default', function ($attribute, $value, $parameters) {

            $language = \Language::where('name', '=', $value)->first();

            if (empty($language)) {
                return false;
            }

            return true;
        });

        // Check the existance of merchant id
        Validator::extend('orbit.empty.mall', function ($attribute, $value, $parameters) {
            $mall = Mall::excludeDeleted()
                        ->where('merchant_id', $value)
                        ->first();

            if (empty($mall)) {
                return FALSE;
            }

            App::instance('orbit.empty.mall', $mall);

            return TRUE;
        });

        Validator::extend('orbit.empty.alert', function ($attribute, $value, $parameters){
            $userId = $parameters[0];

            $inbox = Inbox::active()
                          ->where('inbox_id', $value)
                          ->where('user_id', $userId)
                          ->first();

            if (empty($inbox)) {
                $errorMessage = 'Notification not found.';
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            App::instance('orbit.empty.alert', $inbox);

            return TRUE;
        });


    }

}