<?php
/**
 * An API controller for managing Settings.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Helper\EloquentRecordCounter as RecordCounter;

class SettingAPIController extends ControllerAPI
{
    /**
     * POST - Update Setting
     *
     * @author <Tian> <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string     `setting_name`         (required) - Setting name
     * @param integer    `setting_value`        (required) - Setting value
     * @param integer    `object_id`            (optional) - Object ID
     * @param integer    `object_type`          (optional) - Object type
     * @param string     `status`               (optional) - Status. Valid value: active, inactive, deleted.
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postUpdateSetting()
    {
        $activity = Activity::portal()
                           ->setActivityType('update');

        $user = NULL;
        $updatedsetting = NULL;
        try {
            $httpCode=200;

            Event::fire('orbit.setting.postupdatesetting.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.setting.postupdatesetting.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.setting.postupdatesetting.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('update_setting')) {
                Event::fire('orbit.setting.postupdatesetting.authz.notallowed', array($this, $user));
                $updateSettingLang = Lang::get('validation.orbit.actionlist.update_setting');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $updateSettingLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.setting.postupdatesetting.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $setting_name = OrbitInput::post('setting_name');
            $setting_value = OrbitInput::post('setting_value');
            $object_id = OrbitInput::post('object_id');
            $object_type = OrbitInput::post('object_type');
            $status = OrbitInput::post('status');

            $validator = Validator::make(
                array(
                    'setting_name'     => $setting_name,
                    'setting_value'    => $setting_value,
                    'status'           => $status,
                ),
                array(
                    'setting_name'     => 'required',
                    'setting_value'    => 'required',
                    'status'           => 'orbit.empty.setting_status',
                )
            );

            Event::fire('orbit.setting.postupdatesetting.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.setting.postupdatesetting.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            $updatedsetting = Setting::excludeDeleted()->where('setting_name', $setting_name)->first();

            if (empty($updatedsetting)) {
                // do insert
                $updatedsetting = new Setting();
                $updatedsetting->setting_name = $setting_name;
                $updatedsetting->setting_value = $setting_value;
                $updatedsetting->object_id = $object_id;
                $updatedsetting->object_type = $object_type;
                if (trim($status) !== '') {
                    $updatedsetting->status = $status;
                }
                
                $updatedsetting->modified_by = $this->api->user->user_id;

                Event::fire('orbit.setting.postupdatesetting.before.save', array($this, $updatedsetting));

                $updatedsetting->save();

                Event::fire('orbit.setting.postupdatesetting.after.save', array($this, $updatedsetting));
            } else {
                // do update
                OrbitInput::post('setting_value', function($setting_value) use ($updatedsetting) {
                    $updatedsetting->setting_value = $setting_value;
                });

                OrbitInput::post('object_id', function($object_id) use ($updatedsetting) {
                    $updatedsetting->object_id = $object_id;
                });

                OrbitInput::post('object_type', function($object_type) use ($updatedsetting) {
                    $updatedsetting->object_type = $object_type;
                });

                OrbitInput::post('status', function($status) use ($updatedsetting) {
                    $updatedsetting->status = $status;
                });

                $updatedsetting->modified_by = $this->api->user->user_id;

                Event::fire('orbit.setting.postupdatesetting.before.save', array($this, $updatedsetting));

                $updatedsetting->save();

                Event::fire('orbit.setting.postupdatesetting.after.save', array($this, $updatedsetting));
            }

            $this->response->data = $updatedsetting;

            // Commit the changes
            $this->commit();

            // Successfull Update
            $activityNotes = sprintf('Setting updated: %s', $updatedsetting->setting_name);
            $activity->setUser($user)
                    ->setActivityName('update_setting')
                    ->setActivityNameLong('Update Setting OK')
                    ->setObject($updatedsetting)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.setting.postupdatesetting.after.commit', array($this, $updatedsetting));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.setting.postupdatesetting.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_setting')
                    ->setActivityNameLong('Update Setting Failed')
                    ->setObject($updatedsetting)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.setting.postupdatesetting.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_setting')
                    ->setActivityNameLong('Update Setting Failed')
                    ->setObject($updatedsetting)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.setting.postupdatesetting.query.error', array($this, $e));

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
                    ->setActivityName('update_setting')
                    ->setActivityNameLong('Update Setting Failed')
                    ->setObject($updatedsetting)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.setting.postupdatesetting.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_setting')
                    ->setActivityNameLong('Update Setting Failed')
                    ->setObject($updatedsetting)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save activity
        $activity->save();

        return $this->render($httpCode);

    }

    /**
     * GET - Search Setting
     *
     * @author Tian <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string   `sort_by`               (optional) - Column order by. Valid value: registered_date, setting_name, status.
     * @param string   `sort_mode`             (optional) - asc or desc
     * @param integer  `setting_id`            (optional) - Setting ID
     * @param string   `setting_name`          (optional) - Setting name
     * @param string   `setting_name_like`     (optional) - Setting name like
     * @param integer  `object_id`             (optional) - Object ID
     * @param string   `object_type`           (optional) - Object type
     * @param string   `object_type_like`      (optional) - Object type like
     * @param string   `status`                (optional) - Status
     * @param integer  `take`                  (optional) - limit
     * @param integer  `skip`                  (optional) - limit offset
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchSetting()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.setting.getsearchsetting.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.setting.getsearchsetting.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.setting.getsearchsetting.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('view_setting')) {
                Event::fire('orbit.setting.getsearchsetting.authz.notallowed', array($this, $user));
                $viewSettingLang = Lang::get('validation.orbit.actionlist.view_setting');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewSettingLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.setting.getsearchsetting.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sortby');
            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                ),
                array(
                    'sort_by' => 'in:registered_date,setting_name,status',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.setting_sortby'),
                )
            );

            Event::fire('orbit.setting.getsearchsetting.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.setting.getsearchsetting.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.setting.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.setting.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            // Builder object
            $settings = Setting::excludeDeleted();

            // Filter setting by Ids
            OrbitInput::get('setting_id', function($settingIds) use ($settings)
            {
                $settings->whereIn('settings.setting_id', $settingIds);
            });

            // Filter setting by setting name
            OrbitInput::get('setting_name', function($settingname) use ($settings)
            {
                $settings->whereIn('settings.setting_name', $settingname);
            });

            // Filter setting by matching setting name pattern
            OrbitInput::get('setting_name_like', function($settingname) use ($settings)
            {
                $settings->where('settings.setting_name', 'like', "%$settingname%");
            });

            // Filter setting by object Ids
            OrbitInput::get('object_id', function ($objectIds) use ($settings) {
                $settings->whereIn('settings.object_id', $objectIds);
            });

            // Filter setting by object type
            OrbitInput::get('object_type', function($objecttype) use ($settings)
            {
                $settings->whereIn('settings.object_type', $objecttype);
            });

            // Filter setting by matching object type pattern
            OrbitInput::get('object_type_like', function($objecttype) use ($settings)
            {
                $settings->where('settings.object_type', 'like', "%$objecttype%");
            });

            // Filter setting by status
            OrbitInput::get('status', function ($status) use ($settings) {
                $settings->whereIn('settings.status', $status);
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_settings = clone $settings;

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
            $settings->take($take);

            $skip = 0;
            OrbitInput::get('skip', function($_skip) use (&$skip, $settings)
            {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $settings->skip($skip);

            // Default sort by
            $sortBy = 'settings.setting_name';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'registered_date'   => 'settings.created_at',
                    'setting_name'      => 'settings.setting_name',
                    'status'            => 'settings.status'
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });
            $settings->orderBy($sortBy, $sortMode);

            $totalSettings = RecordCounter::create($_settings)->count();
            $listOfSettings = $settings->get();

            $data = new stdclass();
            $data->total_records = $totalSettings;
            $data->returned_records = count($listOfSettings);
            $data->records = $listOfSettings;

            if ($totalSettings === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.setting');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.setting.getsearchsetting.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.setting.getsearchsetting.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.setting.getsearchsetting.query.error', array($this, $e));

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
            Event::fire('orbit.setting.getsearchsetting.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.setting.getsearchsetting.before.render', array($this, &$output));

        return $output;
    }

    protected function registerCustomValidation()
    {
        // Check the existence of the setting status
        Validator::extend('orbit.empty.setting_status', function ($attribute, $value, $parameters) {
            $valid = false;
            $statuses = array('active', 'inactive', 'deleted');
            foreach ($statuses as $status) {
                if($value === $status) $valid = $valid || TRUE;
            }

            return $valid;
        });

    }
}