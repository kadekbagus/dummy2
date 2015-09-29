<?php
/**
 * An API controller for managing Lucky Draw.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Helper\EloquentRecordCounter as RecordCounter;

class LuckyDrawAPIController extends ControllerAPI
{
    /**
     * POST - Create New Lucky Draw
     *
     * List of API Parameters
     * ----------------------
     * @param string     `lucky_draw_name`       (required) - Lucky Draw name
     * @param string     `status`                (required) - Status. Valid value: active, inactive, pending, blocked, deleted.
     * @param string     `description`           (optional) - Description
     * @param datetime   `start_date`            (optional) - Start date. Example: 2015-04-13 00:00:00
     * @param datetime   `end_date`              (optional) - End date. Example: 2015-04-13 23:59:59
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postNewLuckyDraw()
    {
        $activity = Activity::portal()
                            ->setActivityType('create');

        $user = NULL;
        $newluckydraw = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.luckydraw.postnewluckydraw.before.auth', array($this));

            $this->checkAuth();

            Event::fire('orbit.luckydraw.postnewluckydraw.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.luckydraw.postnewluckydraw.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('create_lucky_draw')) {
                Event::fire('orbit.luckydraw.postnewluckydraw.authz.notallowed', array($this, $user));
                $createLuckyDrawLang = Lang::get('validation.orbit.actionlist.new_lucky_draw');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $createLuckyDrawLang));
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

            Event::fire('orbit.luckydraw.postnewluckydraw.after.authz', array($this, $user));

            $this->registerCustomValidation();

            // set mall id
            $mall_id = OrbitInput::post('mall_id');
            if (trim($mall_id) === '') {
                // if not being sent, then set to current box mall id
                $mall_id = Config::get('orbit.shop.id');
            }

            $lucky_draw_name = OrbitInput::post('lucky_draw_name');
            $description = OrbitInput::post('description');
            $start_date = OrbitInput::post('start_date');
            $end_date = OrbitInput::post('end_date');
            $minimum_amount = OrbitInput::post('minimum_amount');
            $min_number = OrbitInput::post('min_number');
            $max_number = OrbitInput::post('max_number');
            $external_lucky_draw_id = OrbitInput::post('external_lucky_draw_id');
            $grace_period_date = OrbitInput::post('grace_period_date');

            // set default value for status
            $status = OrbitInput::post('status');
            if (trim($status) === '') {
                $status = 'inactive';
            }

            // Begin database transaction
            $this->beginTransaction();

            $validator = Validator::make(
                array(
                    'mall_id'                  => $mall_id,
                    'lucky_draw_name'          => $lucky_draw_name,
                    'description'              => $description,
                    'start_date'               => $start_date,
                    'end_date'                 => $end_date,
                    'minimum_amount'           => $minimum_amount,
                    'min_number'               => $min_number,
                    'max_number'               => $max_number,
                    'external_lucky_draw_id'   => $external_lucky_draw_id,
                    'grace_period_date'        => $grace_period_date,
                    'status'                   => $status,
                ),
                array(
                    'mall_id'                  => 'required|orbit.empty.mall',
                    'lucky_draw_name'          => 'required|max:255|orbit.exists.lucky_draw_name',
                    'description'              => 'required',
                    'start_date'               => 'required|date_format:Y-m-d H:i:s',
                    'end_date'                 => 'required|date_format:Y-m-d H:i:s',
                    'minimum_amount'           => 'required|numeric',
                    'min_number'               => 'required|numeric',
                    'max_number'               => 'required|numeric',
                    'external_lucky_draw_id'   => 'required',
                    'grace_period_date'        => 'date_format:Y-m-d H:i:s',
                    'status'                   => 'orbit.empty.lucky_draw_status|orbit.exists.lucky_draw_active:' . $mall_id,
                )
            );

            Event::fire('orbit.luckydraw.postnewluckydraw.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            Event::fire('orbit.luckydraw.postnewluckydraw.after.validation', array($this, $validator));

            // save Lucky Draw.
            $newluckydraw = new LuckyDraw();
            $newluckydraw->mall_id = $mall_id;
            $newluckydraw->lucky_draw_name = $lucky_draw_name;
            $newluckydraw->description = $description;
            $newluckydraw->start_date = $start_date;
            $newluckydraw->end_date = $end_date;
            $newluckydraw->minimum_amount = $minimum_amount;
            $newluckydraw->min_number = $min_number;
            $newluckydraw->max_number = $max_number;
            $newluckydraw->external_lucky_draw_id = $external_lucky_draw_id;
            $newluckydraw->grace_period_date = $grace_period_date;
            $newluckydraw->status = $status;
            $newluckydraw->created_by = $this->api->user->user_id;

            Event::fire('orbit.luckydraw.postnewluckydraw.before.save', array($this, $newluckydraw));

            $newluckydraw->save();

            // Generate lucky draw numbers
            DB::statement(DB::raw('call generate_lucky_draw_number(' . $min_number . ',' . $max_number . ',' . $newluckydraw->lucky_draw_id . ',' . $user->user_id . ');'));

            Event::fire('orbit.luckydraw.postnewluckydraw.after.save', array($this, $newluckydraw));

            $this->response->data = $newluckydraw;

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activityNotes = sprintf('Lucky Draw Created: %s', $newluckydraw->lucky_draw_name);
            $activity->setUser($user)
                    ->setActivityName('create_lucky_draw')
                    ->setActivityNameLong('Create Lucky Draw OK')
                    ->setObject($newluckydraw)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.luckydraw.postnewluckydraw.after.commit', array($this, $newluckydraw));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.luckydraw.postnewluckydraw.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_lucky_draw')
                    ->setActivityNameLong('Create Lucky Draw Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.luckydraw.postnewluckydraw.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_lucky_draw')
                    ->setActivityNameLong('Create Lucky Draw Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.luckydraw.postnewluckydraw.query.error', array($this, $e));

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
                    ->setActivityName('create_lucky_draw')
                    ->setActivityNameLong('Create Lucky Draw Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.luckydraw.postnewluckydraw.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_lucky_draw')
                    ->setActivityNameLong('Create Lucky Draw Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save the activity
        $activity->save();

        return $this->render($httpCode);
    }

    /**
     * POST - Update Lucky Draw
     *
     * @author Tian <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `lucky_draw_id`         (required) - Lucky Draw ID
     * @param integer    `mall_id`               (optional) - Mall ID
     * @param string     `lucky_draw_name`       (optional) - Lucky Draw name
     * @param string     `description`           (optional) - Description
     * @param file       `images`                (optional) - Lucky Draw image
     * @param datetime   `start_date`            (optional) - Start date. Example: 2014-12-30 00:00:00
     * @param datetime   `end_date`              (optional) - End date. Example: 2014-12-31 23:59:59
     * @param decimal    `minimum_amount`        (optional) - Minimum amount
     * @param datetime   `grace_period_date`     (optional) - Grace period date. Example: 2015-04-13 00:00:00
     * @param integer    `grace_period_in_days`  (optional) - Grace period in days
     * @param integer    `min_number`            (optional) - Min number
     * @param integer    `max_number`            (optional) - Max number
     * @param string     `status`                (optional) - Status. Valid value: active, inactive, pending, blocked, deleted.
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postUpdateLuckyDraw()
    {
        $activity = Activity::portal()
                           ->setActivityType('update');

        $user = NULL;
        $updatedluckydraw = NULL;
        try {
            $httpCode=200;

            Event::fire('orbit.luckydraw.postupdateluckydraw.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.luckydraw.postupdateluckydraw.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.luckydraw.postupdateluckydraw.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('update_lucky_draw')) {
                Event::fire('orbit.luckydraw.postupdateluckydraw.authz.notallowed', array($this, $user));
                $updateLuckyDrawLang = Lang::get('validation.orbit.actionlist.update_lucky_draw');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $updateLuckyDrawLang));
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

            Event::fire('orbit.luckydraw.postupdateluckydraw.after.authz', array($this, $user));

            $this->registerCustomValidation();

            // set mall id
            $mall_id = OrbitInput::post('mall_id');
            if (trim($mall_id) === '') {
                // if not being sent, then set to current box mall id
                $mall_id = Config::get('orbit.shop.id');
            }

            $lucky_draw_id = OrbitInput::post('lucky_draw_id');
            $status = OrbitInput::post('status');
            $start_date = OrbitInput::post('start_date');
            $end_date = OrbitInput::post('end_date');
            $grace_period_date = OrbitInput::post('grace_period_date');
            $now = date('Y-m-d H:i:s');

            $data = array(
                'lucky_draw_id'        => $lucky_draw_id,
                'mall_id'              => $mall_id,
                'start_date'           => $start_date,
                'end_date'             => $end_date,
                'grace_period_date'    => $grace_period_date,
            );

            // Validate lucky_draw_name only if exists in POST.
            OrbitInput::post('lucky_draw_name', function($lucky_draw_name) use (&$data) {
                $data['lucky_draw_name'] = $lucky_draw_name;
            });

            // Validate status only if exists in POST.
            OrbitInput::post('status', function($status) use (&$data) {
                $data['status'] = $status;
            });

            // Begin database transaction
            $this->beginTransaction();

            $validator = Validator::make(
                $data,
                array(
                    'lucky_draw_id'        => 'required|orbit.empty.lucky_draw',
                    'mall_id'              => 'orbit.empty.mall',
                    'lucky_draw_name'      => 'sometimes|required|min:3|max:255|lucky_draw_name_exists_but_me:' . $lucky_draw_id . ',' . $mall_id,
                    'status'               => 'sometimes|required|orbit.empty.lucky_draw_status|orbit.exists.lucky_draw_active_but_me:' . $mall_id . ',' . $lucky_draw_id,
                    'start_date'           => 'date_format:Y-m-d H:i:s',
                    'end_date'             => 'date_format:Y-m-d H:i:s|end_date_greater_than_start_date_and_current_date:'.$start_date.','.$now,
                    'grace_period_date'    => 'date_format:Y-m-d H:i:s',
                ),
                array(
                   'lucky_draw_name_exists_but_me' => Lang::get('validation.orbit.exists.lucky_draw_name'),
                   'orbit.exists.lucky_draw_active_but_me' => Lang::get('validation.orbit.exists.lucky_draw_active'),
                   'end_date_greater_than_start_date_and_current_date' => 'The end datetime should be greater than the start datetime or current datetime.'
                )
            );

            Event::fire('orbit.luckydraw.postupdateluckydraw.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.luckydraw.postupdateluckydraw.after.validation', array($this, $validator));

            $updatedluckydraw = LuckyDraw::excludeDeleted()->where('lucky_draw_id', $lucky_draw_id)->first();

            // save Lucky Draw
            OrbitInput::post('mall_id', function($mall_id) use ($updatedluckydraw) {
                $updatedluckydraw->mall_id = $mall_id;
            });

            OrbitInput::post('lucky_draw_name', function($lucky_draw_name) use ($updatedluckydraw) {
                $updatedluckydraw->lucky_draw_name = $lucky_draw_name;
            });

            OrbitInput::post('description', function($description) use ($updatedluckydraw) {
                $updatedluckydraw->description = $description;
            });

            OrbitInput::post('image', function($image) use ($updatedluckydraw) {
                $updatedluckydraw->image = $image;
            });

            OrbitInput::post('start_date', function($start_date) use ($updatedluckydraw) {
                $updatedluckydraw->start_date = $start_date;
            });

            OrbitInput::post('end_date', function($end_date) use ($updatedluckydraw) {
                $updatedluckydraw->end_date = $end_date;
            });

            OrbitInput::post('minimum_amount', function($minimum_amount) use ($updatedluckydraw) {
                if ((double)$minimum_amount !== (double)$updatedluckydraw->minimum_amount) {
                    $errorMessage = 'You can not change the minimum value to obtain.';
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }
                $updatedluckydraw->minimum_amount = $minimum_amount;
            });

            OrbitInput::post('grace_period_date', function($grace_period_date) use ($updatedluckydraw) {
                $updatedluckydraw->grace_period_date = $grace_period_date;
            });

            OrbitInput::post('grace_period_in_days', function($grace_period_in_days) use ($updatedluckydraw) {
                $updatedluckydraw->grace_period_in_days = $grace_period_in_days;
            });

            OrbitInput::post('min_number', function($min_number) use ($updatedluckydraw) {
                if ((string)$min_number !== (string)$updatedluckydraw->min_number) {
                    $errorMessage = 'You can not change the minumum number of lucky draw its already generated.';
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }
                $updatedluckydraw->min_number = $min_number;
            });

            OrbitInput::post('max_number', function($max_number) use ($updatedluckydraw) {
                if ((string)$max_number !== (string)$updatedluckydraw->max_number) {
                    $errorMessage = 'You can not change the maximum number of lucky draw its already generated.';
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }
                $updatedluckydraw->max_number = $max_number;
            });

            OrbitInput::post('external_lucky_draw_id', function($data) use ($updatedluckydraw) {
                $updatedluckydraw->external_lucky_draw_id = $data;
            });

            OrbitInput::post('status', function($status) use ($updatedluckydraw) {
                $updatedluckydraw->status = $status;
            });

            $updatedluckydraw->modified_by = $this->api->user->user_id;

            Event::fire('orbit.luckydraw.postupdateluckydraw.before.save', array($this, $updatedluckydraw));

            $updatedluckydraw->save();

            Event::fire('orbit.luckydraw.postupdateluckydraw.after.save', array($this, $updatedluckydraw));
            $this->response->data = $updatedluckydraw;

            // Commit the changes
            $this->commit();

            // Successful Update
            $activityNotes = sprintf('Lucky Draw updated: %s', $updatedluckydraw->lucky_draw_name);
            $activity->setUser($user)
                    ->setActivityName('update_lucky_draw')
                    ->setActivityNameLong('Update Lucky Draw OK')
                    ->setObject($updatedluckydraw)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.luckydraw.postupdateluckydraw.after.commit', array($this, $updatedluckydraw));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.luckydraw.postupdateluckydraw.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_lucky_draw')
                    ->setActivityNameLong('Update Lucky Draw Failed')
                    ->setObject($updatedluckydraw)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.luckydraw.postupdateluckydraw.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_lucky_draw')
                    ->setActivityNameLong('Update Lucky Draw Failed')
                    ->setObject($updatedluckydraw)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.luckydraw.postupdateluckydraw.query.error', array($this, $e));

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
                    ->setActivityName('update_lucky_draw')
                    ->setActivityNameLong('Update Lucky Draw Failed')
                    ->setObject($updatedluckydraw)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.luckydraw.postupdateluckydraw.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_lucky_draw')
                    ->setActivityNameLong('Update Lucky Draw Failed')
                    ->setObject($updatedluckydraw)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save activity
        $activity->save();

        return $this->render($httpCode);

    }

    /**
     * POST - Delete Lucky Draw
     *
     * @author Tian <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `lucky_draw_id`                  (required) - ID of the lucky draw
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeleteLuckyDraw()
    {
        $activity = Activity::portal()
                          ->setActivityType('delete');

        $user = NULL;
        $deleteluckydraw = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.luckydraw.postdeleteluckydraw.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.luckydraw.postdeleteluckydraw.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.luckydraw.postdeleteluckydraw.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('delete_lucky_draw')) {
                Event::fire('orbit.luckydraw.postdeleteluckydraw.authz.notallowed', array($this, $user));
                $deleteLuckyDrawLang = Lang::get('validation.orbit.actionlist.delete_lucky_draw');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $deleteLuckyDrawLang));
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

            Event::fire('orbit.luckydraw.postdeleteluckydraw.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $lucky_draw_id = OrbitInput::post('lucky_draw_id');

            $validator = Validator::make(
                array(
                    'lucky_draw_id' => $lucky_draw_id,
                ),
                array(
                    'lucky_draw_id' => 'required|orbit.empty.lucky_draw',
                )
            );

            Event::fire('orbit.luckydraw.postdeleteluckydraw.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.luckydraw.postdeleteluckydraw.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            $deleteluckydraw = LuckyDraw::excludeDeleted()->allowedForUser($user)->where('lucky_draw_id', $lucky_draw_id)->first();
            $deleteluckydraw->status = 'deleted';
            $deleteluckydraw->modified_by = $this->api->user->user_id;

            Event::fire('orbit.luckydraw.postdeleteluckydraw.before.save', array($this, $deleteluckydraw));

            $deleteluckydraw->save();

            Event::fire('orbit.luckydraw.postdeleteluckydraw.after.save', array($this, $deleteluckydraw));
            $this->response->data = null;
            $this->response->message = Lang::get('statuses.orbit.deleted.lucky_draw');

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activityNotes = sprintf('Lucky Draw Deleted: %s', $deleteluckydraw->lucky_draw_name);
            $activity->setUser($user)
                    ->setActivityName('delete_lucky_draw')
                    ->setActivityNameLong('Delete Lucky Draw OK')
                    ->setObject($deleteluckydraw)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.luckydraw.postdeleteluckydraw.after.commit', array($this, $deleteluckydraw));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.luckydraw.postdeleteluckydraw.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_lucky_draw')
                    ->setActivityNameLong('Delete Lucky Draw Failed')
                    ->setObject($deleteluckydraw)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.luckydraw.postdeleteluckydraw.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_lucky_draw')
                    ->setActivityNameLong('Delete Lucky Draw Failed')
                    ->setObject($deleteluckydraw)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.luckydraw.postdeleteluckydraw.query.error', array($this, $e));

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
                    ->setActivityName('delete_lucky_draw')
                    ->setActivityNameLong('Delete Lucky Draw Failed')
                    ->setObject($deleteluckydraw)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.luckydraw.postdeleteluckydraw.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_lucky_draw')
                    ->setActivityNameLong('Delete Lucky Draw Failed')
                    ->setObject($deleteluckydraw)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        $output = $this->render($httpCode);

        // Save the activity
        $activity->save();

        return $output;
    }

    /**
     * GET - Search Lucky Draw
     *
     * @author Tian <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string   `with`                  (optional) - Valid value: mall, media, winners, numbers, issued_numbers.
     * @param string   `sortby`                (optional) - Column order by. Valid value: registered_date, lucky_draw_name, description, start_date, end_date, status.
     * @param string   `sortmode`              (optional) - ASC or DESC
     * @param integer  `take`                  (optional) - Limit
     * @param integer  `skip`                  (optional) - Limit offset
     * @param integer  `lucky_draw_id`         (optional) - Lucky Draw ID
     * @param integer  `mall_id`               (optional) - Mall ID
     * @param string   `lucky_draw_name`       (optional) - Lucky Draw name
     * @param string   `lucky_draw_name_like`  (optional) - Lucky Draw name like
     * @param string   `description`           (optional) - Description
     * @param string   `description_like`      (optional) - Description like
     * @param datetime `start_date`            (optional) - Start date. Example: 2015-04-13 00:00:00
     * @param datetime `end_date`              (optional) - End date. Example: 2015-04-13 23:59:59
     * @param string   `status`                (optional) - Status. Valid value: active, inactive, pending, blocked, deleted.
     * @param string   `details`               (optional) - Value: 'yes' will shows the total of issued lucky draw number in field 'total_issued_lucky_draw_number'
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchLuckyDraw()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.luckydraw.getsearchluckydraw.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.luckydraw.getsearchluckydraw.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.luckydraw.getsearchluckydraw.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('view_lucky_draw')) {
                Event::fire('orbit.luckydraw.getsearchluckydraw.authz.notallowed', array($this, $user));
                $viewLuckyDrawLang = Lang::get('validation.orbit.actionlist.view_lucky_draw');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewLuckyDrawLang));
                ACL::throwAccessForbidden($message);
            }
*/
            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = ['super admin', 'mall admin', 'mall owner', 'mall customer service'];
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.luckydraw.getsearchluckydraw.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sortby');
            $details_view = OrbitInput::get('details');
            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                ),
                array(
                    'sort_by' => 'in:registered_date,lucky_draw_name,description,start_date,end_date,status,total_issued_lucky_draw_number,external_lucky_draw_id',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.lucky_draw_sortby'),
                )
            );

            Event::fire('orbit.luckydraw.getsearchluckydraw.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.luckydraw.getsearchluckydraw.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.lucky_draw.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.lucky_draw.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            // Builder object
            $luckydraws = LuckyDraw::excludeDeleted('lucky_draws')->select('lucky_draws.*');

            if ($details_view === 'yes') {
                $prefix = DB::getTablePrefix();
                $luckydraws->select('lucky_draws.*',
                                    DB::raw("count({$prefix}lucky_draw_numbers.lucky_draw_number_id) as total_issued_lucky_draw_number"))
                                    ->joinLuckyDrawNumbers()
                                    ->groupBy('lucky_draws.lucky_draw_id');
            }

            // Filter lucky draw by ids
            OrbitInput::get('lucky_draw_id', function($id) use ($luckydraws)
            {
                $luckydraws->whereIn('lucky_draws.lucky_draw_id', $id);
            });

            // Filter lucky draw by external_lucky_draw_id
            OrbitInput::get('external_lucky_draw_id', function($id) use ($luckydraws)
            {
                $luckydraws->whereIn('lucky_draws.external_lucky_draw_id', $id);
            });

            // Filter lucky draw by mall ids
            OrbitInput::get('mall_id', function ($mallId) use ($luckydraws) {
                $luckydraws->whereIn('lucky_draws.mall_id', $mallId);
            });

            // Filter lucky draw by name
            OrbitInput::get('lucky_draw_name', function($name) use ($luckydraws)
            {
                $luckydraws->whereIn('lucky_draws.lucky_draw_name', $name);
            });

            // Filter lucky draw by matching name pattern
            OrbitInput::get('lucky_draw_name_like', function($name) use ($luckydraws)
            {
                $luckydraws->where('lucky_draws.lucky_draw_name', 'like', "%$name%");
            });

            // Filter lucky draw by description
            OrbitInput::get('description', function($description) use ($luckydraws)
            {
                $luckydraws->whereIn('lucky_draws.description', $description);
            });

            // Filter lucky draw by matching description pattern
            OrbitInput::get('description_like', function($description) use ($luckydraws)
            {
                $luckydraws->where('lucky_draws.description', 'like', "%$description%");
            });

            // Filter lucky draw by start date
            OrbitInput::get('start_date', function($startDate) use ($luckydraws)
            {
                $luckydraws->where('lucky_draws.start_date', '<=', $startDate);
            });

            // Filter lucky draw by end date
            OrbitInput::get('end_date', function($endDate) use ($luckydraws)
            {
                $luckydraws->where('lucky_draws.end_date', '>=', $endDate);
            });

            // Filter lucky draw by status
            OrbitInput::get('status', function ($status) use ($luckydraws) {
                $luckydraws->whereIn('lucky_draws.status', $status);
            });

            // Filter by start date
            OrbitInput::get('start_date_after', function($data) use ($luckydraws) {
                $luckydraws->where('lucky_draws.start_date', '>=', $data);
            });

            // Filter by start date
            OrbitInput::get('start_date_before', function($data) use ($luckydraws) {
                $luckydraws->where('lucky_draws.start_date', '<=', $data);
            });

            // Filter by end date
            OrbitInput::get('end_date_after', function($data) use ($luckydraws) {
                $luckydraws->where('lucky_draws.end_date', '>=', $data);
            });

            // Filter by end date
            OrbitInput::get('end_date_before', function($data) use ($luckydraws) {
                $luckydraws->where('lucky_draws.end_date', '<=', $data);
            });

            // Filter by created_at date
            OrbitInput::get('created_at_after', function($data) use ($luckydraws) {
                $luckydraws->where('lucky_draws.created_at', '>=', $data);
            });

            // Filter by created_at date
            OrbitInput::get('created_at_before', function($data) use ($luckydraws) {
                $luckydraws->where('lucky_draws.created_at', '<=', $data);
            });

            // Filter by updated_at date
            OrbitInput::get('updated_at_after', function($data) use ($luckydraws) {
                $luckydraws->where('lucky_draws.updated_at', '>=', $data);
            });

            // Filter by updated_at date
            OrbitInput::get('updated_at_before', function($data) use ($luckydraws) {
                $luckydraws->where('lucky_draws.updated_at', '<=', $data);
            });

            // Add new relation based on request
            OrbitInput::get('with', function ($with) use ($luckydraws) {
                $with = (array) $with;

                foreach ($with as $relation) {
                    if ($relation === 'mall') {
                        $luckydraws->with('mall');
                    } elseif ($relation === 'media') {
                        $luckydraws->with('media');
                    } elseif ($relation === 'winners') {
                        $luckydraws->with('winners');
                    } elseif ($relation === 'numbers') {
                        $luckydraws->with('numbers');
                    } elseif ($relation === 'issued_numbers') {
                        $luckydraws->with('issuedNumbers');
                    }
                }
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_luckydraws = clone $luckydraws;

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
            $luckydraws->take($take);

            $skip = 0;
            OrbitInput::get('skip', function($_skip) use (&$skip, $luckydraws)
            {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            if (($take > 0) && ($skip > 0)) {
                $luckydraws->skip($skip);
            }

            // Default sort by
            $sortBy = 'lucky_draws.lucky_draw_name';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'registered_date'          => 'lucky_draws.created_at',
                    'lucky_draw_name'          => 'lucky_draws.lucky_draw_name',
                    'description'              => 'lucky_draws.description',
                    'start_date'               => 'lucky_draws.start_date',
                    'end_date'                 => 'lucky_draws.end_date',
                    'status'                   => 'lucky_draws.status',
                    'external_lucky_draw_id'   => 'lucky_draws.external_lucky_draw_id',
                );

                if (array_key_exists($_sortBy, $sortByMapping)) {
                    $sortBy = $sortByMapping[$_sortBy];
                }
            });

            if ($sortBy !== 'lucky_draws.status') {
                $luckydraws->orderBy('lucky_draws.status', 'asc');
            }

            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });

            $luckydraws->orderBy($sortBy, $sortMode);

            $totalLuckyDraws = RecordCounter::create($_luckydraws)->count();
            $listOfLuckyDraws = $luckydraws->get();

            $data = new stdclass();
            $data->total_records = $totalLuckyDraws;
            $data->returned_records = count($listOfLuckyDraws);
            $data->records = $listOfLuckyDraws;

            if ($totalLuckyDraws === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.lucky_draw');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.luckydraw.getsearchluckydraw.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.luckydraw.getsearchluckydraw.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.luckydraw.getsearchluckydraw.query.error', array($this, $e));

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
            Event::fire('orbit.luckydraw.getsearchluckydraw.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.luckydraw.getsearchluckydraw.before.render', array($this, &$output));

        return $output;
    }

    /**
     * GET - Search Lucky Draw Number
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param string   `with`                  (optional) -
     * @param string   `sortby`                (optional) - Column order by. lucky_draw_number, created_at, issued_date, status.
     * @param string   `sortmode`              (optional) - ASC or DESC
     * @param integer  `take`                  (optional) - Limit
     * @param integer  `skip`                  (optional) - Limit offset
     * @param array    `lucky_draw_id`         (optional) - Lucky Draw ID
     * @param array    `user_id`               (optional) - Consumer ID
     * @param array    `retailer_id`           (optional) - Retailer/Tenant ID
     * @param string   `lucky_draw_name`       (optional) - Lucky Draw name
     * @param string   `lucky_draw_name_like`  (optional) - Lucky Draw name like
     * @param datetime `issued_date_from`      (optional) - Issued begin date.
     * @param datetime `issued_date_to`        (optional) - Issued end date.
     * @param string   `status`                (optional) - Status. Valid value: active, inactive, pending, blocked, deleted.
     * @param string   `group_by_receipt`      (optional) - 'yes' to group the based on receipt number
     *
     * @return Illuminate\Support\Facades\Response
     */
        public function getSearchLuckyDrawNumber()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.luckydraw.getsearchluckydraw.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.luckydraw.getsearchluckydraw.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.luckydraw.getsearchluckydraw.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('view_lucky_draw')) {
                Event::fire('orbit.luckydraw.getsearchluckydraw.authz.notallowed', array($this, $user));
                $viewLuckyDrawLang = Lang::get('validation.orbit.actionlist.view_lucky_draw');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewLuckyDrawLang));
                ACL::throwAccessForbidden($message);
            }
