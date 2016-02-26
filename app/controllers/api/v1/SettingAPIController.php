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

    protected $settingViewRoles = ['super admin', 'mall admin', 'mall owner', 'campaign owner', 'campaign employee', 'mall customer service'];
    protected $settingModifiyRoles = ['super admin', 'mall admin', 'mall owner', 'campaign owner', 'campaign employee'];

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

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.setting.postupdatesetting.after.validation', array($this, $validator));

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
     * POST - Update Setting
     *
     * @author <Tian> <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string        `language`              (optional) - Mobile language in:en,id
     * @param files array   `backgrounds`           (optional) - Image background for mobile ci
     * @param string        `landing_page`          (optional) - in:widget,news,promotion,tenant
     * @param string        `password`              (optional) - Master password for deletion
     * @param string        `password_confirmation` (optional) - Master password confirmation
     *
     * @param integer       `id_language_default`   (optional) - ID language default
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postUpdateMallSetting()
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

/*
            if (! ACL::create($user)->isAllowed('update_setting')) {
                Event::fire('orbit.setting.postupdatesetting.authz.notallowed', array($this, $user));
                $updateSettingLang = Lang::get('validation.orbit.actionlist.update_setting');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $updateSettingLang));
                ACL::throwAccessForbidden($message);
            }
*/
            Event::fire('orbit.setting.postupdatesetting.after.authz', array($this, $user));

            $role = $user->role;
            $validRoles = ['super admin', 'mall admin', 'mall owner'];
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            $this->registerCustomValidation();

            $language = OrbitInput::post('language', NULL);
            $background = OrbitInput::files('backgrounds');
            $mall_logo = OrbitInput::files('logo');
            $landingPage = OrbitInput::post('landing_page');
            $password = OrbitInput::post('password');
            $password2 = OrbitInput::post('password_confirmation');
            $id_language_default = OrbitInput::post('id_language_default');
            $background_config = Config::get('orbit.upload.retailer.background');
            $logo_config = Config::get('orbit.upload.mall.logo');
            $background_units = static::bytesToUnits($background_config['file_size']);
            $logo_units = static::bytesToUnits($logo_config['file_size']);

            // Catch the supported language for mall
            $supportedMallLanguageIds = OrbitInput::post('mall_supported_language_ids');
            $supportedMallLanguageIds_copy = OrbitInput::post('mall_supported_language_ids');

            $mall_id = OrbitInput::post('current_mall');

            $validator = Validator::make(
                array(
                    'current_mall'          => $mall_id,
                    'language'              => $language,
                    'landing_page'          => $landingPage,
                    'password'              => $password,
                    'password_confirmation' => $password2,
                    'id_language_default'   => $id_language_default,
                    'background_type'       => $background['type'],
                    'logo_type'             => $mall_logo['type'],
                    'background_size'       => $background['size'],
                    'logo_size'             => $mall_logo['size'],
                ),
                array(
                    'current_mall'        => 'required|orbit.empty.mall',
                    'language'            => 'required',
                    'landing_page'        => 'required|in:widget,news,promotion,tenant,my-coupon,lucky-draw',
                    'password'            => 'min:5|confirmed',
                    'id_language_default' => 'required|orbit.empty.language_default',
                    'background_type'     => 'in:image/jpg,image/png,image/jpeg,image/gif',
                    'logo_type'           => 'in:image/jpg,image/png,image/jpeg,image/gif',
                    'background_size'     => 'orbit.max.file_size:' . $background_config['file_size'],
                    'logo_size'           => 'orbit.max.file_size:' . $logo_config['file_size'],
                ),
                array(
                    'background_size.orbit.max.file_size' => 'Login Page Background Image size is too big, maximum size allowed is '. $background_units['newsize'] . $background_units['unit'],
                    'logo_size.orbit.max.file_size' => 'Mobile Toolbar Logo Image size is too big, maximum size allowed is '. $logo_units['newsize'] . $logo_units['unit']
                )
            );

            Event::fire('orbit.setting.postupdatesetting.before.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.setting.postupdatesetting.after.validation', array($this, $validator));

            // disabled - the current mall id now using current_mall param
            // $setting = Setting::active()->where('setting_name', 'current_retailer')->first();
            // if (empty($setting)) {
            //     $errorMessage = 'Could not find current active mall from setting.';
            //     OrbitShopAPI::throwInvalidArgument($errorMessage);
            // }

            $mall = Mall::excludeDeleted()
                ->where('merchant_id', $mall_id)
                ->firstOrFail();

            $backgroundSetting = NULL;
            $masterPasswordSetting = NULL;
            $landingPageSetting = NULL;
            $startButtonSetting = NULL;
            $dataMerchantLanguage = NULL;

            $updatedsetting = Setting::active()
                                     ->where('object_id', $mall->merchant_id)
                                     ->where('object_type', 'merchant')
                                     ->get();

            foreach ($updatedsetting as $currentSetting) {
                if ($currentSetting->setting_name === 'master_password') {
                    $masterPasswordSetting = $currentSetting;
                }

                if ($currentSetting->setting_name === 'landing_page') {
                    $landingPageSetting = $currentSetting;
                }

                if ($currentSetting->setting_name === 'background_image') {
                    $backgroundSetting = $currentSetting;
                }

                if ($currentSetting->setting_name === 'start_button_label') {
                    $startButtonSetting = $currentSetting;
                }
            }

            OrbitInput::post('password', function($passwd) use (&$masterPasswordSetting, $mall, $user) {
                // Master password setting
                if (is_null($masterPasswordSetting)) {
                    $masterPasswordSetting = new Setting();
                    $masterPasswordSetting->setting_name = 'master_password';
                    $masterPasswordSetting->object_id = $mall->merchant_id;
                    $masterPasswordSetting->object_type = 'merchant';
                }

                $masterPasswordSetting->setting_value = Hash::make($passwd);
                $masterPasswordSetting->modified_by = $user->user_id;
                $masterPasswordSetting->save();
            });

            OrbitInput::post('landing_page', function($page) use (&$landingPageSetting, $mall, $user) {
                // Landing page setting
                if (is_null($landingPageSetting)) {
                    $landingPageSetting = new Setting();
                    $landingPageSetting->setting_name = 'landing_page';
                    $landingPageSetting->object_id = $mall->merchant_id;
                    $landingPageSetting->object_type = 'merchant';
                }

                $landingPageSetting->setting_value = $page;
                $landingPageSetting->modified_by = $user->user_id;
                $landingPageSetting->save();
            });

            OrbitInput::post('language', function($lang) use ($mall) {
                $mall->mobile_default_language = $lang;
                $mall->save();
            });

            OrbitInput::files('backgrounds', function($files) use ($mall, $user, &$backgroundSetting) {
                $_POST['merchant_id'] = $mall->merchant_id;

                // This will be used on UploadAPIController
                App::instance('orbit.upload.user', $user);

                $response = UploadAPIController::create('raw')
                                               ->setCalledFrom('mall.update')
                                               ->postUploadMallBackground();

                if ($response->code !== 0)
                {
                    throw new \Exception($response->message, $response->code);
                }

                $mall->setRelation('mediaBackground', $response->data);
                $mall->media_background = $response->data;
            });

            OrbitInput::post('backgrounds', function($files_string) use ($mall, $user, &$backgroundSetting) {
                if (empty(trim($files_string))) {
                    $_POST['merchant_id'] = $mall->merchant_id;

                    // This will be used on UploadAPIController
                    App::instance('orbit.upload.user', $user);

                    $response = UploadAPIController::create('raw')
                                                   ->setCalledFrom('mall.update')
                                                   ->postDeleteMallBackground();

                    if ($response->code !== 0)
                    {
                        throw new \Exception($response->message, $response->code);
                    }

                    $mall->setRelation('mediaBackground', $response->data);
                    $mall->media_background = $response->data;
                }
            });

            OrbitInput::files('logo', function($files) use ($mall, $user) {
                $_POST['merchant_id'] = $mall->merchant_id;

                // This will be used on UploadAPIController
                App::instance('orbit.upload.user', $user);

                $response = UploadAPIController::create('raw')
                                               ->setCalledFrom('mall.update')
                                               ->postUploadMallLogo();

                if ($response->code !== 0)
                {
                    throw new \Exception($response->message, $response->code);
                }

                $mall->load('mediaLogo');
            });

            OrbitInput::post('logo', function($files_string) use ($mall, $user) {
                if (empty(trim($files_string))) {
                    $_POST['merchant_id'] = $mall->merchant_id;

                    // This will be used on UploadAPIController
                    App::instance('orbit.upload.user', $user);

                    $response = UploadAPIController::create('raw')
                                                   ->setCalledFrom('mall.update')
                                                   ->postDeleteMallLogo();

                    if ($response->code !== 0)
                    {
                        throw new \Exception($response->message, $response->code);
                    }

                    $mall->load('mediaLogo');
                }
            });

            OrbitInput::post('start_button', function($label) use (&$startButtonSetting, $mall, $user) {
                // Start button label setting
                if (is_null($startButtonSetting)) {
                    $startButtonSetting = new Setting();
                    $startButtonSetting->setting_name = 'start_button_label';
                    $startButtonSetting->object_id = $mall->merchant_id;
                    $startButtonSetting->object_type = 'merchant';
                }

                $startButtonSetting->setting_value = $label;
                $startButtonSetting->modified_by = $user->user_id;
                $startButtonSetting->save();
            });

            // Save the default language setting for start button
            $default_translation = [
                $id_language_default => [
                    'setting_value' => $startButtonSetting->setting_value,
                ]
            ];
            $this->validateAndSaveTranslations($startButtonSetting, json_encode($default_translation), 'create');

            OrbitInput::post('translations', function($translation_json_string) use ($startButtonSetting) {
                $this->validateAndSaveTranslations($startButtonSetting, $translation_json_string, 'create');
            });


            $validator = Validator::make(
                array(
                    'merchant_id'   => $mall->merchant_id,
                    'language_id'   => $supportedMallLanguageIds,
                ),
                array(
                    'merchant_id'   => 'required|orbit.empty.merchant',
                    'language_id'   => 'required',
                )
            );

            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            foreach ($supportedMallLanguageIds as $language_id_check) {
                $validator = Validator::make(
                    array(
                        'language_id'   => $language_id_check,
                    ),
                    array(
                        'language_id'   => 'required|orbit.empty.language',
                    )
                );

                // Run the validation
                if ($validator->fails()) {
                    $errorMessage = $validator->messages()->first();
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }
            }

            Event::fire('orbit.news.postlanguage.before.validation', array($this, $validator));

            // Check old merchant language
            $oldMallLanguage = MerchantLanguage::where('merchant_id','=', $mall->merchant_id)->get();

            // Compare  old and new data of merchant language
            foreach ($oldMallLanguage as $key => $valDeleted) {
                if (!in_array($valDeleted->language_id, $supportedMallLanguageIds, TRUE)) {
                    // inactive merchant language
                    $merchantLanguage = MerchantLanguage::find($valDeleted->merchant_language_id);
                    $merchantLanguage->status = 'deleted';
                    // $merchantLanguage->status = 'inactive';
                    $merchantLanguage->save();
                } else {
                    $keyArray = array_search($valDeleted->language_id, $supportedMallLanguageIds);
                    // this data array will be inserted
                    unset($supportedMallLanguageIds[$keyArray]);
                }
            }

            // Re-activate merchant lamguage
            $oldMallLanguageInactive = MerchantLanguage::where('merchant_id','=', $mall->merchant_id)->where('status','=', 'deleted')->get();
            foreach ($oldMallLanguageInactive as $key => $valInactive) {
                if (in_array($valInactive->language_id, $supportedMallLanguageIds_copy, TRUE)) {
                    // active merchant language
                    $merchantLanguage = MerchantLanguage::find($valInactive->merchant_language_id);
                    $merchantLanguage->status = 'active';
                    $merchantLanguage->save();
                }
            }

            // Insert new merchant language
            if (count($supportedMallLanguageIds) > 0) {
                foreach ($supportedMallLanguageIds as $key => $value) {
                    $merchantLanguage = new MerchantLanguage();
                    $merchantLanguage->merchant_id = $mall->merchant_id;
                    $merchantLanguage->language_id = $value;
                    $merchantLanguage->language_id = $value;
                    $merchantLanguage->save();
                }
            }

            // Return new merchant language data
            $dataMerchantLanguage = MerchantLanguage::with('language')
                                    ->excludeDeleted()
                                    ->where('merchant_id','=', $mall->merchant_id)
                                    ->where('status','=', 'active')->get();

            $this->response->data = [
                'landing_page'      => $landingPageSetting,
                'background'        => $backgroundSetting,
                'mall'              => $mall,
                'start_button'      => $startButtonSetting,
                'merchant_language' => $dataMerchantLanguage
            ];

            // Commit the changes
            $this->commit();

            // Successfull Update
            $activityNotes = sprintf('Setting updated for mall: %s', $mall->name);
            $activity->setUser($user)
                    ->setActivityName('update_setting')
                    ->setActivityNameLong('Update Setting OK')
                    ->setObject($mall)
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
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.setting.postupdatesetting.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = $e->getLine();

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_setting')
                    ->setActivityNameLong('Update Setting Failed')
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
/*
            if (! ACL::create($user)->isAllowed('view_setting')) {
                Event::fire('orbit.setting.getsearchsetting.authz.notallowed', array($this, $user));
                $viewSettingLang = Lang::get('validation.orbit.actionlist.view_setting');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewSettingLang));
                ACL::throwAccessForbidden($message);
            }
*/
            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->settingViewRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
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

            // Filter setting by current_mall
            OrbitInput::get('current_mall', function ($current_mall) use ($settings) {
                $settings->where('settings.object_id', $current_mall);
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

            // Append widget template if param with=widget_template is specified
            OrbitInput::get('with', function ($with) use ($listOfSettings) {
                $with = (array) $with;
                foreach ($with as $wth) {
                    if ($wth === 'widget_template') {
                        $widgetTemplateSetting = NULL;
                        foreach ($listOfSettings as $currentSetting) {
                            if ($currentSetting->setting_name === 'widget_template') {
                                if (! empty($currentSetting->setting_value)) {
                                    $currentSetting->widget_template = WidgetTemplate::where('widget_template_id', $currentSetting->setting_value)->first();
                                } else {
                                    $currentSetting->widget_template = NULL;
                                }
                            } else {
                                $currentSetting->widget_template = NULL;
                            }
                        }
                    }
                }
            });

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

    /**
     * GET - Agreement Setting
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getAgreement()
    {
        try {
            $httpCode = 200;

            $this->registerCustomValidation();

            // set mall id
            $mallId = OrbitInput::get('current_mall');

            $validator = Validator::make(
                array(
                    'current_mall' => $mallId
                ),
                array(
                    'current_mall' => 'required|orbit.empty.mall'
                )
            );

            Event::fire('orbit.setting.getagreement.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // Builder object
            $settings = Setting::excludeDeleted()
                               ->where('object_type', 'merchant')
                               ->where('object_id', $mallId)
                               ->where('setting_name', 'agreement')
                               ->where('status', 'active')
                               ->first();

            if (empty($settings)) {
                $agreement = 'no';
            } else {
                $agreement = $settings->setting_value;
            }

            $this->response->data = $agreement;

        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.setting.getagreement.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.setting.getagreement.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.setting.getagreement.query.error', array($this, $e));

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
            Event::fire('orbit.setting.getagreement.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.setting.getagreement.before.render', array($this, &$output));

        return $output;
    }

    /**
     * POST - Create/update Agreement setting
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postUpdateAgreement()
    {
        $activity = Activity::portal()
                           ->setActivityType('update');

        $user = NULL;
        $updatedsetting = NULL;
        try {
            $httpCode=200;

            $this->registerCustomValidation();

            // set mall id
            $mallId = OrbitInput::post('current_mall');;

            $validator = Validator::make(
                array(
                    'current_mall' => $mallId
                ),
                array(
                    'current_mall' => 'required|orbit.empty.mall'
                )
            );

            Event::fire('orbit.setting.getagreement.before.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $setting_name = 'agreement';
            $setting_value = 'yes';
            $object_type = 'merchant';
            $status = 'active';

            $updatedsetting = Setting::excludeDeleted()
                                     ->where('object_type', $object_type)
                                     ->where('object_id', $mallId)
                                     ->where('setting_name', $setting_name)
                                     ->where('status', $status)
                                     ->first();

            if (empty($updatedsetting)) {
                // do insert
                $updatedsetting = new Setting();
                $updatedsetting->setting_name = $setting_name;
                $updatedsetting->setting_value = $setting_value;
                $updatedsetting->object_id = $mallId;
                $updatedsetting->object_type = $object_type;
                $updatedsetting->status = $status;

                Event::fire('orbit.setting.postupdateagreement.before.save', array($this, $updatedsetting));

                $updatedsetting->save();

                Event::fire('orbit.setting.postupdateagreement.after.save', array($this, $updatedsetting));

            } else {
                // do update
                $updatedsetting->setting_value = $setting_value;

                Event::fire('orbit.setting.postupdateagreement.before.save', array($this, $updatedsetting));

                $updatedsetting->save();

                Event::fire('orbit.setting.postupdateagreement.after.save', array($this, $updatedsetting));
            }

            $this->response->data = $setting_value;

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

            Event::fire('orbit.setting.postupdateagreement.after.commit', array($this, $updatedsetting));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.setting.postupdateagreement.access.forbidden', array($this, $e));

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
            Event::fire('orbit.setting.postupdateagreement.invalid.arguments', array($this, $e));

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
            Event::fire('orbit.setting.postupdateagreement.query.error', array($this, $e));

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
            Event::fire('orbit.setting.postupdateagreement.general.exception', array($this, $e));

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
     * GET - Mobile CI Signin Language
     *
     * @author <kadek> <kadek@dominopos.com>
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getMobileCiSigninLanguage()
    {
        try {
            $httpCode=200;

            Event::fire('orbit.setting.getmobilecisigninlanguage.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.setting.getmobilecisigninlanguage.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.setting.getmobilecisigninlanguage.before.authz', array($this, $user));

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->settingViewRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            $langs = array();
            App::setLocale('en');
            $langs['en'] = Lang::get('mobileci.signin');
            App::setLocale('ja');
            $langs['ja'] = Lang::get('mobileci.signin');
            App::setLocale('zh');
            $langs['zh'] = Lang::get('mobileci.signin');
            App::setLocale('en');

            $this->response->data = $langs;

        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.setting.getmobilecisigninlanguage.access.forbidden', array($this, $e));
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.setting.getmobilecisigninlanguage.invalid.arguments', array($this, $e));
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        }  catch (Exception $e) {
            Event::fire('orbit.setting.getmobilecisigninlanguage.general.exception', array($this, $e));
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }
        return $this->render($httpCode);
    }

    protected function registerCustomValidation()
    {
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

        // Check the existence of the setting status
        Validator::extend('orbit.empty.setting_status', function ($attribute, $value, $parameters) {
            $valid = false;
            $statuses = array('active', 'inactive', 'deleted');
            foreach ($statuses as $status) {
                if($value === $status) $valid = $valid || TRUE;
            }

            return $valid;
        });

        Validator::extend('orbit.empty.mall', function ($attribute, $value, $parameters) {
            $merchant = Mall::excludeDeleted()
                        ->where('merchant_id', $value)
                        ->first();

            if (empty($merchant)) {
                return FALSE;
            }

            App::instance('orbit.empty.mall', $merchant);

            return TRUE;
        });

        $user = $this->api->user;
        Validator::extend('orbit.empty.merchant', function ($attribute, $value, $parameters) use ($user) {
            $merchant = Mall::excludeDeleted()
                /* ->allowedForUser($user) */
                ->where('merchant_id', $value)
                /* ->where('is_mall', 'yes') */
                ->first();

            if (empty($merchant)) {
                return false;
            }

            App::instance('orbit.empty.merchant', $merchant);

            return true;
        });


        Validator::extend('orbit.empty.language', function ($attribute, $value, $parameters) {
            $language = Language::where('language_id', $value)->where('status', '=', 'active')->first();
            if (empty($language)) {
                return false;
            }
            App::instance('orbit.empty.language', $language);
            return true;
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
     * @param SettingModel $event
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
         * where SettingTranslation object is object with keys:
         *   setting_value
         *
         * No requirement for including fields. If field not included it means not updated. If field included with
         * value null it means set to null (use main language content instead).
         */

        $valid_fields = ['setting_value'];
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
            };
            $existing_translation = SettingTranslation::excludeDeleted()
                ->where('setting_id', '=', $event->setting_id)
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
                $new_translation = new SettingTranslation();
                $new_translation->setting_id = $event->setting_id;
                $new_translation->merchant_language_id = $operation[1];
                $data = $operation[2];
                foreach ($data as $field => $value) {
                    $new_translation->{$field} = $value;
                }
                $new_translation->created_by = $this->api->user->user_id;
                $new_translation->modified_by = $this->api->user->user_id;
                $new_translation->save();

                $event->setRelation('translation_'. $new_translation->merchant_language_id, $new_translation);
            }
            elseif ($op === 'update') {

                /** @var SettingTranslation $existing_translation */
                $existing_translation = $operation[1];
                $data = $operation[2];
                foreach ($data as $field => $value) {
                    $existing_translation->{$field} = $value;
                }
                $existing_translation->modified_by = $this->api->user->user_id;
                $existing_translation->save();

                $event->setRelation('translation_'. $existing_translation->merchant_language_id, $existing_translation);
            }
            elseif ($op === 'delete') {
                /** @var SettingTranslation $existing_translation */
                $existing_translation = $operation[1];
                $existing_translation->modified_by = $this->api->user->user_id;
                $existing_translation->delete();
            }
        }
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
