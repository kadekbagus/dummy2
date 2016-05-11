<?php
/**
 * An API controller for managing Membership.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Helper\EloquentRecordCounter as RecordCounter;

class MembershipAPIController extends ControllerAPI
{
    /**
     * POST - Create New Membership
     *
     * @author Tian <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string     `mall_id`               (required) - Mall ID
     * @param string     `membership_name`       (required) - Membership name
     * @param string     `status`                (required) - Status. Valid value: active, inactive, deleted
     * @param string     `description`           (optional) - Description.
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postNewMembership()
    {
        $activity = Activity::portal()
                            ->setActivityType('create');

        $user = NULL;
        $new = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.membership.postnewmembership.before.auth', array($this));

            $this->checkAuth();

            Event::fire('orbit.membership.postnewmembership.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.membership.postnewmembership.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('create_membership')) {
                Event::fire('orbit.membership.postnewmembership.authz.notallowed', array($this, $user));
                $createMembershipLang = Lang::get('validation.orbit.actionlist.new_membership');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $createMembershipLang));
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

            Event::fire('orbit.membership.postnewmembership.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $mall_id = OrbitInput::post('mall_id');
            $membership_name = OrbitInput::post('membership_name');
            $description = OrbitInput::post('description');
            $status = OrbitInput::post('status');

            $validator = Validator::make(
                array(
                    'mall_id'            => $mall_id,
                    'membership_name'    => $membership_name,
                    'status'             => $status,
                ),
                array(
                    'mall_id'            => 'required|orbit.empty.mall',
                    'membership_name'    => 'required|orbit.exists.membership_name',
                    'status'             => 'required|orbit.empty.membership_status',
                )
            );

            Event::fire('orbit.membership.postnewmembership.before.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            Event::fire('orbit.membership.postnewmembership.after.validation', array($this, $validator));

            // save Membership.
            $new = new Membership();
            $new->merchant_id = $mall_id;
            $new->membership_name = $membership_name;
            $new->description = $description;
            $new->status = $status;
            $new->created_by = $user->user_id;

            Event::fire('orbit.membership.postnewmembership.before.save', array($this, $new));

            $new->save();

            Event::fire('orbit.membership.postnewmembership.after.save', array($this, $new));
            $this->response->data = $new;

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activityNotes = sprintf('Membership Created: %s', $new->membership_name);
            $activity->setUser($user)
                     ->setActivityName('create_membership')
                     ->setActivityNameLong('Create Membership OK')
                     ->setObject($new)
                     ->setNotes($activityNotes)
                     ->responseOK();

            Event::fire('orbit.membership.postnewmembership.after.commit', array($this, $new));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.membership.postnewmembership.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                     ->setActivityName('create_membership')
                     ->setActivityNameLong('Create Membership Failed')
                     ->setNotes($e->getMessage())
                     ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.membership.postnewmembership.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                     ->setActivityName('create_membership')
                     ->setActivityNameLong('Create Membership Failed')
                     ->setNotes($e->getMessage())
                     ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.membership.postnewmembership.query.error', array($this, $e));

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
                     ->setActivityName('create_membership')
                     ->setActivityNameLong('Create Membership Failed')
                     ->setNotes($e->getMessage())
                     ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.membership.postnewmembership.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                     ->setActivityName('create_membership')
                     ->setActivityNameLong('Create Membership Failed')
                     ->setNotes($e->getMessage())
                     ->responseFailed();
        }

        // Save the activity
        $activity->save();

        return $this->render($httpCode);
    }

    /**
     * POST - Update Membership
     *
     * @author Tian <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string     `membership_id`             (required) - Membership ID
     * @param string     `mall_id`                   (optional) - Mall ID
     * @param string     `membership_name`           (optional) - Membership name
     * @param string     `description`               (optional) - Description
     * @param string     `status`                    (optional) - Status. Valid value: active, inactive, deleted
     * @param string     `enable_membership_card`    (optional) - Enable membership card. Valid value: true, false
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postUpdateMembership()
    {
        $activity = Activity::portal()
                            ->setActivityType('update');

        $user = NULL;
        $update = NULL;
        try {
            $httpCode=200;

            Event::fire('orbit.membership.postupdatemembership.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.membership.postupdatemembership.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.membership.postupdatemembership.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('update_membership')) {
                Event::fire('orbit.membership.postupdatemembership.authz.notallowed', array($this, $user));
                $updateMembershipLang = Lang::get('validation.orbit.actionlist.update_membership');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $updateMembershipLang));
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

            Event::fire('orbit.membership.postupdatemembership.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $membership_id = OrbitInput::post('membership_id');
            $mall_id = OrbitInput::post('mall_id');
            $status = OrbitInput::post('status');
            $enable_membership_card = OrbitInput::post('enable_membership_card');
            $membership_images = OrbitInput::files('images');
            $membership_images_config = Config::get('orbit.upload.membership.main');;
            $membership_images_units = static::bytesToUnits($membership_images_config['file_size']);

            // get user mall_ids
            $listOfMallIds = $user->getUserMallIds($mall_id);
            if (empty($listOfMallIds)) { // invalid mall id
                $errorMessage = 'Invalid mall id.';
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // auto create membership card
            if (trim($membership_id) === '') {
                /*
                 * Get membership card id based on mall id
                 */
                $membershipCard = Membership::active()
                                            ->whereIn('merchant_id', $listOfMallIds)
                                            ->first();

                // create membership card if not exists
                if (empty($membershipCard)) {
                    // create
                    $membershipCard = new Membership();
                    $membershipCard->merchant_id = $listOfMallIds[0];
                    $membershipCard->membership_name = 'Standard Card';
                    $membershipCard->status = 'active';
                    $membershipCard->created_by = $user->user_id;
                    $membershipCard->save();
                }

                $membership_id = $membershipCard->membership_id;
            }

            $data = array(
                'membership_id' => $membership_id,
                'mall_id'       => $mall_id,
                'status'        => $status,
                'images_type'   => $membership_images['type'],
                'images_size'   => $membership_images['size'],
            );

            // Validate membership_name only if exists in POST.
            OrbitInput::post('membership_name', function($membership_name) use (&$data) {
                $data['membership_name'] = $membership_name;
            });

            // Validate enable_membership_card only if exists in POST
            OrbitInput::post('enable_membership_card', function($arg) use (&$data) {
                $data['enable_membership_card'] = $arg;
            });

            $validator = Validator::make(
                $data,
                array(
                    'membership_id'          => 'required|orbit.empty.membership',
                    'mall_id'                => 'orbit.empty.mall',
                    'membership_name'        => 'sometimes|required|membership_name_exists_but_me:' . $membership_id,
                    'status'                 => 'orbit.empty.membership_status',
                    'enable_membership_card' => 'sometimes|required|orbit.empty.enable_membership_card',
                    'images_type'            => 'in:image/jpg,image/png,image/jpeg,image/gif',
                    'images_size'            => 'orbit.max.file_size:' . $membership_images_config['file_size'],
                ),
                array(
                   'membership_name_exists_but_me' => Lang::get('validation.orbit.exists.membership_name'),
                   'orbit.max.file_size'           => 'Picture size is too big, maximum size allowed is ' . $membership_images_units['newsize'] . $membership_images_units['unit'],
                )
            );

            Event::fire('orbit.membership.postupdatemembership.before.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.membership.postupdatemembership.after.validation', array($this, $validator));

            $update = Membership::excludeDeleted()
                                ->where('membership_id', $membership_id)
                                ->first();

            // save Membership
            OrbitInput::post('mall_id', function ($arg) use ($update) {
                $update->merchant_id = $arg;
            });

            OrbitInput::post('membership_name', function ($arg) use ($update) {
                $update->membership_name = $arg;
            });

            OrbitInput::post('description', function ($arg) use ($update) {
                $update->description = $arg;
            });

            OrbitInput::post('status', function ($arg) use ($update) {
                $update->status = $arg;
            });

            $update->modified_by = $user->user_id;

            // create/update enable_membership_card on table settings
            $settingName = 'enable_membership_card';

            $setting = Setting::active()
                              ->where('object_type', 'merchant')
                              ->whereIn('object_id', $listOfMallIds)
                              ->where('setting_name', $settingName)
                              ->first();

            if (empty($setting)) {
                // create
                $setting = new Setting();
                $setting->setting_name = $settingName;
                // if enable_membership_card is not being sent, set false as default value
                if (trim($enable_membership_card) === '') {
                    $setting->setting_value = 'false';
                } else {
                    $setting->setting_value = $enable_membership_card;
                }
                $setting->object_type = 'merchant';
                $setting->object_id = $listOfMallIds[0];
                $setting->status = 'active';
                $setting->modified_by = $user->user_id;
                $setting->save();
            } else {
                // update
                OrbitInput::post('enable_membership_card', function ($arg) use ($setting, $user) {
                    $setting->setting_value = $arg;
                    $setting->modified_by = $user->user_id;
                    $setting->save();
                });
            }

            // delete membership card image if images=""
            OrbitInput::post('images', function ($arg) use ($update) {
                if (trim($arg) === '') {
                    $_POST['membership_id'] = $update->membership_id;
                    $response = UploadAPIController::create('raw')
                                                   ->setCalledFrom('membership.update')
                                                   ->postDeleteMembershipImage();

                    if ($response->code !== 0)
                    {
                        throw new \Exception($response->message, $response->code);
                    }
                }
            });

            Event::fire('orbit.membership.postupdatemembership.before.save', array($this, $update));

            $update->save();

            Event::fire('orbit.membership.postupdatemembership.after.save', array($this, $update));

            $update->enable_membership_card = $setting->setting_value;
            $this->response->data = $update;

            // Commit the changes
            $this->commit();

            // Successfull Update
            $activityNotes = sprintf('Membership updated: %s', $update->membership_name);
            $activity->setUser($user)
                     ->setActivityName('update_membership')
                     ->setActivityNameLong('Update Membership OK')
                     ->setObject($update)
                     ->setNotes($activityNotes)
                     ->responseOK();

            Event::fire('orbit.membership.postupdatemembership.after.commit', array($this, $update));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.membership.postupdatemembership.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                     ->setActivityName('update_membership')
                     ->setActivityNameLong('Update Membership Failed')
                     ->setObject($update)
                     ->setNotes($e->getMessage())
                     ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.membership.postupdatemembership.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                     ->setActivityName('update_membership')
                     ->setActivityNameLong('Update Membership Failed')
                     ->setObject($update)
                     ->setNotes($e->getMessage())
                     ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.membership.postupdatemembership.query.error', array($this, $e));

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
                     ->setActivityName('update_membership')
                     ->setActivityNameLong('Update Membership Failed')
                     ->setObject($update)
                     ->setNotes($e->getMessage())
                     ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.membership.postupdatemembership.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                     ->setActivityName('update_membership')
                     ->setActivityNameLong('Update Membership Failed')
                     ->setObject($update)
                     ->setNotes($e->getMessage())
                     ->responseFailed();
        }

        // Save activity
        $activity->save();

        return $this->render($httpCode);

    }

    /**
     * POST - Delete Membership
     *
     * @author Tian <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     *
     * @param string     `mall_id`                  (required) - Mall ID
     * @param string     `membership_id`            (required) - Membership ID
     * @param string     `password`                 (required) - Master password
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeleteMembership()
    {
        $activity = Activity::portal()
                            ->setActivityType('delete');

        $user = NULL;
        $delete = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.membership.postdeletemembership.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.membership.postdeletemembership.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.membership.postdeletemembership.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('delete_membership')) {
                Event::fire('orbit.membership.postdeletemembership.authz.notallowed', array($this, $user));
                $deleteMembershipLang = Lang::get('validation.orbit.actionlist.delete_membership');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $deleteMembershipLang));
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

            Event::fire('orbit.membership.postdeletemembership.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $mall_id = OrbitInput::post('mall_id');
            $membership_id = OrbitInput::post('membership_id');
            $password = OrbitInput::post('password');

            $validator = Validator::make(
                array(
                    'mall_id'          => $mall_id,
                    'membership_id'    => $membership_id,
                    'password'         => $password,
                ),
                array(
                    'mall_id'          => 'required|orbit.empty.mall',
                    'membership_id'    => 'required|orbit.empty.membership',
                    'password'         => 'required|orbit.masterpassword.delete:' . $mall_id,
                ),
                array(
                    'required.password'             => 'The master is password is required.',
                    'orbit.masterpassword.delete'   => 'The password is incorrect.'
                )
            );

            Event::fire('orbit.membership.postdeletemembership.before.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.membership.postdeletemembership.after.validation', array($this, $validator));

            $delete = Membership::excludeDeleted()
                                ->where('membership_id', $membership_id)
                                ->first();

            $delete->status = 'deleted';

            Event::fire('orbit.membership.postdeletemembership.before.save', array($this, $delete));

            $delete->save();

            Event::fire('orbit.membership.postdeletemembership.after.save', array($this, $delete));

            $this->response->data = null;
            $this->response->message = Lang::get('statuses.orbit.deleted.membership');

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activityNotes = sprintf('Membership Deleted: %s', $delete->membership_name);
            $activity->setUser($user)
                     ->setActivityName('delete_membership')
                     ->setActivityNameLong('Delete Membership OK')
                     ->setObject($delete)
                     ->setNotes($activityNotes)
                     ->responseOK();

            Event::fire('orbit.membership.postdeletemembership.after.commit', array($this, $delete));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.membership.postdeletemembership.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                     ->setActivityName('delete_membership')
                     ->setActivityNameLong('Delete Membership Failed')
                     ->setObject($delete)
                     ->setNotes($e->getMessage())
                     ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.membership.postdeletemembership.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                     ->setActivityName('delete_membership')
                     ->setActivityNameLong('Delete Membership Failed')
                     ->setObject($delete)
                     ->setNotes($e->getMessage())
                     ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.membership.postdeletemembership.query.error', array($this, $e));

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
                     ->setActivityName('delete_membership')
                     ->setActivityNameLong('Delete Membership Failed')
                     ->setObject($delete)
                     ->setNotes($e->getMessage())
                     ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.membership.postdeletemembership.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                     ->setActivityName('delete_membership')
                     ->setActivityNameLong('Delete Membership Failed')
                     ->setObject($delete)
                     ->setNotes($e->getMessage())
                     ->responseFailed();
        }

        $output = $this->render($httpCode);

        // Save the activity
        $activity->save();

        return $output;
    }

    /**
     * GET - Search Membership
     *
     * @author Tian <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string   `with`                  (optional) - Valid value: mall
     * @param string   `sortby`                (optional) - Sort by
     * @param string   `sortmode`              (optional) - Sort mode. Valid value: asc, desc
     * @param integer  `take`                  (optional) - Limit
     * @param integer  `skip`                  (optional) - Limit offset
     * @param string   `membership_id`         (optional) - Membership ID
     * @param string   `mall_id`               (optional) - Mall ID
     * @param string   `membership_name`       (optional) - Membership name
     * @param string   `membership_name_like`  (optional) - Membership name like
     * @param string   `description`           (optional) - Description
     * @param string   `description_like`      (optional) - Description like
     * @param string   `status`                (optional) - Status. Valid value: active, inactive, deleted
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchMembership()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.membership.getsearchmembership.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.membership.getsearchmembership.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.membership.getsearchmembership.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('view_membership')) {
                Event::fire('orbit.membership.getsearchmembership.authz.notallowed', array($this, $user));
                $viewMembershipLang = Lang::get('validation.orbit.actionlist.view_membership');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewMembershipLang));
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

            Event::fire('orbit.membership.getsearchmembership.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $mall_id = OrbitInput::get('mall_id');
            $sort_by = OrbitInput::get('sortby');

            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                ),
                array(
                    'sort_by' => 'in:registered_date,membership_name,description,status',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.membership_sortby'),
                )
            );

            Event::fire('orbit.membership.getsearchmembership.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.membership.getsearchmembership.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.membership.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.membership.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            // Builder membership
            $record = Membership::excludeDeleted();

            // get user mall_ids
            $listOfMallIds = $user->getUserMallIds($mall_id);

            // filter mall based on user role
            if (empty($listOfMallIds)) { // invalid mall id
                $record->whereRaw('0');
            } elseif ($listOfMallIds[0] === 1) { // if super admin
                // show all users
            } else { // valid mall id
                $record->whereIn('memberships.merchant_id', $listOfMallIds);
            }

            // Filter membership by ids
            OrbitInput::get('membership_id', function ($arg) use ($record)
            {
                $record->whereIn('memberships.membership_id', (array)$arg);
            });

            // Filter membership by membership name
            OrbitInput::get('membership_name', function ($arg) use ($record)
            {
                $record->whereIn('memberships.membership_name', (array)$arg);
            });

            // Filter membership by matching membership name pattern
            OrbitInput::get('membership_name_like', function ($arg) use ($record)
            {
                $record->where('memberships.membership_name', 'like', "%$arg%");
            });

            // Filter membership by description
            OrbitInput::get('description', function ($arg) use ($record)
            {
                $record->whereIn('memberships.description', (array)$arg);
            });

            // Filter membership by matching description pattern
            OrbitInput::get('description_like', function ($arg) use ($record)
            {
                $record->where('memberships.description', 'like', "%$arg%");
            });

            // Filter membership by status
            OrbitInput::get('status', function ($arg) use ($record) {
                $record->whereIn('memberships.status', (array)$arg);
            });

            // Add new relation based on request
            OrbitInput::get('with', function ($with) use ($record) {
                $with = (array) $with;

                foreach ($with as $relation) {
                    if ($relation === 'mall') {
                        $record->with('mall');
                    } elseif ($relation === 'media') {
                        $record->with('media');
                    }
                }
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_record = clone $record;

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
            $record->take($take);

            $skip = 0;
            OrbitInput::get('skip', function($_skip) use (&$skip, $record)
            {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $record->skip($skip);

            // Default sort by
            $sortBy = 'memberships.membership_name';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'registered_date'   => 'memberships.created_at',
                    'membership_name'   => 'memberships.membership_name',
                    'description'       => 'memberships.description',
                    'status'            => 'memberships.status'
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });
            $record->orderBy($sortBy, $sortMode);

            $totalMembership = RecordCounter::create($_record)->count();
            $listOfMembership = $record->get();

            $data = new stdclass();

            // set enable_membership_card value from table settings
            $settingName = 'enable_membership_card';

            $setting = Setting::active()
                              ->where('object_type', 'merchant')
                              ->whereIn('object_id', $listOfMallIds)
                              ->where('setting_name', $settingName)
                              ->first();

            if (empty($setting)) {
                $data->enable_membership_card = 'false';
            } else {
                $data->enable_membership_card = $setting->setting_value;
            }

            $data->total_records = $totalMembership;
            $data->returned_records = count($listOfMembership);
            $data->records = $listOfMembership;

            if ($totalMembership === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.membership');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.membership.getsearchmembership.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.membership.getsearchmembership.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.membership.getsearchmembership.query.error', array($this, $e));

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
            Event::fire('orbit.membership.getsearchmembership.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.membership.getsearchmembership.before.render', array($this, &$output));

        return $output;
    }

    protected function registerCustomValidation()
    {
        // Check the existance of membership id
        Validator::extend('orbit.empty.membership', function ($attribute, $value, $parameters) {
            $membership = Membership::excludeDeleted()
                                    ->where('membership_id', $value)
                                    ->first();

            if (empty($membership)) {
                return FALSE;
            }

            App::instance('orbit.empty.membership', $membership);

            return TRUE;
        });

        // Check the existance of mall id
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

        // Check membership name, it should not exists
        Validator::extend('orbit.exists.membership_name', function ($attribute, $value, $parameters) {
            $membership = Membership::excludeDeleted()
                                    ->where('membership_name', $value)
                                    ->first();

            if (! empty($membership)) {
                return FALSE;
            }

            App::instance('orbit.validation.membership_name', $membership);

            return TRUE;
        });

        // Check membership name, it should not exists (for update)
        Validator::extend('membership_name_exists_but_me', function ($attribute, $value, $parameters) {
            $membership_id = $parameters[0];

            $membership = Membership::excludeDeleted()
                                    ->where('membership_name', $value)
                                    ->where('membership_id', '!=', $membership_id)
                                    ->first();

            if (! empty($membership)) {
                return FALSE;
            }

            App::instance('orbit.validation.membership_name', $membership);

            return TRUE;
        });

        // Check the existence of the membership status
        Validator::extend('orbit.empty.membership_status', function ($attribute, $value, $parameters) {
            $valid = false;
            $statuses = array('active', 'inactive', 'deleted');
            foreach ($statuses as $status) {
                if($value === $status) $valid = $valid || TRUE;
            }

            return $valid;
        });

        // Membership deletion master password
        Validator::extend('orbit.masterpassword.delete', function ($attribute, $value, $parameters) {
            $mall_id = $parameters[0];

            // Get the master password from settings table
            $masterPassword = Setting::getMasterPasswordFor($mall_id);

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

        // Check the existence of the enable_membership_card
        Validator::extend('orbit.empty.enable_membership_card', function ($attribute, $value, $parameters) {
            $valid = false;
            $statuses = array('true', 'false');
            foreach ($statuses as $status) {
                if($value === $status) $valid = $valid || TRUE;
            }

            return $valid;
        });

        Validator::extend('orbit.max.file_size', function ($attribute, $value, $parameters) {
            $config_size = $parameters[0];
            $file_size = $value;

            if ($file_size > $config_size) {
                return false;
            }

            return true;
        });

    }

    /**
     * Method to convert the size from bytes to more human readable units. As
     * an example:
     *
     * Input 356 produces => array('unit' => 'bytes', 'newsize' => 356)
     * Input 2045 produces => array('unit' => 'kB', 'newsize' => 2.045)
     * Input 1055000 produces => array('unit' => 'MB', 'newsize' => 1.055)
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @author Irianto <irianto@dominopos.com>
     * @param int $size - The size in bytes
     * @return array
     */
    public static function bytesToUnits($size)
    {
       $kb = 1000;
       $mb = $kb * 1000;
       $gb = $mb * 1000;

       if ($size > $gb) {
            return array(
                    'unit' => 'GB',
                    'newsize' => $size / $gb
                   );
       }

       if ($size > $mb) {
            return array(
                    'unit' => 'MB',
                    'newsize' => $size / $mb
                   );
       }

       if ($size > $kb) {
            return array(
                    'unit' => 'kB',
                    'newsize' => $size / $kb
                   );
       }

       return array(
                'unit' => 'bytes',
                'newsize' => 1
              );
    }

}
