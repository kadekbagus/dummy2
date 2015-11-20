<?php
/**
 * An API controller for managing Event.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Helper\EloquentRecordCounter as RecordCounter;
use Carbon\Carbon as Carbon;

class EventAPIController extends ControllerAPI
{
    /**
     * POST - Create New Event
     *
     * @author Tian <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `merchant_id`           (required) - Merchant ID
     * @param string     `event_name`            (required) - Event name
     * @param string     `event_type`            (required) - Event type. Valid value: informative, link.
     * @param string     `status`                (required) - Status. Valid value: active, inactive, pending, blocked, deleted.
     * @param string     `description`           (optional) - Description
     * @param datetime   `begin_date`            (optional) - Begin date. Example: 2015-04-15 00:00:00
     * @param datetime   `end_date`              (optional) - End date. Example: 2015-04-18 23:59:59
     * @param string     `is_permanent`          (optional) - Is permanent. Valid value: Y, N.
     * @param file       `images`                (optional) - Event image
     * @param string     `link_object_type`      (optional) - Link object type. Valid value: retailer, retailer_category, promotion, news.
     * @param array      `retailer_ids`          (optional) - Retailer IDs
     * @param integer    `id_language_default`   (optional) - ID language default
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postNewEvent()
    {
        $activity = Activity::portal()
                            ->setActivityType('create');

        $user = NULL;
        $newevent = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.event.postnewevent.before.auth', array($this));

            $this->checkAuth();

            Event::fire('orbit.event.postnewevent.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.event.postnewevent.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('create_event')) {
                Event::fire('orbit.event.postnewevent.authz.notallowed', array($this, $user));
                $createEventLang = Lang::get('validation.orbit.actionlist.new_event');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $createEventLang));
                ACL::throwAccessForbidden($message);
            }
*/
            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = ['super admin', 'mall admin', 'mall owner'];
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.event.postnewevent.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $merchant_id = OrbitInput::post('current_mall');
            $event_name = OrbitInput::post('event_name');
            $event_type = OrbitInput::post('event_type');
            $status = OrbitInput::post('status');
            $description = OrbitInput::post('description');
            $begin_date = OrbitInput::post('begin_date');
            $end_date = OrbitInput::post('end_date');
            $is_permanent = OrbitInput::post('is_permanent');
            $link_object_type = OrbitInput::post('link_object_type');
            $id_language_default = OrbitInput::post('id_language_default');
            $retailer_ids = OrbitInput::post('retailer_ids');
            $retailer_ids = (array) $retailer_ids;

            $validator = Validator::make(
                array(
                    'current_mall'        => $merchant_id,
                    'event_name'          => $event_name,
                    'event_type'          => $event_type,
                    'status'              => $status,
                    'link_object_type'    => $link_object_type,
                    'begin_date'          => $begin_date,
                    'end_date'            => $end_date,
                    'id_language_default' => $id_language_default,
                ),
                array(
                    'current_mall'        => 'required|orbit.empty.merchant',
                    'event_name'          => 'required|max:255|orbit.exists.event_name',
                    'event_type'          => 'required|orbit.empty.event_type',
                    'status'              => 'required|orbit.empty.event_status',
                    'link_object_type'    => 'orbit.empty.link_object_type',
                    'begin_date'          => 'required|date',
                    'end_date'            => 'required|date',
                    'id_language_default' => 'required|orbit.empty.language_default',
                )
            );

            Event::fire('orbit.event.postnewevent.before.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            foreach ($retailer_ids as $retailer_id_check) {
                $validator = Validator::make(
                    array(
                        'retailer_id'   => $retailer_id_check,
                    ),
                    array(
                        'retailer_id'   => 'orbit.empty.link_object_id:'.$link_object_type,
                    )
                );

                Event::fire('orbit.event.postnewevent.before.retailervalidation', array($this, $validator));

                // Run the validation
                if ($validator->fails()) {
                    $errorMessage = $validator->messages()->first();
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }

                Event::fire('orbit.event.postnewevent.after.retailervalidation', array($this, $validator));
            }

            Event::fire('orbit.event.postnewevent.after.validation', array($this, $validator));

            // save Event
            $newevent = new EventModel();
            $newevent->merchant_id = $merchant_id;
            $newevent->event_name = $event_name;
            $newevent->event_type = $event_type;
            $newevent->status = $status;
            $newevent->description = $description;
            $newevent->begin_date = $begin_date;
            $newevent->end_date = $end_date;
            $newevent->is_permanent = $is_permanent;
            $newevent->link_object_type = $link_object_type;
            $newevent->created_by = $this->api->user->user_id;

            Event::fire('orbit.event.postnewevent.before.save', array($this, $newevent));

            $newevent->save();

            // save EventRetailer
            $eventretailers = array();
            foreach ($retailer_ids as $retailer_id) {
                $eventretailer = new EventRetailer();
                $eventretailer->retailer_id = $retailer_id;
                $eventretailer->event_id = $newevent->event_id;
                $eventretailer->object_type = $link_object_type;
                $eventretailer->save();
                $eventretailers[] = $eventretailer;
            }
            $newevent->retailers = $eventretailers;

            Event::fire('orbit.event.postnewevent.after.save', array($this, $newevent));

            // @author Irianto Pratama <irianto@dominopos.com>
            $default_translation = [
                $id_language_default => [
                    'event_name' => $newevent->event_name,
                    'description' => $newevent->description
                ]
            ];
            $this->validateAndSaveTranslations($newevent, json_encode($default_translation), 'create');

            OrbitInput::post('translations', function($translation_json_string) use ($newevent) {
                $this->validateAndSaveTranslations($newevent, $translation_json_string, 'create');
            });

            $this->response->data = $newevent;

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activityNotes = sprintf('Event Created: %s', $newevent->event_name);
            $activity->setUser($user)
                    ->setActivityName('create_event')
                    ->setActivityNameLong('Create Event OK')
                    ->setObject($newevent)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.event.postnewevent.after.commit', array($this, $newevent));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.event.postnewevent.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_event')
                    ->setActivityNameLong('Create Event Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.event.postnewevent.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_event')
                    ->setActivityNameLong('Create Event Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.event.postnewevent.query.error', array($this, $e));

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

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_event')
                    ->setActivityNameLong('Create Event Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.event.postnewevent.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_event')
                    ->setActivityNameLong('Create Event Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save the activity
        $activity->save();

        return $this->render($httpCode);
    }

    /**
     * POST - Update Event
     *
     * @author <Tian> <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `event_id`              (required) - Event ID
     * @param integer    `merchant_id`           (optional) - Merchant ID
     * @param string     `event_name`            (optional) - Event name
     * @param string     `event_type`            (optional) - Event type. Valid value: informative, link.
     * @param string     `status`                (optional) - Status. Valid value: active, inactive, pending, blocked, deleted.
     * @param string     `description`           (optional) - Description
     * @param datetime   `begin_date`            (optional) - Begin date. Example: 2014-12-30 00:00:00
     * @param datetime   `end_date`              (optional) - End date. Example: 2014-12-31 23:59:59
     * @param string     `is_permanent`          (optional) - Is permanent. Valid value: Y, N.
     * @param file       `images`                (optional) - Event image
     * @param string     `link_object_type`      (optional) - Link object type. Valid value: retailer, retailer_category, promotion, news.
     * @param string     `no_retailer`           (optional) - Flag to delete all ORID links. Valid value: Y.
     * @param array      `retailer_ids`          (optional) - Retailer IDs
     * @param integer    `id_language_default`   (optional) - ID language default
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postUpdateEvent()
    {
        $activity = Activity::portal()
                           ->setActivityType('update');

        $user = NULL;
        $updatedevent = NULL;
        try {
            $httpCode=200;

            Event::fire('orbit.event.postupdateevent.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.event.postupdateevent.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.event.postupdateevent.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('update_event')) {
                Event::fire('orbit.event.postupdateevent.authz.notallowed', array($this, $user));
                $updateEventLang = Lang::get('validation.orbit.actionlist.update_event');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $updateEventLang));
                ACL::throwAccessForbidden($message);
            }
*/
            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = ['super admin', 'mall admin', 'mall owner'];
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.event.postupdateevent.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $event_id = OrbitInput::post('event_id');
            $merchant_id = OrbitInput::post('current_mall');
            $event_type = OrbitInput::post('event_type');
            $status = OrbitInput::post('status');
            $link_object_type = OrbitInput::post('link_object_type');
            $end_date = OrbitInput::post('end_date');
            $id_language_default = OrbitInput::post('id_language_default');

            $data = array(
                'event_id'            => $event_id,
                'current_mall'        => $merchant_id,
                'event_type'          => $event_type,
                'status'              => $status,
                'link_object_type'    => $link_object_type,
                'end_date'            => $end_date,
                'id_language_default' => $id_language_default,
            );

            // Validate event_name only if exists in POST.
            OrbitInput::post('event_name', function($event_name) use (&$data) {
                $data['event_name'] = $event_name;
            });

            $validator = Validator::make(
                $data,
                array(
                    'event_id'            => 'required|orbit.empty.event',
                    'current_mall'        => 'orbit.empty.merchant',
                    'event_name'          => 'sometimes|required|min:5|max:255|event_name_exists_but_me',
                    'event_type'          => 'orbit.empty.event_type',
                    'status'              => 'orbit.empty.event_status',
                    'link_object_type'    => 'orbit.empty.link_object_type',
                    'end_date'            => 'date',
                    'id_language_default' => 'required|orbit.empty.language_default',
                ),
                array(
                   'event_name_exists_but_me' => Lang::get('validation.orbit.exists.event_name'),
                )
            );

            Event::fire('orbit.event.postupdateevent.before.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.event.postupdateevent.after.validation', array($this, $validator));

            $updatedevent = EventModel::with('retailers', 'retailerCategories', 'promotions', 'news')->excludeDeleted()->where('event_id', $event_id)->first();

            // save Event
            OrbitInput::post('merchant_id', function($merchant_id) use ($updatedevent) {
                $updatedevent->merchant_id = $merchant_id;
            });

            OrbitInput::post('event_name', function($event_name) use ($updatedevent) {
                $updatedevent->event_name = $event_name;
            });

            OrbitInput::post('event_type', function($event_type) use ($updatedevent) {
                $updatedevent->event_type = $event_type;
            });

            OrbitInput::post('status', function($status) use ($updatedevent) {
                $updatedevent->status = $status;
            });

            OrbitInput::post('description', function($description) use ($updatedevent) {
                $updatedevent->description = $description;
            });

            OrbitInput::post('begin_date', function($begin_date) use ($updatedevent) {
                $updatedevent->begin_date = $begin_date;
            });

            OrbitInput::post('end_date', function($end_date) use ($updatedevent) {
                $updatedevent->end_date = $end_date;
            });

            OrbitInput::post('is_permanent', function($is_permanent) use ($updatedevent) {
                $updatedevent->is_permanent = $is_permanent;
            });

            OrbitInput::post('link_object_type', function($link_object_type) use ($updatedevent) {
                if (trim($link_object_type) === '') {
                    $link_object_type = NULL;
                }
                $updatedevent->link_object_type = $link_object_type;
            });

            // @author Irianto Pratama <irianto@dominopos.com>
            $default_translation = [
                $id_language_default => [
                    'event_name' => $updatedevent->event_name,
                    'description' => $updatedevent->description
                ]
            ];
            $this->validateAndSaveTranslations($updatedevent, json_encode($default_translation), 'update');

            OrbitInput::post('translations', function($translation_json_string) use ($updatedevent) {
                $this->validateAndSaveTranslations($updatedevent, $translation_json_string, 'update');
            });

            $updatedevent->modified_by = $this->api->user->user_id;
            $updatedevent->touch();

            Event::fire('orbit.event.postupdateevent.before.save', array($this, $updatedevent));

            $updatedevent->save();

            // save EventRetailer
            OrbitInput::post('no_retailer', function($no_retailer) use ($updatedevent) {
                if ($no_retailer == 'Y') {
                    $deleted_retailer_ids = EventRetailer::where('event_id', $updatedevent->event_id)->get(array('retailer_id'))->toArray();

                    // delete retailers
                    $updatedevent->retailers()->detach($deleted_retailer_ids);
                    $updatedevent->load('retailers');
                    // delete retailer categories
                    $updatedevent->retailerCategories()->detach($deleted_retailer_ids);
                    $updatedevent->load('retailerCategories');
                    // delete promotions
                    $updatedevent->promotions()->detach($deleted_retailer_ids);
                    $updatedevent->load('promotions');
                    // delete news
                    $updatedevent->news()->detach($deleted_retailer_ids);
                    $updatedevent->load('news');
                }
            });

            OrbitInput::post('retailer_ids', function($retailer_ids) use ($updatedevent, $link_object_type) {
                // validate retailer_ids
                $retailer_ids = (array) $retailer_ids;
                foreach ($retailer_ids as $retailer_id_check) {
                    $validator = Validator::make(
                        array(
                            'retailer_id'   => $retailer_id_check,
                        ),
                        array(
                            'retailer_id'   => 'orbit.empty.link_object_id:'.$link_object_type,
                        )
                    );

                    Event::fire('orbit.event.postupdateevent.before.retailervalidation', array($this, $validator));

                    // Run the validation
                    if ($validator->fails()) {
                        $errorMessage = $validator->messages()->first();
                        OrbitShopAPI::throwInvalidArgument($errorMessage);
                    }

                    Event::fire('orbit.event.postupdateevent.after.retailervalidation', array($this, $validator));
                }
                // sync new set of retailer ids
                if ($link_object_type === 'retailer') {
                    $pivotData = array_fill(0, count($retailer_ids), ['object_type' => 'retailer']);
                    $syncData = array_combine($retailer_ids, $pivotData);
                    $updatedevent->retailers()->sync($syncData);
                } elseif ($link_object_type === 'retailer_category') {
                    $pivotData = array_fill(0, count($retailer_ids), ['object_type' => 'retailer_category']);
                    $syncData = array_combine($retailer_ids, $pivotData);
                    $updatedevent->retailerCategories()->sync($syncData);
                } elseif ($link_object_type === 'promotion') {
                    $pivotData = array_fill(0, count($retailer_ids), ['object_type' => 'promotion']);
                    $syncData = array_combine($retailer_ids, $pivotData);
                    $updatedevent->promotions()->sync($syncData);
                } elseif ($link_object_type === 'news') {
                    $pivotData = array_fill(0, count($retailer_ids), ['object_type' => 'news']);
                    $syncData = array_combine($retailer_ids, $pivotData);
                    $updatedevent->news()->sync($syncData);
                }

                $updatedevent->load('retailers');
                $updatedevent->load('retailerCategories');
                $updatedevent->load('promotions');
                $updatedevent->load('news');

            });

            Event::fire('orbit.event.postupdateevent.after.save', array($this, $updatedevent));
            $this->response->data = $updatedevent;

            // Commit the changes
            $this->commit();

            // Successfull Update
            $activityNotes = sprintf('Event updated: %s', $updatedevent->event_name);
            $activity->setUser($user)
                    ->setActivityName('update_event')
                    ->setActivityNameLong('Update Event OK')
                    ->setObject($updatedevent)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.event.postupdateevent.after.commit', array($this, $updatedevent));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.event.postupdateevent.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_event')
                    ->setActivityNameLong('Update Event Failed')
                    ->setObject($updatedevent)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.event.postupdateevent.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_event')
                    ->setActivityNameLong('Update Event Failed')
                    ->setObject($updatedevent)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.event.postupdateevent.query.error', array($this, $e));

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

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_event')
                    ->setActivityNameLong('Update Event Failed')
                    ->setObject($updatedevent)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.event.postupdateevent.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_event')
                    ->setActivityNameLong('Update Event Failed')
                    ->setObject($updatedevent)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save activity
        $activity->save();

        return $this->render($httpCode);

    }

    /**
     * POST - Delete Event
     *
     * @author Tian <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `event_id`                  (required) - ID of the event
     * @param string     `password`                  (required) - master password
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeleteEvent()
    {
        $activity = Activity::portal()
                          ->setActivityType('delete');

        $user = NULL;
        $deleteevent = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.event.postdeleteevent.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.event.postdeleteevent.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.event.postdeleteevent.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('delete_event')) {
                Event::fire('orbit.event.postdeleteevent.authz.notallowed', array($this, $user));
                $deleteEventLang = Lang::get('validation.orbit.actionlist.delete_event');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $deleteEventLang));
                ACL::throwAccessForbidden($message);
            }
*/
            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = ['super admin', 'mall admin', 'mall owner'];
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.event.postdeleteevent.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $event_id = OrbitInput::post('event_id');
            $password = OrbitInput::post('password');
            $mall_id = OrbitInput::post('current_mall');

            $validator = Validator::make(
                array(
                    'current_mall' => $mall_id,
                    'event_id' => $event_id,
                    'password' => $password,
                ),
                array(
                    'current_mall' => 'required|orbit.empty.mall',
                    'event_id' => 'required|orbit.empty.event',
                    'password'    => [
                        'required',
                        ['orbit.masterpassword.delete', $mall_id]
                    ],
                ),
                array(
                    'required.password'             => 'The master is password is required.',
                    'orbit.masterpassword.delete'   => 'The password is incorrect.'
                )
            );

            Event::fire('orbit.event.postdeleteevent.before.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.event.postdeleteevent.after.validation', array($this, $validator));

            $deleteevent = EventModel::excludeDeleted()->where('event_id', $event_id)->first();
            $deleteevent->status = 'deleted';
            $deleteevent->modified_by = $this->api->user->user_id;

            Event::fire('orbit.event.postdeleteevent.before.save', array($this, $deleteevent));

            // hard delete event-retailer.
            $deleteeventretailers = EventRetailer::where('event_id', $deleteevent->event_id)->get();
            foreach ($deleteeventretailers as $deleteeventretailer) {
                $deleteeventretailer->delete();
            }

            foreach ($deleteevent->translations as $translation) {
                $translation->modified_by = $this->api->user->user_id;
                $translation->delete();
            }

            $deleteevent->save();

            Event::fire('orbit.event.postdeleteevent.after.save', array($this, $deleteevent));
            $this->response->data = null;
            $this->response->message = Lang::get('statuses.orbit.deleted.event');

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activityNotes = sprintf('Event Deleted: %s', $deleteevent->event_name);
            $activity->setUser($user)
                    ->setActivityName('delete_event')
                    ->setActivityNameLong('Delete Event OK')
                    ->setObject($deleteevent)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.event.postdeleteevent.after.commit', array($this, $deleteevent));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.event.postdeleteevent.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_event')
                    ->setActivityNameLong('Delete Event Failed')
                    ->setObject($deleteevent)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.event.postdeleteevent.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_event')
                    ->setActivityNameLong('Delete Event Failed')
                    ->setObject($deleteevent)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.event.postdeleteevent.query.error', array($this, $e));

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

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_event')
                    ->setActivityNameLong('Delete Event Failed')
                    ->setObject($deleteevent)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.event.postdeleteevent.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_event')
                    ->setActivityNameLong('Delete Event Failed')
                    ->setObject($deleteevent)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        $output = $this->render($httpCode);

        // Save the activity
        $activity->save();

        return $output;
    }

    /**
     * GET - Search Event
     *
     * @author Tian <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string   `with`                  (optional) - Valid value: retailers, retailer_categories, promotions, news.
     * @param string   `sortby`                (optional) - Column order by. Valid value: registered_date, event_name, event_type, description, begin_date, end_date, is_permanent, status.
     * @param string   `sortmode`              (optional) - asc or desc
     * @param integer  `take`                  (optional) - limit
     * @param integer  `skip`                  (optional) - limit offset
     * @param integer  `event_id`              (optional) - Event ID
     * @param integer  `merchant_id`           (optional) - Merchant ID
     * @param string   `event_name`            (optional) - Event name
     * @param string   `event_name_like`       (optional) - Event name like
     * @param string   `event_type`            (optional) - Event type. Valid value: informative, link.
     * @param string   `description`           (optional) - Description
     * @param string   `description_like`      (optional) - Description like
     * @param datetime `begin_date`            (optional) - Begin date. Example: 2014-12-30 00:00:00
     * @param datetime `end_date`              (optional) - End date. Example: 2014-12-31 23:59:59
     * @param string   `is_permanent`          (optional) - Is permanent. Valid value: Y, N.
     * @param string   `status`                (optional) - Status. Valid value: active, inactive, pending, blocked, deleted.
     * @param string   `link_object_type`      (optional) - Link object type. Valid value: retailer, retailer_category, promotion, news.
     * @param integer  `retailer_id`           (optional) - Retailer IDs
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchEvent()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.event.getsearchevent.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.event.getsearchevent.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.event.getsearchevent.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('view_event')) {
                Event::fire('orbit.event.getsearchevent.authz.notallowed', array($this, $user));
                $viewEventLang = Lang::get('validation.orbit.actionlist.view_event');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewEventLang));
                ACL::throwAccessForbidden($message);
            }
*/
            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = ['super admin', 'mall admin', 'mall owner'];
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.event.getsearchevent.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sortby');
            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                ),
                array(
                    'sort_by' => 'in:registered_date,event_name,event_type,description,begin_date,end_date,updated_at,is_permanent,status',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.event_sortby'),
                )
            );

            Event::fire('orbit.event.getsearchevent.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.event.getsearchevent.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.event.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.event.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            // Builder object
            $events = EventModel::excludeDeleted();

            // Filter event by Ids
            OrbitInput::get('event_id', function($eventIds) use ($events)
            {
                $events->whereIn('events.event_id', $eventIds);
            });

            // Filter event by merchant Ids
            OrbitInput::get('merchant_id', function ($merchantIds) use ($events) {
                $events->whereIn('events.merchant_id', $merchantIds);
            });

            // Filter event by event name
            OrbitInput::get('event_name', function($eventname) use ($events)
            {
                $events->whereIn('events.event_name', $eventname);
            });

            // Filter event by matching event name pattern
            OrbitInput::get('event_name_like', function($eventname) use ($events)
            {
                $events->where('events.event_name', 'like', "%$eventname%");
            });

            // Filter event by event type
            OrbitInput::get('event_type', function($eventTypes) use ($events)
            {
                $events->whereIn('events.event_type', $eventTypes);
            });

            // Filter event by description
            OrbitInput::get('description', function($description) use ($events)
            {
                $events->whereIn('events.description', $description);
            });

            // Filter event by matching description pattern
            OrbitInput::get('description_like', function($description) use ($events)
            {
                $events->where('events.description', 'like', "%$description%");
            });

            // Filter event by begin date
            OrbitInput::get('begin_date', function($begindate) use ($events)
            {
                $events->where('events.begin_date', '<=', $begindate);
            });

            // Filter event by end date
            OrbitInput::get('end_date', function($enddate) use ($events)
            {
                $events->where('events.end_date', '>=', $enddate);
            });

            // Filter event by is permanent
            OrbitInput::get('is_permanent', function ($ispermanent) use ($events) {
                $events->whereIn('events.is_permanent', $ispermanent);
            });

            // Filter event by status
            OrbitInput::get('status', function ($statuses) use ($events) {
                $events->whereIn('events.status', $statuses);
            });

            // Filter event by link object type
            OrbitInput::get('link_object_type', function ($linkObjectTypes) use ($events) {
                $events->whereIn('events.link_object_type', $linkObjectTypes);
            });

            // Filter event retailer by retailer id
            OrbitInput::get('retailer_id', function ($retailerIds) use ($events) {
                $events->whereHas('retailers', function($q) use ($retailerIds) {
                    $q->whereIn('retailer_id', $retailerIds);
                });
            });

            // Add new relation based on request
            OrbitInput::get('with', function ($with) use ($events) {
                $with = (array) $with;

                foreach ($with as $relation) {
                    if ($relation === 'retailers') {
                        $events->with('retailers');
                    } elseif ($relation === 'retailer_categories') {
                        $events->with('retailerCategories');
                    } elseif ($relation === 'promotion') {
                        $events->with('promotion');
                    } elseif ($relation === 'news') {
                        $events->with('news');
                    } elseif ($relation === 'translations') {
                        $events->with('translations');
                    } elseif ($relation === 'translations.media') {
                        $events->with('translations.media');
                    }
                }
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_events = clone $events;

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
            $events->take($take);

            $skip = 0;
            OrbitInput::get('skip', function($_skip) use (&$skip, $events)
            {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $events->skip($skip);

            // Default sort by
            $sortBy = 'events.event_name';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'registered_date'   => 'events.created_at',
                    'event_name'        => 'events.event_name',
                    'event_type'        => 'events.event_type',
                    'description'       => 'events.description',
                    'begin_date'        => 'events.begin_date',
                    'end_date'          => 'events.end_date',
                    'updated_at'        => 'events.updated_at',
                    'is_permanent'      => 'events.is_permanent',
                    'status'            => 'events.status'
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            if ($sortBy !== 'events.status') {
                $events->orderBy('events.status', 'asc');
            }

            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });
            $events->orderBy($sortBy, $sortMode);

            $totalEvents = RecordCounter::create($_events)->count();
            $listOfEvents = $events->get();

            $data = new stdclass();
            $data->total_records = $totalEvents;
            $data->returned_records = count($listOfEvents);
            $data->records = $listOfEvents;

            if ($totalEvents === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.event');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.event.getsearchevent.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.event.getsearchevent.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.event.getsearchevent.query.error', array($this, $e));

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
            Event::fire('orbit.event.getsearchevent.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.event.getsearchevent.before.render', array($this, &$output));

        return $output;
    }

    /**
     * GET - Search Event - List By Retailer
     *
     * @author Tian <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string   `sortby`                (optional) - column order by. Valid value: retailer_name, registered_date, event_name, event_type, description, begin_date, end_date, is_permanent, status.
     * @param string   `sortmode`              (optional) - asc or desc
     * @param integer  `take`                  (optional) - limit
     * @param integer  `skip`                  (optional) - limit offset
     * @param integer  `event_id`              (optional) - Event ID
     * @param integer  `merchant_id`           (optional) - Merchant ID
     * @param string   `event_name`            (optional) - Event name
     * @param string   `event_name_like`       (optional) - Event name like
     * @param string   `event_type`            (optional) - Event type. Valid value: informative, link.
     * @param string   `description`           (optional) - Description
     * @param string   `description_like`      (optional) - Description like
     * @param datetime `begin_date`            (optional) - Begin date. Example: 2015-1-31 13:00:00
     * @param datetime `end_date`              (optional) - End date. Example: 2015-1-31 13:00:00
     * @param string   `is_permanent`          (optional) - Is permanent. Valid value: Y, N.
     * @param string   `status`                (optional) - Status. Valid value: active, inactive, pending, blocked, deleted.
     * @param string   `link_object_type`      (optional) - Link object type. Valid value: product, family, promotion, widget.
     * @param integer  `link_object_id1`       (optional) - Link object ID1 (product_id or category_id1 or promotion_id or widget_id).
     * @param integer  `link_object_id2`       (optional) - Link object ID2 (category_id2).
     * @param integer  `link_object_id3`       (optional) - Link object ID3 (category_id3).
     * @param integer  `link_object_id4`       (optional) - Link object ID4 (category_id4).
     * @param integer  `link_object_id5`       (optional) - Link object ID5 (category_id5).
     * @param string   `widget_object_type`    (optional) - Widget object type.
     * @param string   `city`                  (optional) - City name
     * @param string   `city_like`             (optional) - City name like
     * @param integer  `retailer_id`           (optional) - Retailer IDs
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchEventByRetailer()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.event.getsearcheventbyretailer.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.event.getsearcheventbyretailer.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.event.getsearcheventbyretailer.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('view_event')) {
                Event::fire('orbit.event.getsearcheventbyretailer.authz.notallowed', array($this, $user));
                $viewEventLang = Lang::get('validation.orbit.actionlist.view_event');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewEventLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.event.getsearcheventbyretailer.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sortby');
            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                ),
                array(
                    'sort_by' => 'in:retailer_name,registered_date,event_name,event_type,description,begin_date,end_date,updated_at,is_permanent,status',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.event_by_retailer_sortby'),
                )
            );

            Event::fire('orbit.event.getsearcheventbyretailer.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.event.getsearcheventbyretailer.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int)Config::get('orbit.pagination.max_record');
            if ($maxRecord <= 0) {
                $maxRecord = 20;
            }

            // Builder object
            $events = EventModel::join('merchants', 'events.merchant_id', '=', 'merchants.merchant_id')
                ->select('merchants.name AS retailer_name', 'events.*')
                // ->where('events.status', '!=', 'deleted');
                ->where('events.status', '=', 'active');

            $mallTime = Carbon::now();
            if (empty(OrbitInput::get('begin_date')) && empty(OrbitInput::get('end_date'))) {
                $events->whereRaw("? between begin_date and end_date", [$mallTime]);
            }

            // Filter event by Ids
            OrbitInput::get('event_id', function($eventIds) use ($events)
            {
                $events->whereIn('events.event_id', $eventIds);
            });

            // Filter event by merchant Ids
            OrbitInput::get('merchant_id', function ($merchantIds) use ($events) {
                $events->whereIn('events.merchant_id', $merchantIds);
            });

            // Filter event by event name
            OrbitInput::get('event_name', function($eventname) use ($events)
            {
                $events->whereIn('events.event_name', $eventname);
            });

            // Filter event by matching event name pattern
            OrbitInput::get('event_name_like', function($eventname) use ($events)
            {
                $events->where('events.event_name', 'like', "%$eventname%");
            });

            // Filter event by event type
            OrbitInput::get('event_type', function($eventTypes) use ($events)
            {
                $events->whereIn('events.event_type', $eventTypes);
            });

            // Filter event by description
            OrbitInput::get('description', function($description) use ($events)
            {
                $events->whereIn('events.description', $description);
            });

            // Filter event by matching description pattern
            OrbitInput::get('description_like', function($description) use ($events)
            {
                $events->where('events.description', 'like', "%$description%");
            });

            // Filter event by begin date
            OrbitInput::get('begin_date', function($begindate) use ($events)
            {
                $events->where('events.begin_date', '<=', $begindate);
            });

            // Filter event by end date
            OrbitInput::get('end_date', function($enddate) use ($events)
            {
                $events->where('events.end_date', '>=', $enddate);
            });

            // Filter event by is permanent
            OrbitInput::get('is_permanent', function ($ispermanent) use ($events) {
                $events->whereIn('events.is_permanent', $ispermanent);
            });

            // Filter event by status
            OrbitInput::get('status', function ($statuses) use ($events) {
                $events->whereIn('events.status', $statuses);
            });

            // Filter event by link object type
            OrbitInput::get('link_object_type', function ($linkObjectTypes) use ($events) {
                $events->whereIn('events.link_object_type', $linkObjectTypes);
            });

            // Filter event by link object id1
            OrbitInput::get('link_object_id1', function ($linkObjectId1s) use ($events) {
                $events->whereIn('events.link_object_id1', $linkObjectId1s);
            });

            // Filter event by link object id2
            OrbitInput::get('link_object_id2', function ($linkObjectId2s) use ($events) {
                $events->whereIn('events.link_object_id2', $linkObjectId2s);
            });

            // Filter event by link object id3
            OrbitInput::get('link_object_id3', function ($linkObjectId3s) use ($events) {
                $events->whereIn('events.link_object_id3', $linkObjectId3s);
            });

            // Filter event by link object id4
            OrbitInput::get('link_object_id4', function ($linkObjectId4s) use ($events) {
                $events->whereIn('events.link_object_id4', $linkObjectId4s);
            });

            // Filter event by link object id5
            OrbitInput::get('link_object_id5', function ($linkObjectId5s) use ($events) {
                $events->whereIn('events.link_object_id5', $linkObjectId5s);
            });

            // Filter event by widget object type
            OrbitInput::get('widget_object_type', function ($widgetObjectTypes) use ($events) {
                $events->whereIn('events.widget_object_type', $widgetObjectTypes);
            });

            // Filter event by city
            OrbitInput::get('city', function($city) use ($events)
            {
                $events->whereIn('merchants.city', $city);
            });

            // Filter event by matching city pattern
            OrbitInput::get('city_like', function($city) use ($events)
            {
                $events->where('merchants.city', 'like', "%$city%");
            });

            // Filter event by retailer Ids
            OrbitInput::get('retailer_id', function ($retailerIds) use ($events) {
                $events->whereIn('event_retailer.retailer_id', $retailerIds);
            });

            OrbitInput::get('with', function ($with) use ($events) {
                $with = (array) $with;

                foreach ($with as $relation) {
                    if ($relation === 'translations') {
                        $events->with('translations');
                    } elseif ($relation === 'translations.media') {
                        $events->with('translations.media');
                    }
                }
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_events = clone $events;

            // Get the take args
            if (trim(OrbitInput::get('take')) === '') {
                $take = $maxRecord;
            } else {
                OrbitInput::get('take', function($_take) use (&$take, $maxRecord)
                {
                    if ($_take > $maxRecord) {
                        $_take = $maxRecord;
                    }
                    $take = $_take;
                });
            }
            if ($take > 0) {
                $events->take($take);
            }

            $skip = 0;
            OrbitInput::get('skip', function($_skip) use (&$skip, $events)
            {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            if (($take > 0) && ($skip > 0)) {
                $events->skip($skip);
            }

            // Default sort by
            $sortBy = 'retailer_name';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'retailer_name'     => 'retailer_name',
                    'registered_date'   => 'events.created_at',
                    'event_name'        => 'events.event_name',
                    'event_type'        => 'events.event_type',
                    'description'       => 'events.description',
                    'begin_date'        => 'events.begin_date',
                    'end_date'          => 'events.end_date',
                    'updated_at'        => 'events.updated_at',
                    'is_permanent'      => 'events.is_permanent',
                    'status'            => 'events.status'
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });
            $events->orderBy($sortBy, $sortMode);

            $totalEvents = $_events->count();
            $listOfEvents = $events->get();

            $data = new stdclass();
            $data->total_records = $totalEvents;
            $data->returned_records = count($listOfEvents);
            $data->records = $listOfEvents;

            if ($totalEvents === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.event');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.event.getsearcheventbyretailer.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.event.getsearcheventbyretailer.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.event.getsearcheventbyretailer.query.error', array($this, $e));

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
            Event::fire('orbit.event.getsearcheventbyretailer.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.event.getsearcheventbyretailer.before.render', array($this, &$output));

        return $output;
    }

    protected function registerCustomValidation()
    {
        $user = $this->api->user;

        // Check the existance of mall id
        Validator::extend('orbit.empty.mall', function ($attribute, $value, $parameters) use ($user){
            $mall = Mall::excludeDeleted()
                        ->where('merchant_id', $value)
                        ->first();

            if (empty($mall)) {
                return FALSE;
            }

            App::instance('orbit.empty.mall', $mall);

            return TRUE;
        });

        // Check the existance of id_language_default
        Validator::extend('orbit.empty.language_default', function ($attribute, $value, $parameters) {
            $news = MerchantLanguage::excludeDeleted()
                        ->where('merchant_language_id', $value)
                        ->first();

            if (empty($news)) {
                return FALSE;
            }

            App::instance('orbit.empty.language_default', $news);

            return TRUE;
        });

        // Check the existance of event id
        Validator::extend('orbit.empty.event', function ($attribute, $value, $parameters) {
            $event = EventModel::excludeDeleted()
                        ->where('event_id', $value)
                        ->first();

            if (empty($event)) {
                return FALSE;
            }

            App::instance('orbit.empty.event', $event);

            return TRUE;
        });

        // Check the existance of merchant id
        Validator::extend('orbit.empty.merchant', function ($attribute, $value, $parameters) {
            $merchant = Mall::excludeDeleted()
                        ->where('merchant_id', $value)
                        ->where('is_mall', 'yes')
                        ->first();

            if (empty($merchant)) {
                return FALSE;
            }

            App::instance('orbit.empty.merchant', $merchant);

            return TRUE;
        });

        // Check event name, it should not exists
        Validator::extend('orbit.exists.event_name', function ($attribute, $value, $parameters) {
            $merchant_id = OrbitInput::post('current_mall');

            $eventName = EventModel::excludeDeleted()
                        ->where('event_name', $value)
                        ->where('merchant_id', $merchant_id)
                        ->first();

            if (! empty($eventName)) {
                return FALSE;
            }

            App::instance('orbit.validation.event_name', $eventName);

            return TRUE;
        });

        // Check event name, it should not exists (for update)
        Validator::extend('event_name_exists_but_me', function ($attribute, $value, $parameters) {
            $event_id = trim(OrbitInput::post('event_id'));
            $merchant_id = OrbitInput::post('current_mall');

            $event = EventModel::excludeDeleted()
                        ->where('event_name', $value)
                        ->where('event_id', '!=', $event_id)
                        ->where('merchant_id', $merchant_id)
                        ->first();

            if (! empty($event)) {
                return FALSE;
            }

            App::instance('orbit.validation.event_name', $event);

            return TRUE;
        });

        // Check the existence of the event status
        Validator::extend('orbit.empty.event_status', function ($attribute, $value, $parameters) {
            $valid = false;
            $statuses = array('active', 'inactive', 'pending', 'blocked', 'deleted');
            foreach ($statuses as $status) {
                if($value === $status) $valid = $valid || TRUE;
            }

            return $valid;
        });

        // Check the existence of the event type
        Validator::extend('orbit.empty.event_type', function ($attribute, $value, $parameters) {
            $valid = false;
            $eventtypes = array('informative', 'link');
            foreach ($eventtypes as $eventtype) {
                if($value === $eventtype) $valid = $valid || TRUE;
            }

            return $valid;
        });

        // Check the existence of the link object type
        Validator::extend('orbit.empty.link_object_type', function ($attribute, $value, $parameters) {
            $valid = false;
            $linkobjecttypes = array('retailer', 'retailer_category', 'promotion', 'news');
            foreach ($linkobjecttypes as $linkobjecttype) {
                if($value === $linkobjecttype) $valid = $valid || TRUE;
            }

            return $valid;
        });

        // Check the existance of link_object_id
        Validator::extend('orbit.empty.link_object_id', function ($attribute, $value, $parameters) {
            $link_object_type = trim($parameters[0]);

            if ($link_object_type === 'retailer') {
                $linkObject = Tenant::excludeDeleted()
                            ->where('merchant_id', $value)
                            ->first();
            } elseif ($link_object_type === 'retailer_category') {
                $linkObject = Category::excludeDeleted()
                            ->where('category_id', $value)
                            ->first();
            } elseif ($link_object_type === 'promotion') {
                $linkObject = News::excludeDeleted()
                            ->where('news_id', $value)
                            ->where('object_type', 'promotion')
                            ->first();
            } elseif ($link_object_type === 'news') {
                $linkObject = News::excludeDeleted()
                            ->where('news_id', $value)
                            ->where('object_type', 'news')
                            ->first();
            }

            if (empty($linkObject)) {
                return FALSE;
            }

            App::instance('orbit.empty.link_object_id', $linkObject);

            return TRUE;
        });

        // News deletion master password
        Validator::extend('orbit.masterpassword.delete', function ($attribute, $value, $parameters) {
            // Current Mall location
            $currentMall = $parameters[0];

            // Get the master password from settings table
            $masterPassword = Setting::getMasterPasswordFor($currentMall);

            if (! is_object($masterPassword)) {
                // @Todo replace with language
                $message = 'The master password is not set.';
                ACL::throwAccessForbidden($message);
            }

            if (! Hash::check($value, $masterPassword->setting_value)) {
                $message = 'The master password is incorrect.';
                ACL::throwAccessForbidden($message);
            }

            return TRUE;
        });

    }

    /**
     * @param EventModel $event
     * @param string $translations_json_string
     * @param string $scenario 'create' / 'update'
     * @throws InvalidArgsException
     */
    private function validateAndSaveTranslations($event, $translations_json_string, $scenario = 'create')
    {
        /*
         * JSON structure: object with keys = merchant_language_id and values = ProductTranslation object or null
         *
         * Having a value of null means deleting the translation
         *
         * where EventTranslation object is object with keys:
         *   event_name, description
         *
         * No requirement for including fields. If field not included it means not updated. If field included with
         * value null it means set to null (use main language content instead).
         */

        $valid_fields = ['event_name', 'description'];
        $user = $this->api->user;
        $operations = [];

        $data = @json_decode($translations_json_string);
        if (json_last_error() != JSON_ERROR_NONE) {
            OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.jsonerror.field.format', ['field' => 'translations']));
        }
        foreach ($data as $merchant_language_id => $translations) {
            $language = MerchantLanguage::excludeDeleted()
                ->where('merchant_language_id', '=', $merchant_language_id)
                ->first();
            if (empty($language)) {
                OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.empty.merchant_language'));
            }
            $existing_translation = EventTranslation::excludeDeleted()
                ->where('event_id', '=', $event->event_id)
                ->where('merchant_language_id', '=', $merchant_language_id)
                ->first();
            if ($translations === null) {
                // deleting, verify exists
                if (empty($existing_translation)) {
                    OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.empty.merchant_language'));
                }
                $operations[] = ['delete', $existing_translation];
            } else {
                foreach ($translations as $field => $value) {
                    if (!in_array($field, $valid_fields, TRUE)) {
                        OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.formaterror.translation.key'));
                    }
                    if ($value !== null && !is_string($value)) {
                        OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.formaterror.translation.value'));
                    }
                }
                if (empty($existing_translation)) {
                    $operations[] = ['create', $merchant_language_id, $translations];
                } else {
                    $operations[] = ['update', $existing_translation, $translations];
                }
            }
        }

        foreach ($operations as $operation) {
            $op = $operation[0];
            if ($op === 'create') {
                $new_translation = new EventTranslation();
                $new_translation->event_id = $event->event_id;
                $new_translation->merchant_language_id = $operation[1];
                $data = $operation[2];
                foreach ($data as $field => $value) {
                    $new_translation->{$field} = $value;
                }
                $new_translation->created_by = $this->api->user->user_id;
                $new_translation->modified_by = $this->api->user->user_id;
                $new_translation->save();

                // Fire an event which listen on orbit.event.after.translation.save
                // @param ControllerAPI $this
                // @param EventTranslation $new_transalation
                Event::fire('orbit.event.after.translation.save', array($this, $new_translation));

                $event->setRelation('translation_'. $new_translation->merchant_language_id, $new_translation);
            }
            elseif ($op === 'update') {

                /** @var EventTranslation $existing_translation */
                $existing_translation = $operation[1];
                $data = $operation[2];
                foreach ($data as $field => $value) {
                    $existing_translation->{$field} = $value;
                }
                $existing_translation->status = $event->status;
                $existing_translation->modified_by = $this->api->user->user_id;
                $existing_translation->save();

                // Fire an event which listen on orbit.event.after.translation.save
                // @param ControllerAPI $this
                // @param EventTranslation $existing_transalation
                Event::fire('orbit.event.after.translation.save', array($this, $existing_translation));

                // return respones if any upload image or no
                $existing_translation->load('media');

                $event->setRelation('translation_'. $existing_translation->merchant_language_id, $existing_translation);
            }
            elseif ($op === 'delete') {
                /** @var EventTranslation $existing_translation */
                $existing_translation = $operation[1];
                $existing_translation->modified_by = $this->api->user->user_id;
                $existing_translation->delete();
            }
        }
    }
}
