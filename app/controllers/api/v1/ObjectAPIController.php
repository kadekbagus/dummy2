<?php
/**
 * An API controller for managing Object.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Helper\EloquentRecordCounter as RecordCounter;

class ObjectAPIController extends ControllerAPI
{
    /**
     * POST - Create New Object
     *
     * @author Tian <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `merchant_id`           (required) - Merchant ID
     * @param string     `object_name`             (required) - Object name
     * @param string     `object_type`           (optional) - Object type. Valid value: bank, payment.
     * @param string     `status`                (required) - Status. Valid value: active, inactive, pending, blocked, deleted.
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postNewObject()
    {
        $activity = Activity::portal()
                            ->setActivityType('create');

        $user = NULL;
        $newobject = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.object.postnewobject.before.auth', array($this));

            $this->checkAuth();

            Event::fire('orbit.object.postnewobject.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.object.postnewobject.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('create_object')) {
                Event::fire('orbit.object.postnewobject.authz.notallowed', array($this, $user));
                $createObjectLang = Lang::get('validation.orbit.actionlist.new_object');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $createObjectLang));
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

            Event::fire('orbit.object.postnewobject.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $merchant_id = OrbitInput::post('current_mall');;
            $object_name = OrbitInput::post('object_name');
            $object_type = OrbitInput::post('object_type');
            $status = OrbitInput::post('status');

            $validator = Validator::make(
                array(
                    'merchant_id'        => $merchant_id,
                    'object_name'        => $object_name,
                    'object_type'        => $object_type,
                    'status'             => $status,
                ),
                array(
                    'merchant_id'        => 'required|orbit.empty.merchant',
                    'object_name'        => 'required|orbit.exists.object_name',
                    'object_type'        => 'required|orbit.empty.object_object_type',
                    'status'             => 'required|orbit.empty.object_status',
                )
            );

            Event::fire('orbit.object.postnewobject.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            Event::fire('orbit.object.postnewobject.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // save Object.
            $newobject = new Object();
            $newobject->merchant_id = $merchant_id;
            $newobject->object_name = $object_name;
            $newobject->object_type = $object_type;
            $newobject->status = $status;

            Event::fire('orbit.object.postnewobject.before.save', array($this, $newobject));

            $newobject->save();

            Event::fire('orbit.object.postnewobject.after.save', array($this, $newobject));
            $this->response->data = $newobject;

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activityNotes = sprintf('Object Created: %s', $newobject->object_name);
            $activity->setUser($user)
                    ->setActivityName('create_object')
                    ->setActivityNameLong('Create Object OK')
                    ->setObject($newobject)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.object.postnewobject.after.commit', array($this, $newobject));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.object.postnewobject.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_object')
                    ->setActivityNameLong('Create Object Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.object.postnewobject.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_object')
                    ->setActivityNameLong('Create Object Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.object.postnewobject.query.error', array($this, $e));

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
                    ->setActivityName('create_object')
                    ->setActivityNameLong('Create Object Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.object.postnewobject.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_object')
                    ->setActivityNameLong('Create Object Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save the activity
        $activity->save();

        return $this->render($httpCode);
    }

    /**
     * POST - Update Object
     *
     * @author Tian <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `object_id`             (required) - Object ID
     * @param integer    `merchant_id`           (optional) - Merchant ID
     * @param string     `object_name`           (optional) - Object name
     * @param string     `object_type`           (optional) - Object type. Valid value: bank, payment.
     * @param string     `status`                (optional) - Status. Valid value: active, inactive, pending, blocked, deleted.
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postUpdateObject()
    {
        $activity = Activity::portal()
                           ->setActivityType('update');

        $user = NULL;
        $updatedobject = NULL;
        try {
            $httpCode=200;

            Event::fire('orbit.object.postupdateobject.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.object.postupdateobject.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.object.postupdateobject.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('update_object')) {
                Event::fire('orbit.object.postupdateobject.authz.notallowed', array($this, $user));
                $updateObjectLang = Lang::get('validation.orbit.actionlist.update_object');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $updateObjectLang));
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

            Event::fire('orbit.object.postupdateobject.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $object_id = OrbitInput::post('object_id');
            $merchant_id = OrbitInput::post('current_mall');;
            $object_type = OrbitInput::post('object_type');
            $status = OrbitInput::post('status');

            $data = array(
                'object_id'        => $object_id,
                'merchant_id'      => $merchant_id,
                'object_type'      => $object_type,
                'status'           => $status,
            );

            // Validate object_name only if exists in POST.
            OrbitInput::post('object_name', function($object_name) use (&$data) {
                $data['object_name'] = $object_name;
            });

            $validator = Validator::make(
                $data,
                array(
                    'object_id'        => 'required|orbit.empty.object',
                    'merchant_id'      => 'orbit.empty.merchant',
                    'object_name'      => 'sometimes|required|object_name_exists_but_me',
                    'object_type'      => 'orbit.empty.object_object_type',
                    'status'           => 'orbit.empty.object_status',
                ),
                array(
                   'object_name_exists_but_me' => Lang::get('validation.orbit.exists.object_name'),
                )
            );

            Event::fire('orbit.object.postupdateobject.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.object.postupdateobject.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            $updatedobject = Object::excludeDeleted()->where('object_id', $object_id)->first();

            // save Object
            OrbitInput::post('merchant_id', function($merchant_id) use ($updatedobject) {
                $updatedobject->merchant_id = $merchant_id;
            });

            OrbitInput::post('object_name', function($object_name) use ($updatedobject) {
                $updatedobject->object_name = $object_name;
            });

            OrbitInput::post('object_type', function($object_type) use ($updatedobject) {
                $updatedobject->object_type = $object_type;
            });

            OrbitInput::post('status', function($status) use ($updatedobject) {
                $updatedobject->status = $status;
            });

            Event::fire('orbit.object.postupdateobject.before.save', array($this, $updatedobject));

            $updatedobject->save();

            Event::fire('orbit.object.postupdateobject.after.save', array($this, $updatedobject));
            $this->response->data = $updatedobject;

            // Commit the changes
            $this->commit();

            // Successfull Update
            $activityNotes = sprintf('Object updated: %s', $updatedobject->object_name);
            $activity->setUser($user)
                    ->setActivityName('update_object')
                    ->setActivityNameLong('Update Object OK')
                    ->setObject($updatedobject)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.object.postupdateobject.after.commit', array($this, $updatedobject));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.object.postupdateobject.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_object')
                    ->setActivityNameLong('Update Object Failed')
                    ->setObject($updatedobject)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.object.postupdateobject.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_object')
                    ->setActivityNameLong('Update Object Failed')
                    ->setObject($updatedobject)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.object.postupdateobject.query.error', array($this, $e));

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
                    ->setActivityName('update_object')
                    ->setActivityNameLong('Update Object Failed')
                    ->setObject($updatedobject)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.object.postupdateobject.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_object')
                    ->setActivityNameLong('Update Object Failed')
                    ->setObject($updatedobject)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save activity
        $activity->save();

        return $this->render($httpCode);

    }

    /**
     * POST - Delete Object
     *
     * @author Tian <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `object_id`                (required) - ID of the object
     * @param string     `password`                 (required) - master password
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeleteObject()
    {
        $activity = Activity::portal()
                          ->setActivityType('delete');

        $user = NULL;
        $deleteobject = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.object.postdeleteobject.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.object.postdeleteobject.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.object.postdeleteobject.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('delete_object')) {
                Event::fire('orbit.object.postdeleteobject.authz.notallowed', array($this, $user));
                $deleteObjectLang = Lang::get('validation.orbit.actionlist.delete_object');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $deleteObjectLang));
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

            Event::fire('orbit.object.postdeleteobject.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $object_id = OrbitInput::post('object_id');
            $password = OrbitInput::post('password');
            $mall_id = OrbitInput::post('current_mall');;

            $validator = Validator::make(
                array(
                    'merchant_id'=> $mall_id,
                    'object_id'  => $object_id,
                    'password'   => $password,
                ),
                array(
                    'merchant_id'=> 'required|orbit.empty.mall',
                    'object_id'  => 'required|orbit.empty.object',
                    'password'   => 'required|orbit.masterpassword.delete:' . $mall_id,
                ),
                array(
                    'required.password'             => 'The master is password is required.',
                    'orbit.masterpassword.delete'   => 'The password is incorrect.'
                )
            );

            Event::fire('orbit.object.postdeleteobject.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.object.postdeleteobject.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            $deleteobject = Object::excludeDeleted()->where('object_id', $object_id)->first();
            $deleteobject->status = 'deleted';

            Event::fire('orbit.object.postdeleteobject.before.save', array($this, $deleteobject));

            $deleteobject->save();

            Event::fire('orbit.object.postdeleteobject.after.save', array($this, $deleteobject));
            $this->response->data = null;
            $this->response->message = Lang::get('statuses.orbit.deleted.object');

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activityNotes = sprintf('Object Deleted: %s', $deleteobject->object_name);
            $activity->setUser($user)
                    ->setActivityName('delete_object')
                    ->setActivityNameLong('Delete Object OK')
                    ->setObject($deleteobject)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.object.postdeleteobject.after.commit', array($this, $deleteobject));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.object.postdeleteobject.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_object')
                    ->setActivityNameLong('Delete Object Failed')
                    ->setObject($deleteobject)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.object.postdeleteobject.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_object')
                    ->setActivityNameLong('Delete Object Failed')
                    ->setObject($deleteobject)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.object.postdeleteobject.query.error', array($this, $e));

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
                    ->setActivityName('delete_object')
                    ->setActivityNameLong('Delete Object Failed')
                    ->setObject($deleteobject)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.object.postdeleteobject.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_object')
                    ->setActivityNameLong('Delete Object Failed')
                    ->setObject($deleteobject)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        $output = $this->render($httpCode);

        // Save the activity
        $activity->save();

        return $output;
    }

    /**
     * GET - Search Object
     *
     * @author Tian <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string   `with`                  (optional) - Valid value: mall.
     * @param string   `sortby`                (optional) - column order by
     * @param string   `sortmode`              (optional) - asc or desc
     * @param integer  `take`                  (optional) - limit
     * @param integer  `skip`                  (optional) - limit offset
     * @param integer  `object_id`             (optional) - Object ID
     * @param integer  `merchant_id`           (optional) - Merchant ID
     * @param string   `object_name`           (optional) - Object name
     * @param string   `object_name_like`      (optional) - Object name like
     * @param string   `object_type`           (optional) - Object type. Valid value: bank, payment.
     * @param string   `status`                (optional) - Status. Valid value: active, inactive, pending, blocked, deleted.
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchObject()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.object.getsearchobject.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.object.getsearchobject.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.object.getsearchobject.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('view_object')) {
                Event::fire('orbit.object.getsearchobject.authz.notallowed', array($this, $user));
                $viewObjectLang = Lang::get('validation.orbit.actionlist.view_object');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewObjectLang));
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

            Event::fire('orbit.object.getsearchobject.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sortby');
            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                ),
                array(
                    'sort_by' => 'in:registered_date,object_name,object_type,status',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.object_sortby'),
                )
            );

            Event::fire('orbit.object.getsearchobject.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.object.getsearchobject.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.object.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.object.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            // Builder object
            $object = Object::excludeDeleted();

            // Filter object by Ids
            OrbitInput::get('object_id', function($objectIds) use ($object)
            {
                $object->whereIn('objects.object_id', $objectIds);
            });

            // Filter object by merchant Ids
            OrbitInput::get('merchant_id', function ($merchantIds) use ($object) {
                $object->whereIn('objects.merchant_id', (array)$merchantIds);
            });

            // Filter object by object name
            OrbitInput::get('object_name', function($objectname) use ($object)
            {
                $object->whereIn('objects.object_name', $objectname);
            });

            // Filter object by matching object name pattern
            OrbitInput::get('object_name_like', function($objectname) use ($object)
            {
                $object->where('objects.object_name', 'like', "%$objectname%");
            });

            // Filter object by object type
            OrbitInput::get('object_type', function($objectTypes) use ($object)
            {
                $object->whereIn('objects.object_type', $objectTypes);
            });

            // Filter object by status
            OrbitInput::get('status', function ($statuses) use ($object) {
                $object->whereIn('objects.status', $statuses);
            });

            // Add new relation based on request
            OrbitInput::get('with', function ($with) use ($object) {
                $with = (array) $with;

                foreach ($with as $relation) {
                    if ($relation === 'mall') {
                        $object->with('mall');
                    }
                }
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_object = clone $object;

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
            $object->take($take);

            $skip = 0;
            OrbitInput::get('skip', function($_skip) use (&$skip, $object)
            {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $object->skip($skip);

            // Default sort by
            $sortBy = 'objects.object_name';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'registered_date'   => 'objects.created_at',
                    'object_name'       => 'objects.object_name',
                    'object_type'       => 'objects.object_type',
                    'status'            => 'objects.status'
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });
            $object->orderBy($sortBy, $sortMode);

            $totalObject = RecordCounter::create($_object)->count();
            $listOfObject = $object->get();

            $data = new stdclass();
            $data->total_records = $totalObject;
            $data->returned_records = count($listOfObject);
            $data->records = $listOfObject;

            if ($totalObject === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.object');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.object.getsearchobject.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.object.getsearchobject.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.object.getsearchobject.query.error', array($this, $e));

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
            Event::fire('orbit.object.getsearchobject.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.object.getsearchobject.before.render', array($this, &$output));

        return $output;
    }

    protected function registerCustomValidation()
    {
        // Check the existance of object id
        Validator::extend('orbit.empty.object', function ($attribute, $value, $parameters) {
            $object = Object::excludeDeleted()
                        ->where('object_id', $value)
                        ->first();

            if (empty($object)) {
                return FALSE;
            }

            App::instance('orbit.empty.object', $object);

            return TRUE;
        });

        // Check the existance of merchant id
        Validator::extend('orbit.empty.merchant', function ($attribute, $value, $parameters) {
            $merchant = Mall::excludeDeleted()
                                ->where('merchant_id', $value)
                                ->first();

            if (empty($merchant)) {
                return FALSE;
            }

            App::instance('orbit.empty.merchant', $merchant);

            return TRUE;
        });

        // Check object name, it should not exists
        Validator::extend('orbit.exists.object_name', function ($attribute, $value, $parameters) {
            $objectName = Object::excludeDeleted()
                        ->where('object_name', $value)
                        ->first();

            if (! empty($objectName)) {
                return FALSE;
            }

            App::instance('orbit.validation.object_name', $objectName);

            return TRUE;
        });

        // Check object name, it should not exists (for update)
        Validator::extend('object_name_exists_but_me', function ($attribute, $value, $parameters) {
            $object_id = trim(OrbitInput::post('object_id'));
            $object = Object::excludeDeleted()
                        ->where('object_name', $value)
                        ->where('object_id', '!=', $object_id)
                        ->first();

            if (! empty($object)) {
                return FALSE;
            }

            App::instance('orbit.validation.object_name', $object);

            return TRUE;
        });

        // Check the existence of the object status
        Validator::extend('orbit.empty.object_status', function ($attribute, $value, $parameters) {
            $valid = false;
            $statuses = array('active', 'inactive', 'pending', 'blocked', 'deleted');
            foreach ($statuses as $status) {
                if($value === $status) $valid = $valid || TRUE;
            }

            return $valid;
        });

        // Check the existence of the object object type
        Validator::extend('orbit.empty.object_object_type', function ($attribute, $value, $parameters) {
            $valid = false;
            $objectTypes = array('bank', 'payment');
            foreach ($objectTypes as $objectType) {
                if($value === $objectType) $valid = $valid || TRUE;
            }

            return $valid;
        });

        // Object deletion master password
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

    }

}