*/
            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = ['super admin', 'mall admin', 'mall owner', 'mall customer service', 'consumer'];
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.luckydraw.getsearchluckydraw.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sortby');
            $groupByReceipt = OrbitInput::get('group_by_receipt');

            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                ),
                array(
                    'sort_by' => 'in:created_at,lucky_draw_number,issued_date,status',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.lucky_draw_sortby'),
                )
            );

            Event::fire('orbit.luckydraw.getsearchluckydraw.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.luckydraw.getsearchluckydraw.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.lucky_draw.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.lucky_draw_number.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            // Builder object
            $luckydraws = LuckyDrawNumber::select('lucky_draw_numbers.*')
                                         ->active('lucky_draw_numbers')
                                         ->joinReceipts()
                                         ->joinLuckyDraw();

            if ($groupByReceipt === 'yes') {
                $prefix = DB::getTablePrefix();
                $luckydraws->select('lucky_draw_receipts.*',
                                    DB::raw("count({$prefix}lucky_draw_numbers.lucky_draw_number_id) as total_lucky_draw_number"),
                                    'merchants.name as retailer_name',
                                    'merchants.merchant_id as retailer_id',
                                    'lucky_draws.lucky_draw_id',
                                    'lucky_draws.lucky_draw_name',
                                    'lucky_draws.image as lucky_draw_image',
                                    'lucky_draws.start_date',
                                    'lucky_draws.end_date')
                           ->groupBy('lucky_draw_receipts.lucky_draw_receipt_id');
            } else {
                $luckydraws->groupBy('lucky_draw_numbers.lucky_draw_number_id');
            }

            // Filter lucky draw by ids
            OrbitInput::get('lucky_draw_id', function($id) use ($luckydraws)
            {
                $luckydraws->whereIn('lucky_draw_numbers.lucky_draw_id', $id);
            });

            // Filter lucky draw by ids
            if ($user->isRoleName('consumer')) {
                $luckydraws->whereIn('lucky_draw_numbers.user_id', [$user->user_id]);
            } else {
                OrbitInput::get('user_id', function($id) use ($luckydraws)
                {
                    $luckydraws->whereIn('lucky_draw_numbers.user_id', $id);
                });
            }

            // Filter lucky draw by ids
            OrbitInput::get('retailer_id', function($id) use ($luckydraws)
            {
                $luckydraws->whereIn('merchants.merchant_id', $id);
            });

            // Filter lucky draw by matching number
            OrbitInput::get('lucky_draw_number', function($number) use ($luckydraws)
            {
                $luckydraws->where('lucky_draw_numbers.lucky_draw_number_code', $number);
            });

            // Filter lucky draw by matching number pattern
            OrbitInput::get('lucky_draw_number_like', function($name) use ($luckydraws)
            {
                $luckydraws->where('lucky_draw_numbers.lucky_draw_number_code', 'like', "%$name%");
            });

            // Filter lucky draw by matching name pattern
            OrbitInput::get('lucky_draw_name_like', function($name) use ($luckydraws)
            {
                $luckydraws->where('lucky_draw_numbers.lucky_draw_name', 'like', "%$name%");
            });

            // Filter lucky draw by name
            OrbitInput::get('lucky_draw_name', function($name) use ($luckydraws)
            {
                $luckydraws->whereIn('lucky_draws.lucky_draw_name', $name);
            });

            // Filter lucky draw by matching name pattern
            OrbitInput::get('lucky_draw_name_like', function($name) use ($luckydraws)
            {
                $luckydraws->where('lucky_draws.lucky_draw_name', 'like', "%$name%");
            });

            // Filter lucky draw by status
            OrbitInput::get('status', function ($status) use ($luckydraws) {
                $luckydraws->whereIn('lucky_draw_numbers.status', $status);
            });

            // Filter lucky draw by status
            OrbitInput::get('issued_date_from', function ($from) use ($luckydraws) {
                $luckydraws->where(function($query) use ($from) {
                    $to = OrbitInput::get('issued_date_to', NULL);
                    $prefix = DB::getTablePrefix();

                    if (empty($to)) {
                        $query->whereRaw("date({$prefix}lucky_draw_numbers.issued_date) >= date(?)", [$from]);
                    } else {
                        $query->whereRaw("date({$prefix}lucky_draw_numbers.issued_date) between date(?) and date(?)", [$from, $to]);
                    }
                });
            });

            // Add new relation based on request
            OrbitInput::get('with', function ($with) use ($luckydraws) {
                $with = (array) $with;

                foreach ($with as $relation) {
                    if ($relation === 'mall') {
                        $luckydraws->with('mall');
                    } elseif ($relation === 'media') {
                        $luckydraws->with('media');
                    } elseif ($relation === 'winners') {
                        $luckydraws->with('winners');
                    } elseif ($relation === 'issued_numbers') {
                        $luckydraws->with('issuedNumbers');
                    } elseif ($relation === 'receipts') {
                        $luckydraws->with('receipts');
                    }
                }
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_luckydraws = clone $luckydraws;

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
            $luckydraws->take($take);

            $skip = 0;
            OrbitInput::get('skip', function($_skip) use (&$skip, $luckydraws)
            {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            if (($take > 0) && ($skip > 0)) {
                $luckydraws->skip($skip);
            }

            // Default sort by
            $sortBy = 'lucky_draw_numbers.lucky_draw_number_code';
            // Default sort mode
            $sortMode = 'desc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'registered_date'          => 'lucky_draw_numbers.created_at',
                    'lucky_draw_number'        => 'lucky_draw_numbers.lucky_draw_number_code',
                    'issued_date'              => 'lucky_draw_numbers.issued_date',
                    'status'                   => 'lucky_draw_numbers.status'
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) !== 'desc') {
                    $sortMode = 'asc';
                }
            });

            $luckydraws->orderBy('lucky_draw_numbers.issued_date', 'desc');
            $luckydraws->orderBy($sortBy, $sortMode);

            $totalLuckyDraws = RecordCounter::create($_luckydraws)->count();
            $listOfLuckyDraws = $luckydraws->get();

            $data = new stdclass();
            $data->total_records = $totalLuckyDraws;
            $data->returned_records = count($listOfLuckyDraws);
            $data->records = $listOfLuckyDraws;

            if ($totalLuckyDraws === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.lucky_draw');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.luckydraw.getsearchluckydraw.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.luckydraw.getsearchluckydraw.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.luckydraw.getsearchluckydraw.query.error', array($this, $e));

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
            Event::fire('orbit.luckydraw.getsearchluckydraw.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.luckydraw.getsearchluckydraw.before.render', array($this, &$output));

        return $output;
    }

    /**
     * GET - Search Luckydraw - List By Mall
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string   `with`                  (optional) - Valid value: mall, media, winners, numbers, issued_numbers.
     * @param string   `sortby`                (optional) - column order by. Valid value: issue_retailer_name, registered_date, promotion_name, promotion_type, description, begin_date, end_date, is_permanent, status.
     * @param string   `sortmode`              (optional) - asc or desc
     * @param integer  `take`                  (optional) - limit
     * @param integer  `skip`                  (optional) - limit offset
     * @param string   `status`                (optional) - Status. Valid value: active, inactive, pending, blocked, deleted.
     * @param string   `user_id`               (optional) - User ID
     * @param string   `city`                  (optional) - City name
     * @param string   `city_like`             (optional) - City name like
     * @param integer  `mall_id`               (optional) - Mall ID
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchLuckyDrawByMall()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.luckydraw.getsearchluckydrawbymall.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.luckydraw.getsearchluckydrawbymall.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.luckydraw.getsearchluckydrawbymall.before.authz', array($this, $user));

            // if (! ACL::create($user)->isAllowed('view_lucky_draw')) {
            //     Event::fire('orbit.luckydraw.getsearchluckydrawbymall.authz.notallowed', array($this, $user));
            //     $viewLuckyDrawLang = Lang::get('validation.orbit.actionlist.view_lucky_draw');
            //     $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewLuckyDrawLang));
            //     ACL::throwAccessForbidden($message);
            // }

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = ['super admin', 'mall admin', 'mall owner', 'mall customer service', 'consumer'];
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.luckydraw.getsearchluckydrawbymall.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sortby');
            $details_view = OrbitInput::get('details_view');

            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                ),
                array(
                    'sort_by' => 'in:registered_date,lucky_draw_name,end_date,status',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.luckydraw_by_issue_retailer_sortby'),
                )
            );

            Event::fire('orbit.luckydraw.getsearchluckydrawbymall.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.luckydraw.getsearchluckydrawbymall.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int)Config::get('orbit.pagination.max_record');
            if ($maxRecord <= 0) {
                $maxRecord = 20;
            }

            // Builder object
            $luckydraws = LuckyDraw::excludeDeleted('lucky_draws')->select('lucky_draws.*');

            if ($details_view === 'yes') {
                $prefix = DB::getTablePrefix();
                $luckydraws->select('lucky_draws.*',
                                    DB::raw("count({$prefix}lucky_draw_numbers.lucky_draw_number_id) as total_issued_lucky_draw_number"))
                                    ->leftJoin('lucky_draw_numbers', function($join) use($user) {
                                        $prefix = DB::getTablePrefix();
                                        $join->on('lucky_draw_numbers.lucky_draw_id', '=', 'lucky_draws.lucky_draw_id');
                                        // $join->on('lucky_draw_numbers.status', '!=',
                                        //           DB::raw("'deleted' and ({$prefix}lucky_draw_numbers.user_id is not null and {$prefix}lucky_draw_numbers.user_id != 0)"));
                                        $join->on('lucky_draw_numbers.status', '=',
                                                  DB::raw("'active' and ({$prefix}lucky_draw_numbers.user_id is not null and {$prefix}lucky_draw_numbers.user_id != 0)"));
                                        $join->on('lucky_draw_numbers.user_id', 'in', DB::raw('(' . $user->user_id . ')'));
                                    })
                                    ->groupBy('lucky_draws.lucky_draw_id');
            }

            // Add new relation based on request
            OrbitInput::get('with', function ($with) use ($luckydraws) {
                $with = (array) $with;

                foreach ($with as $relation) {
                    if ($relation === 'mall') {
                        $luckydraws->with('mall');
                    } elseif ($relation === 'media') {
                        $luckydraws->with('media');
                    } elseif ($relation === 'winners') {
                        $luckydraws->with('winners');
                    } elseif ($relation === 'numbers') {
                        $luckydraws->with('numbers');
                    } elseif ($relation === 'issued_numbers') {
                        $luckydraws->with('issuedNumbers');
                    }
                }
            });

            // Filter lucky draw by ids
            if ($user->isRoleName('consumer')) {
                // $luckydraws->whereIn('lucky_draw_numbers.user_id', [$user->user_id]);
            } else {
                OrbitInput::get('user_id', function($id) use ($luckydraws)
                {
                    $luckydraws->whereIn('lucky_draw_numbers.user_id', $id);
                });
            }

            // Filter luckydraw by status
            OrbitInput::get('status', function ($statuses) use ($luckydraws) {
                $luckydraws->whereIn('lucky_draws.status', $statuses);
            });

            // Filter luckydraw by city
            OrbitInput::get('city', function($city) use ($luckydraws)
            {
                $luckydraws->whereIn('merchants.city', $city);
            });

            // Filter luckydraw by matching city pattern
            OrbitInput::get('city_like', function($city) use ($luckydraws)
            {
                $luckydraws->where('merchants.city', 'like', "%$city%");
            });

            // Filter luckydraw by issue retailer Ids
            OrbitInput::get('mall_id', function ($issueRetailerIds) use ($luckydraws) {
                $luckydraws->whereIn('lucky_draws.mall_id', $issueRetailerIds);
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_luckydraws = clone $luckydraws;

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
                $luckydraws->take($take);
            }

            $skip = 0;
            OrbitInput::get('skip', function($_skip) use (&$skip, $luckydraws)
            {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            if (($take > 0) && ($skip > 0)) {
                $luckydraws->skip($skip);
            }

            // Default sort by
            $sortBy = 'lucky_draw_name';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'registered_date'        => 'lucky_draws.created_at',
                    'lucky_draw_name'        => 'lucky_draws.lucky_draw_name',
                    'end_date'               => 'lucky_draws.end_date',
                    'status'                 => 'lucky_draws.status'
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });
            $luckydraws->orderBy($sortBy, $sortMode);

            $totalLuckyDraws = $_luckydraws->count();
            $listOfLuckyDraws = $luckydraws->get();

            $data = new stdclass();
            $data->total_records = $totalLuckyDraws;
            $data->returned_records = count($listOfLuckyDraws);
            $data->records = $listOfLuckyDraws;

            if ($totalLuckyDraws === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.lucky_draw');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.luckydraw.getsearchluckydrawbymall.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.luckydraw.getsearchluckydrawbymall.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.luckydraw.getsearchluckydrawbymall.query.error', array($this, $e));

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
            Event::fire('orbit.luckydraw.getsearchluckydrawbymall.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.luckydraw.getsearchluckydrawbymall.before.render', array($this, &$output));

        return $output;
    }

    protected function registerCustomValidation()
    {
        // Check the existance of lucky_draw id
        Validator::extend('orbit.empty.lucky_draw', function ($attribute, $value, $parameters) {
            $lucky_draw = LuckyDraw::excludeDeleted()
                                   ->where('lucky_draw_id', $value)
                                   ->first();

            if (empty($lucky_draw)) {
                return FALSE;
            }

            App::instance('orbit.empty.lucky_draw', $lucky_draw);

            return TRUE;
        });

        // Check the existance of mall id
        Validator::extend('orbit.empty.mall', function ($attribute, $value, $parameters) {
            $mall = Retailer::excludeDeleted()
                            ->isMall()
                            ->where('merchant_id', $value)
                            ->first();

            if (empty($mall)) {
                return FALSE;
            }

            App::instance('orbit.empty.mall', $mall);

            return TRUE;
        });

        // Check lucky draw name, it should not exists
        Validator::extend('orbit.exists.lucky_draw_name', function ($attribute, $value, $parameters) {
            $lucky_draw = LuckyDraw::excludeDeleted()
                                   ->where('lucky_draw_name', $value)
                                   ->first();

            if (! empty($lucky_draw)) {
                return FALSE;
            }

            App::instance('orbit.validation.lucky_draw_name', $lucky_draw);

            return TRUE;
        });

        // Check lucky draw name, it should not exists (for update)
        Validator::extend('lucky_draw_name_exists_but_me', function ($attribute, $value, $parameters) {
            $lucky_draw_id = $parameters[0];
            $mallId = $parameters[1];
            $lucky_draw = LuckyDraw::excludeDeleted()
                                   ->where('mall_id', $mallId)
                                   ->where('lucky_draw_name', $value)
                                   ->where('lucky_draw_id', '!=', $lucky_draw_id)
                                   ->first();

            if (! empty($lucky_draw)) {
                return FALSE;
            }

            App::instance('orbit.validation.lucky_draw_name', $lucky_draw);

            return TRUE;
        });

        // Check the existence of the lucky draw status
        Validator::extend('orbit.empty.lucky_draw_status', function ($attribute, $value, $parameters) {
            $valid = false;
            $statuses = array('active', 'inactive');
            foreach ($statuses as $status) {
                if($value === $status) $valid = $valid || TRUE;
            }

            return $valid;
        });

        // Check end date should be greater than start date and current date
        Validator::extend('end_date_greater_than_start_date_and_current_date', function ($attribute, $value, $parameters) {
            $start_date = strtotime($parameters[0]);
            $end_date = strtotime($value);
            $current_date = strtotime($parameters[1]);

            if (($end_date > $start_date) && ($end_date > $current_date)) {
                return TRUE;
            }

            return FALSE;
        });

        // Check status for only allowed one lucky draw to be active
        Validator::extend('orbit.exists.lucky_draw_active', function ($attribute, $value, $parameters) {
            // Check only if status is active
            if ($value === 'active') {
                $mallId = $parameters[0];

                $data = LuckyDraw::excludeDeleted()
                                 ->where('mall_id', $mallId)
                                 ->active()
                                 ->first();

                if (! empty($data)) {
                    return FALSE;
                }

                App::instance('orbit.exists.lucky_draw_active', $data);
            }

            return TRUE;
        });

        // Check status for only allowed one lucky draw to be active
        Validator::extend('orbit.exists.lucky_draw_active_but_me', function ($attribute, $value, $parameters) {
            // Check only if status is active
            if ($value === 'active') {
                $mallId = $parameters[0];
                $luckyDrawId = $parameters[1];

                $data = LuckyDraw::excludeDeleted()
                                 ->where('mall_id', $mallId)
                                 ->active()
                                 ->where('lucky_draw_id', '!=', $luckyDrawId)
                                 ->first();

                if (! empty($data)) {
                    return FALSE;
                }

                App::instance('orbit.exists.lucky_draw_active', $data);
            }

            return TRUE;
        });

    }
}
