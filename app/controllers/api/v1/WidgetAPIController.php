<?php
/**
 * An API controller for managing widget.
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

class WidgetAPIController extends ControllerAPI
{
    /**
     * POST - Create new widget
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @author Firmansyah <firmansyah@dominopos.net>
     *
     * List of API Parameters
     * ----------------------
     * @param array     `widget`                (required) - Array of parameter collection
     * @param string    `type`                  (required) - Widget type, 'catalogue', 'new_product', 'promotion', 'coupon'
     * @param integer   `object_id`             (required) - The object ID
     * @param integer   `merchant_id`           (required) - Merchant ID
     * @param integer   `retailer_ids`          (required) - Retailer IDs
     * @param string    `animation`             (required) - Animation type, 'none', 'horizontal', 'vertical'
     * @param string    `slogan`                (required) - Widget slogan
     * @param integer   `widget_order`          (required) - Order of the widget
     * @param array     `image_widget_type`     (optional) - Widget_type is 'catalogue', 'new_product', 'promotion', 'coupon' example image_promotion
     * @param integer   `id_language_default`   (required) - ID language default
     * @return Illuminate\Support\Facades\Response
     */
    public function postNewWidget()
    {
        $activity = Activity::portal()
                            ->setActivityType('create');

        $user = NULL;
        $widget = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.widget.postnewwidget.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.widget.postnewwidget.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.widget.postnewwidget.before.authz', array($this, $user));

            // if (! ACL::create($user)->isAllowed('create_widget')) {
            //     Event::fire('orbit.widget.postnewwidget.authz.notallowed', array($this, $user));

            //     $errorMessage = Lang::get('validation.orbit.actionlist.add_new_widget');
            //     $message = Lang::get('validation.orbit.access.forbidden', array('action' => $errorMessage));

            //     ACL::throwAccessForbidden($message);
            // }

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = ['super admin', 'mall admin', 'mall owner'];
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.widget.postnewwidget.after.authz', array($this, $user));

            $this->registerCustomValidation();


            $widgetbatch = OrbitInput::post('widget');

            $validator = Validator::make(
                array(
                    'widget' => $widgetbatch,
                ),
                array(
                    'widget' => 'required|array',
                )
            );

            Event::fire('orbit.widget.postnewwidget.before.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            foreach ($widgetbatch as $key => $value) {
                $widgetType = $value['widget_type'];
                $widgetObjectId = $value['object_id'];
                $merchantId = $value['merchant_id'];
                // $retailerIds = $value['retailer_ids'];
                $slogan = $value['slogan'];
                $animation = $value['animation'];
                $widgetOrder = $value['widget_order'];
                $images = OrbitInput::files('widget');
                $idLanguageDefault = $value['id_language_default'];
                // $translations = $value['translation'];

                $validator = Validator::make(
                    array(
                        'object_id'             => $widgetObjectId,
                        'merchant_id'           => $merchantId,
                        'widget_type'           => $widgetType,
                        // 'retailer_ids'          => $retailerIds,
                        // 'slogan'                => $slogan,
                        'animation'             => $animation,
                        'widget_order'          => $widgetOrder,
                        // 'images'                => $images
                        'id_language_default'   => $idLanguageDefault,
                    ),
                    array(
                        'object_id'             => 'required',
                        'merchant_id'           => 'required|orbit.empty.merchant',
                        'widget_type'           => 'required|in:tenant,lucky_draw,promotion,coupon,news,service,free_wifi|orbit.exists.widget_type:' . $merchantId,
                        // 'slogan'                => 'required',
                        'animation'             => 'in:none,horizontal,vertical',
                        'widget_order'          => 'required|numeric',
                        // 'images'                => 'required_if:animation,none',
                        // 'retailer_ids'          => 'array|orbit.empty.retailer',
                        'id_language_default'   => 'required|orbit.empty.language_default',
                    ),
                    array(
                        'orbit.exists.widget_type' => Lang::get('validation.orbit.exists.widget_type'),
                    )
                );

                Event::fire('orbit.widget.postnewwidget.before.validation', array($this, $validator));

                // Run the validation
                if ($validator->fails()) {
                    $errorMessage = $validator->messages()->first();
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }
                Event::fire('orbit.widget.postnewwidget.after.validation', array($this, $validator));

                $mall = Mall::find($merchantId);

                $widget = new Widget();
                $widget->widget_type = $widgetType;
                $widget->widget_object_id = $widgetObjectId;
                $widget->widget_slogan = $slogan;
                $widget->widget_order = $widgetOrder;
                $widget->merchant_id = $mall->merchant_id;
                // $widget->animation = $animation;
                $widget->animation = 'none';
                $widget->status = 'active';
                $widget->created_by = $user->user_id;

                Event::fire('orbit.widget.postnewwidget.before.save', array($this, $widget));

                $widget->save();

                $widget->malls()->sync(array($merchantId));

                // // If widget is empty then it should be applied to all retailers
                // if (empty(OrbitInput::post('retailer_ids', NULL))) {
                //     $merchant = App::make('orbit.empty.merchant');
                //     $listOfRetailerIds = $merchant->getMyRetailerIds();
                //     $widget->retailers()->sync($listOfRetailerIds);
                // }

                Event::fire('orbit.widget.postnewwidget.after.save', array($this, $widget));

                $default_translation = [
                    $idLanguageDefault => [
                        'widget_slogan' => $widget->widget_slogan,
                    ]
                ];
                $this->validateAndSaveTranslations($widget, json_encode($default_translation), 'create');

                if (isset($widgetbatch[$widgetType]['translation']) && $widgetbatch[$widgetType]['translation'] != NULL){
                    $this->validateAndSaveTranslations($widget, $widgetbatch[$widgetType]['translation'], 'create');
                }

                $dataResponse[$widgetType] = $widget;
            }

            $this->response->data = $dataResponse;

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activityNotes = sprintf('Widget Created: %s', $widget->widget_slogan);
            $activity->setUser($user)
                    ->setActivityName('create_widget')
                    ->setActivityNameLong('Create Widget OK')
                    ->setObject($widget)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.widget.postnewwidget.after.commit', array($this, $widget));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.widget.postnewwidget.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_widget')
                    ->setActivityNameLong('Create Widget Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.widget.postnewwidget.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 400;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_widget')
                    ->setActivityNameLong('Create Widget Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.widget.postnewwidget.query.error', array($this, $e));

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
                    ->setActivityName('create_widget')
                    ->setActivityNameLong('Create Widget Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.widget.postnewwidget.general.exception', array($this, $e));

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

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_widget')
                    ->setActivityNameLong('Create Widget Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save the activity
        $activity->save();

        return $this->render($httpCode);
    }

    /**
     * POST - Update widget
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @author Firmansyah <firmansyah@dominopos.net>
     * @author Irianto Pratama <irianto@dominopos.net>
     *
     * List of API Parameters
     * ----------------------
     * @param array     `widget`                (required) - Array of parameter collection
     * @param string    `type`                  (required) - Widget type, 'catalogue', 'new_product', 'promotion', 'coupon'
     * @param integer   `object_id`             (required) - The object ID
     * @param integer   `merchant_id`           (required) - Merchant ID
     * @param integer   `retailer_ids`          (required) - Retailer IDs
     * @param string    `animation`             (required) - Animation type, 'none', 'horizontal', 'vertical'
     * @param string    `slogan`                (required) - Widget slogan
     * @param integer   `widget_order`          (required) - Order of the widget
     * @param array     `image_widget_type`     (optional) - Widget_type is 'catalogue', 'new_product', 'promotion', 'coupon' example image_promotion
     * @param integer   `id_language_default`   (required) - ID language default
     * @return Illuminate\Support\Facades\Response
     */
    public function postUpdateWidget()
    {
        $activity = Activity::portal()
                           ->setActivityType('update');

        $user = NULL;
        $widget = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.widget.postupdatewidget.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.widget.postupdatewidget.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.widget.postupdatewidget.before.authz', array($this, $user));

            // if (! ACL::create($user)->isAllowed('update_widget')) {
            //     Event::fire('orbit.widget.postupdatewidget.authz.notallowed', array($this, $user));

            //     $errorMessage = Lang::get('validation.orbit.actionlist.update_widget');
            //     $message = Lang::get('validation.orbit.access.forbidden', array('action' => $errorMessage));

            //     ACL::throwAccessForbidden($message);
            // }

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = ['super admin', 'mall admin', 'mall owner'];
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.widget.postupdatewidget.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $widgetbatch = OrbitInput::post('widget');

            $validator = Validator::make(
                array(
                    'widget' => $widgetbatch,
                ),
                array(
                    'widget' => 'required|array',
                )
            );

            Event::fire('orbit.widget.postupdatewidget.before.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // split all validation for validation image first
            foreach ($widgetbatch as $key => $value) {
                $widgetId = $value['widget_id'];
                $widgetType = $value['widget_type'];
                $widgetObjectId = $value['object_id'];
                $merchantId = $value['merchant_id'];
                $slogan = $value['slogan'];
                $animation = $value['animation'];
                $widgetOrder = $value['widget_order'];
                $images = OrbitInput::files('image_' . $widgetType);
                $idLanguageDefault = $value['id_language_default'];
                $widgetImageConfig = Config::get('orbit.upload.widget.main');
                $widget_units = static::bytesToUnits($widgetImageConfig['file_size']);

                $validator = Validator::make(
                    array(
                        'widget_id'           => $widgetId,
                        'object_id'           => $widgetObjectId,
                        'merchant_id'         => $merchantId,
                        'widget_type'         => $widgetType,
                        'animation'           => $animation,
                        'widget_order'        => $widgetOrder,
                        'widget_image_type'   => $images['type'],
                        'widget_image_size'   => $images['size'],
                        'id_language_default' => $idLanguageDefault,
                    ),
                    array(
                        'widget_id'           => 'required|orbit.empty.widget',
                        'object_id'           => '',
                        'merchant_id'         => 'orbit.empty.merchant',
                        'widget_type'         => 'required|in:tenant,lucky_draw,promotion,coupon,news,service,free_wifi|orbit.exists.widget_type_but_me:' . $merchantId . ', ' . $widgetId,
                        'animation'           => 'in:none,horizontal,vertical',
                        'widget_order'        => 'numeric',
                        'widget_image_type'   => 'in:image/jpg,image/png,image/jpeg,image/gif',
                        'widget_image_size'   => 'orbit.max.file_size:' . $widgetImageConfig['file_size'],
                        'id_language_default' => 'required|orbit.empty.language_default',
                    ),
                    array(
                        'orbit.exists.widget_type_but_me' => Lang::get('validation.orbit.exists.widget_type'),
                        'orbit.max.file_size' => 'Picture ' . $widgetOrder . ' size is too big, maximum size allowed is ' . $widget_units['newsize'] . $widget_units['unit'],
                    )
                );

                Event::fire('orbit.widget.postupdatewidget.before.validation', array($this, $validator));

                // Run the validation
                if ($validator->fails()) {
                    $errorMessage = $validator->messages()->first();
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }
                Event::fire('orbit.widget.postupdatewidget.after.validation', array($this, $validator));
            }

            foreach ($widgetbatch as $key => $value) {
                $widgetId = $value['widget_id'];
                $widgetType = $value['widget_type'];
                $widgetObjectId = $value['object_id'];
                $merchantId = $value['merchant_id'];
                // $retailerIds = $value['retailer_ids'];
                $slogan = $value['slogan'];
                $animation = $value['animation'];
                $widgetOrder = $value['widget_order'];
                $images = OrbitInput::files('image_' . $widgetType);
                $idLanguageDefault = $value['id_language_default'];
                $widgetImageConfig = Config::get('orbit.upload.widget.main');
                $widget_units = static::bytesToUnits($widgetImageConfig['file_size']);

                $updatedwidget = Widget::where('widget_id', $widgetId)->first();

                $mall = Mall::find($merchantId);

                // $widget = App::make('orbit.empty.widget');

                if ($widgetType != NULL) {
                    $updatedwidget->widget_type = $widgetType;
                }

                if ($widgetObjectId != NULL) {
                    $updatedwidget->widget_object_id = $widgetObjectId;
                }

                if ($merchantId != NULL) {
                    $updatedwidget->merchant_id = $merchantId;
                }

                if ($widgetOrder != NULL) {
                    $updatedwidget->widget_order = $widgetOrder;
                }

                if ($animation != NULL) {
                    $updatedwidget->animation = 'none';
                }

                // slogan can be null or empty string
                $updatedwidget->widget_slogan = $slogan;

                $updatedwidget->modified_by = $user->user_id;

                Event::fire('orbit.widget.postupdatewidget.before.save', array($this, $updatedwidget));

                $updatedwidget->save();

                // Insert attribute values if specified by the caller
                // if ($retailerIds != NULL) {
                    $updatedwidget->malls()->sync(array($merchantId));
                // }

                // If widget is empty then it should be applied to all retailers
                // if (empty(OrbitInput::post('retailer_ids', NULL))) {
                //     $merchant = App::make('orbit.empty.merchant');
                //     $listOfRetailerIds = $merchant->getMyRetailerIds();
                //     $updatedwidget->retailers()->sync($listOfRetailerIds);
                // }

                Event::fire('orbit.widget.postupdatewidget.after.save', array($this, $updatedwidget));

                OrbitInput::post('image_' . $updatedwidget->widget_type, function ($files_string) use ($updatedwidget) {
                    if (empty(trim($files_string))) {
                        $this->deleteWidgetImage($updatedwidget->widget_id);
                    }
                });

                // Default translation
                $default_translation = [
                    $idLanguageDefault => [
                        'widget_slogan' => $updatedwidget->widget_slogan,
                    ]
                ];
                $this->validateAndSaveTranslations($updatedwidget, json_encode($default_translation), 'update');

                // Save translations
                if (isset($widgetbatch[$widgetType]['translation']) && $widgetbatch[$widgetType]['translation'] != NULL){
                    $this->validateAndSaveTranslations($updatedwidget, $widgetbatch[$widgetType]['translation'], 'update');
                }

                $dataResponse[$widgetType] = $updatedwidget;
            }

            OrbitInput::post('widget_template', function($label) use ($mall, $user) {
                $widget_template = WidgetTemplate::active()->where('template_file_name', $label)->first();
                if(! is_object($widget_template)) {
                    $errorMessage = 'Template name cannot be found.';
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }
                $widgetTemplateSetting = NULL;
                $updatedsetting = Setting::active()
                    ->where('object_id', $mall->merchant_id)
                    ->where('object_type', 'merchant')
                    ->get();

                foreach ($updatedsetting as $currentSetting) {
                    if ($currentSetting->setting_name === 'widget_template') {
                        $widgetTemplateSetting = $currentSetting;
                    }
                }

                if (is_null($widgetTemplateSetting)) {
                    $widgetTemplateSetting = new Setting();
                    $widgetTemplateSetting->setting_name = 'widget_template';
                    $widgetTemplateSetting->object_id = $mall->merchant_id;
                    $widgetTemplateSetting->object_type = 'merchant';
                }

                $widgetTemplateSetting->setting_value = $widget_template->widget_template_id;
                $widgetTemplateSetting->modified_by = $user->user_id;
                $widgetTemplateSetting->save();
            });

            $this->response->data = $dataResponse;

            // Commit the changes
            $this->commit();

            // Successfull Update
            $activityNotes = sprintf('Widget updated: %s', $updatedwidget->widget_slogan);
            $activity->setUser($user)
                    ->setActivityName('update_widget')
                    ->setActivityNameLong('Update Widget OK')
                    ->setObject($updatedwidget)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.widget.postupdatewidget.after.commit', array($this, $updatedwidget));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.widget.postupdatewidget.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_widget')
                    ->setActivityNameLong('Update Widget Failed')
                    ->setObject($widget)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.widget.postupdatewidget.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_widget')
                    ->setActivityNameLong('Update Widget Failed')
                    ->setObject($widget)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.widget.postupdatewidget.query.error', array($this, $e));

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
                    ->setActivityName('update_widget')
                    ->setActivityNameLong('Update Widget Failed')
                    ->setObject($widget)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.widget.postupdatewidget.general.exception', array($this, $e));

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

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_widget')
                    ->setActivityNameLong('Update Widget Failed')
                    ->setObject($widget)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save activity
        $activity->save();

        return $this->render($httpCode);
    }

    /**
     * POST - Delete widget
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param integer   `wiget_id`              (required) - The Widget ID
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeleteWidget()
    {
        $activity = Activity::portal()
                          ->setActivityType('delete');

        $user = NULL;
        $widget = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.widget.postdeletewiget.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.widget.postdeletewiget.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.widget.postdeletewiget.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('delete_widget')) {
                Event::fire('orbit.widget.postdeletewiget.authz.notallowed', array($this, $user));

                $errorMessage = Lang::get('validation.orbit.actionlist.delete_widget');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $errorMessage));

                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.widget.postdeletewiget.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $widgetId = OrbitInput::post('widget_id');
            $validator = Validator::make(
                array(
                    'widget_id'             => $widgetId,
                ),
                array(
                    'widget_id'             => 'required|orbit.empty.widget',
                )
            );

            Event::fire('orbit.widget.postdeletewiget.before.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.widget.postdeletewiget.after.validation', array($this, $validator));

            $widget = App::make('orbit.empty.widget');
            $widget->status = 'deleted';
            $widget->modified_by = $user->user_id;
            $widget->save();

            Event::fire('orbit.widget.postdeletewiget.after.save', array($this, $widget));
            $this->response->data = $widget;

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activityNotes = sprintf('Widget Deleted: %s', $widget->widget_slogan);
            $activity->setUser($user)
                    ->setActivityName('delete_widget')
                    ->setActivityNameLong('Delete Widget OK')
                    ->setObject($widget)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.widget.postdeletewiget.after.commit', array($this, $widget));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.widget.postdeletewiget.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_widget')
                    ->setActivityNameLong('Delete Widget Failed')
                    ->setObject($widget)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.widget.postdeletewiget.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_widget')
                    ->setActivityNameLong('Delete Widget Failed')
                    ->setObject($widget)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.widget.postdeletewiget.query.error', array($this, $e));

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
                    ->setActivityName('delete_widget')
                    ->setActivityNameLong('Delete Widget Failed')
                    ->setObject($widget)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.widget.postdeletewiget.general.exception', array($this, $e));

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

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_widget')
                    ->setActivityNameLong('Delete Widget Failed')
                    ->setObject($widget)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save the activity
        $activity->save();

        return $this->render($httpCode);
    }

    /**
     * POST - Delete widget image
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer   `wiget_id`              (required) - The Widget ID
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeleteWidgetImage()
    {
        $activity = Activity::portal()
                          ->setActivityType('delete');

        $user = NULL;
        $widget = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.widget.postdeletewigetimage.before.auth', array($this));

            // Require authentication
           if (! $this->calledFrom('widget.new, widget.update'))
            {
                $this->checkAuth();
                Event::fire('orbit.widget.postdeletewigetimage.after.auth', array($this));

                // Try to check access control list, does this user allowed to
                // perform this action
                $user = $this->api->user;
                Event::fire('orbit.widget.postdeletewigetimage.before.authz', array($this, $user));

                if (! ACL::create($user)->isAllowed('delete_widget')) {
                    Event::fire('orbit.widget.postdeletewigetimage.authz.notallowed', array($this, $user));

                    $errorMessage = Lang::get('validation.orbit.actionlist.delete_widget');
                    $message = Lang::get('validation.orbit.access.forbidden', array('action' => $errorMessage));

                    ACL::throwAccessForbidden($message);
                }
                Event::fire('orbit.widget.postdeletewigetimage.after.authz', array($this, $user));
            } else {
                $user = App::make('orbit.upload.user');
            }

            $this->registerCustomValidation();

            $widgetId = OrbitInput::post('widget_id');
            $validator = Validator::make(
                array(
                    'widget_id'             => $widgetId,
                ),
                array(
                    'widget_id'             => 'required|orbit.empty.widget',
                )
            );

            Event::fire('orbit.widget.postdeletewigetimage.before.validation', array($this, $validator));

            // Begin database transaction
           if (! $this->calledFrom('widget.new, widget.update'))
            {
                $this->beginTransaction();
            }

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.widget.postdeletewigetimage.after.validation', array($this, $validator));

            $imgs = Media::where('object_name', 'widget')->where('object_id', $widgetId)->get();
            // dd($img);
            foreach ($imgs as $img) {
                $img->delete(TRUE);
            }

            Event::fire('orbit.widget.postdeletewigetimage.after.save', array($this, $imgs));
            $this->response->data = NULL;

            // Commit the changes
           if (! $this->calledFrom('widget.new, widget.update'))
            {
                $this->commit();
            }

            // Successfull Creation
            $activityNotes = sprintf('Widget Image Deleted');
            $activity->setUser($user)
                    ->setActivityName('delete_widget_image')
                    ->setActivityNameLong('Delete Widget Image OK')
                    ->setObject($widget)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.widget.postdeletewigetimage.after.commit', array($this, $imgs));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.widget.postdeletewigetimage.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
           if (! $this->calledFrom('widget.new, widget.update'))
            {
                $this->rollBack();
            }

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_widget')
                    ->setActivityNameLong('Delete Widget Failed')
                    ->setObject($widget)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.widget.postdeletewigetimage.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
           if (! $this->calledFrom('widget.new, widget.update'))
            {
                $this->rollBack();
            }

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_widget')
                    ->setActivityNameLong('Delete Widget Failed')
                    ->setObject($widget)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.widget.postdeletewigetimage.query.error', array($this, $e));

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
           if (! $this->calledFrom('widget.new, widget.update'))
            {
                $this->rollBack();
            }

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_widget')
                    ->setActivityNameLong('Delete Widget Failed')
                    ->setObject($widget)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.widget.postdeletewigetimage.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();

            if (Config::get('app.debug')) {
                $this->response->data = $e->__toString();
            } else {
                $this->response->data = null;
            }

            // Rollback the changes\
           if (! $this->calledFrom('widget.new, widget.update'))
            {
                $this->rollBack();
            }

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_widget')
                    ->setActivityNameLong('Delete Widget Failed')
                    ->setObject($widget)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save the activity
        $activity->save();

        return $this->render($httpCode);
    }

    public function deleteWidgetImage($widgetId)
    {
        $activity = Activity::portal()
                          ->setActivityType('delete');

        $user = NULL;
        $widget = NULL;
        try {
            $httpCode = 200;

            $this->registerCustomValidation();

            $widgetId = $widgetId;
            $validator = Validator::make(
                array(
                    'widget_id'             => $widgetId,
                ),
                array(
                    'widget_id'             => 'required|orbit.empty.widget',
                )
            );

            Event::fire('orbit.widget.postdeletewigetimage.before.validation', array($this, $validator));

            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.widget.postdeletewigetimage.after.validation', array($this, $validator));

            $imgs = Media::where('object_name', 'widget')->where('object_id', $widgetId)->get();
            // dd($img);
            foreach ($imgs as $img) {
                $img->delete(TRUE);
            }

            Event::fire('orbit.widget.postdeletewigetimage.after.save', array($this, $imgs));
            $this->response->data = NULL;

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activityNotes = sprintf('Widget Image Deleted');
            $activity->setUser($user)
                    ->setActivityName('delete_widget_image')
                    ->setActivityNameLong('Delete Widget Image OK')
                    ->setObject($widget)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.widget.postdeletewigetimage.after.commit', array($this, $imgs));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.widget.postdeletewigetimage.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_widget')
                    ->setActivityNameLong('Delete Widget Failed')
                    ->setObject($widget)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.widget.postdeletewigetimage.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_widget')
                    ->setActivityNameLong('Delete Widget Failed')
                    ->setObject($widget)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.widget.postdeletewigetimage.query.error', array($this, $e));

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
                    ->setActivityName('delete_widget')
                    ->setActivityNameLong('Delete Widget Failed')
                    ->setObject($widget)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.widget.postdeletewigetimage.general.exception', array($this, $e));

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

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_widget')
                    ->setActivityNameLong('Delete Widget Failed')
                    ->setObject($widget)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save the activity
        $activity->save();

        return $this->render($httpCode);
    }

    /**
     * GET - List of Widgets.
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param array         `widget_ids`            (optional) - List of widget IDs
     * @param array         `widget_type`           (optional) - Type of the widget, e.g: 'catalogue', 'new_product', 'promotion', 'coupon'
     * @param array         `merchant_ids`          (optional) - List of Merchant IDs
     * @param array         `merchant_id`           (optional) - Merchant ID
     * @param array         `retailer_ids`          (optional) - List of Retailer IDs
     * @param array         `animations`            (optional) - Filter by animation
     * @param array         `types`                 (optional) - Filter by widget types
     * @param array         `with`                  (optional) - relationship included
     * @param integer       `take`                  (optional) - limit
     * @param integer       `skip`                  (optional) - limit offset
     * @param string        `sort_by`               (optional) - column order by
     * @param string        `sort_mode`             (optional) - asc or desc
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchWidget()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.widget.getwidget.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.widget.getwidget.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.widget.getwidget.before.authz', array($this, $user));

            // if (! ACL::create($user)->isAllowed('view_widget')) {
            //     Event::fire('orbit.widget.getwidget.authz.notallowed', array($this, $user));

            //     $errorMessage = Lang::get('validation.orbit.actionlist.view_widget');
            //     $message = Lang::get('validation.orbit.access.forbidden', array('action' => $errorMessage));

            //     ACL::throwAccessForbidden($message);
            // }

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = ['super admin', 'mall admin', 'mall owner'];
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.widget.getwidget.after.authz', array($this, $user));

            $validator = Validator::make(
                array(
                    'widget_ids'    => OrbitInput::get('widget_ids'),
                    'merchant_ids'  => OrbitInput::get('merchant_ids'),
                    'retailer_ids'  => OrbitInput::get('retailer_ids'),
                    'animations'    => OrbitInput::get('animations'),
                    'types'         => OrbitInput::get('types')
                ),
                array(
                    'widget_ids'    => 'array|min:1',
                    'merchant_ids'  => 'array|min:1',
                    'retailer_ids'  => 'array|min:1',
                    'animations'    => 'array|min:1',
                    'types'         => 'array|min:1'
                )
            );

            Event::fire('orbit.widget.postdeletewiget.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.widget.postdeletewiget.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.widget.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.widget.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            // Available merchant to query
            $listOfMerchantIds = [];

            // Available retailer to query
            $listOfRetailerIds = [];

            $merchantId = implode("', '", OrbitInput::get('merchant_id'));
            // Builder object
            $tablePrefix = DB::getTablePrefix();
            $widgets = Widget::select('widgets.*')
                            ->leftJoin(DB::raw("(SELECT setting_id, setting_name, setting_value, object_id
                                        FROM {$tablePrefix}settings
                                        WHERE setting_name like '%widget%'
                                            AND object_id IN ('{$merchantId}')) AS os"),
                                // On
                                DB::raw('os.setting_name'), '=', DB::raw("CONCAT('enable_', {$tablePrefix}widgets.widget_type, '_widget')"))
                            ->join('widget_retailer', 'widget_retailer.widget_id', '=', 'widgets.widget_id')
                            ->where('widgets.status', '=', 'active')
                            ->whereRaw("(CASE WHEN os.setting_id IS NULL THEN 'true' ELSE os.setting_value END) = 'true'");

            // Include other relationship
            OrbitInput::get('with', function($with) use ($widgets) {
                $widgets->with($with);
            });

            // Filter by ids
            OrbitInput::get('widget_ids', function($widgetIds) use ($widgets) {
                $widgets->whereIn('widgets.widget_id', $widgetIds);
            });

            // Filter by merchant ids
            OrbitInput::get('merchant_ids', function($merchantIds) use ($widgets) {
                $listOfMerchantIds = (array)$merchantIds;
            });

            // Filter by retailer ids
            OrbitInput::get('retailer_ids', function($retailerIds) use ($widgets) {
                $listOfRetailerIds = (array)$retailerIds;
            });

            // Filter by animation
            OrbitInput::get('animations', function($animation) use ($widgets) {
                $widgets->whereIn('widgets.animation', $animation);
            });

            OrbitInput::get('merchant_id', function($merchant_id) use ($widgets) {
                $widgets->whereIn('widgets.merchant_id', $merchant_id);
            });

            // Filter by widget type
            OrbitInput::get('types', function($types) use ($widgets) {
                $widgets->whereIn('widgets.widget_type', $types);
            });

            // @To do: Replace this hacks
            // if (! $user->isSuperAdmin()) {
            //     $listOfMerchantIds = $user->getMyMerchantIds();

            //     if (empty($listOfMerchantIds)) {
            //         $listOfMerchantIds = [-1];
            //     }
            //     $widgets->whereIn('widgets.merchant_id', $listOfMerchantIds);
            // } else {
            //     if (! empty($listOfMerchantIds)) {
            //         $widgets->whereIn('widgets.merchant_id', $listOfMerchantIds);
            //     }
            // }

            // @To do: Replace this hacks
            // if (! $user->isSuperAdmin()) {
            //     $listOfRetailerIds = $user->getMyRetailerIds();

            //     if (empty($listOfRetailerIds)) {
            //         $listOfRetailerIds = [-1];
            //     }
            //     $widgets->whereIn('widget_retailer.retailer_id', $listOfRetailerIds);
            // } else {
            //     if (! empty($listOfRetailerIds)) {
            //         $widgets->whereIn('widget_retailer.retailer_id', $listOfRetailerIds);
            //     }
            // }

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_widgets = clone $widgets;

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
            $widgets->take($take);

            $skip = 0;
            OrbitInput::get('skip', function ($_skip) use (&$skip, $widgets) {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $widgets->skip($skip);

            // Default sort by
            $sortBy = 'widgets.widget_order';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function ($_sortBy) use (&$sortBy) {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'widget_order'  => 'widgets.widget_order',
                    'id'            => 'widgets.widget_id',
                    'created'       => 'widgets.created_at',
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function ($_sortMode) use (&$sortMode) {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });
            $widgets->orderBy($sortBy, $sortMode);

            $totalWidgets = RecordCounter::create($_widgets)->count();
            $listOfWidgets = $widgets->get();

            $counter = 1;
            foreach ($listOfWidgets as $widget) {
                $widget->widget_order = $counter;
                $counter += 1;

                if ($widget->widget_type == 'tenant') {
                    $widget->image = 'mobile-ci/images/default_tenants_directory.png';
                    $widget->default_image = 'mobile-ci/images/default_tenants_directory.png';

                    foreach ($widget->media as $media) {
                        if ($media->media_name_long === 'home_widget_orig') {
                            if (empty($media->path)) {
                                $widget->image = 'mobile-ci/images/default_tenants_directory.png';
                            } else {
                                $widget->image = $media->path;
                            }
                        }
                    }

                    $widget->display_title = Lang::get('mobileci.widgets.tenant');
                    if ($widget->item_count > 1) {
                        $widget->display_sub_title = Lang::get('mobileci.widgets.tenants');
                    } else {
                        $widget->display_sub_title = Lang::get('mobileci.widgets.tenants_single');
                    }
                }
                if ($widget->widget_type == 'service') {
                    $widget->image = 'mobile-ci/images/default_services_directory.png';
                    $widget->default_image = 'mobile-ci/images/default_services_directory.png';

                    foreach ($widget->media as $media) {
                        if ($media->media_name_long === 'home_widget_orig') {
                            if (empty($media->path)) {
                                $widget->image = 'mobile-ci/images/default_services_directory.png';
                            } else {
                                $widget->image = $media->path;
                            }
                        }
                    }

                    $widget->display_title = Lang::get('mobileci.widgets.service');
                    if ($widget->item_count > 1) {
                        $widget->display_sub_title = Lang::get('mobileci.widgets.services');
                    } else {
                        $widget->display_sub_title = Lang::get('mobileci.widgets.services_single');
                    }
                }
                if ($widget->widget_type == 'promotion') {
                    $widget->image = 'mobile-ci/images/default_promotion.png';
                    $widget->default_image = 'mobile-ci/images/default_promotion.png';

                    foreach ($widget->media as $media) {
                        if ($media->media_name_long === 'home_widget_orig') {
                            if (empty($media->path)) {
                                $widget->image = 'mobile-ci/images/default_promotion.png';
                            } else {
                                $widget->image = $media->path;
                            }
                        }
                    }

                    $widget->display_title = Lang::get('mobileci.widgets.promotion');
                    if ($widget->item_count > 1) {
                        $widget->display_sub_title = Lang::get('mobileci.widgets.promotions');
                    } else {
                        $widget->display_sub_title = Lang::get('mobileci.widgets.promotions_single');
                    }
                }
                if ($widget->widget_type == 'news') {
                    $widget->image = 'mobile-ci/images/default_news.png';
                    $widget->default_image = 'mobile-ci/images/default_news.png';

                    foreach ($widget->media as $media) {
                        if ($media->media_name_long === 'home_widget_orig') {
                            if (empty($media->path)) {
                                $widget->image = 'mobile-ci/images/default_news.png';
                            } else {
                                $widget->image = $media->path;
                            }
                        }
                    }

                    $widget->display_title = Lang::get('mobileci.widgets.news');
                    if ($widget->item_count > 1) {
                        $widget->display_sub_title = Lang::get('mobileci.widgets.newss');
                    } else {
                        $widget->display_sub_title = Lang::get('mobileci.widgets.newss_single');
                    }
                }
                if ($widget->widget_type == 'coupon') {
                    $widget->image = 'mobile-ci/images/default_coupon.png';
                    $widget->default_image = 'mobile-ci/images/default_coupon.png';

                    foreach ($widget->media as $media) {
                        if ($media->media_name_long === 'home_widget_orig') {
                            if (empty($media->path)) {
                                $widget->image = 'mobile-ci/images/default_coupon.png';
                            } else {
                                $widget->image = $media->path;
                            }
                        }
                    }

                    $widget->display_title = Lang::get('mobileci.widgets.coupon');
                    if ($widget->item_count > 1) {
                        $widget->display_sub_title = Lang::get('mobileci.widgets.coupons');
                    } else {
                        $widget->display_sub_title = Lang::get('mobileci.widgets.coupons_single');
                    }
                }
                if ($widget->widget_type == 'lucky_draw') {
                    $widget->image = 'mobile-ci/images/default_lucky_number.png';
                    $widget->default_image = 'mobile-ci/images/default_lucky_number.png';

                    foreach ($widget->media as $media) {
                        if ($media->media_name_long === 'home_widget_orig') {
                            if (empty($media->path)) {
                                $widget->image = 'mobile-ci/images/default_lucky_number.png';
                            } else {
                                $widget->image = $media->path;
                            }
                        }
                    }

                    $widget->display_title = Lang::get('mobileci.widgets.lucky_draw');
                    if ($widget->item_count > 1) {
                        $widget->display_sub_title = Lang::get('mobileci.widgets.lucky_draws');
                    } else {
                        $widget->display_sub_title = Lang::get('mobileci.widgets.lucky_draws_single');
                    }
                }
                if ($widget->widget_type == 'free_wifi') {
                    $widget->image = 'mobile-ci/images/default_free_wifi_directory.png';
                    $widget->default_image = 'mobile-ci/images/default_free_wifi_directory.png';

                    foreach ($widget->media as $media) {
                        if ($media->media_name_long === 'home_widget_orig') {
                            if (empty($media->path)) {
                                $widget->image = 'mobile-ci/images/default_free_wifi_directory.png';
                            } else {
                                $widget->image = $media->path;
                            }
                        }
                    }

                    $widget->display_title = Lang::get('mobileci.widgets.free_wifi');
                    $widget->display_sub_title = Lang::get('mobileci.widgets.free_wifi');
                }
            }

            $data = new stdclass();
            $data->total_records = $totalWidgets;
            $data->returned_records = count($listOfWidgets);
            $data->records = $listOfWidgets;

            if ($totalWidgets === 0) {
                $data->records = null;
                $this->response->message = Lang::get('statuses.orbit.nodata.widget');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.widget.getwidget.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.widget.getwidget.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.widget.getwidget.query.error', array($this, $e));

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
            Event::fire('orbit.widget.getwidget.general.exception', array($this, $e));

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
        Event::fire('orbit.widget.getwidget.before.render', array($this, &$output));

        return $output;
    }

    protected function registerCustomValidation()
    {
        // Check the existance of id_language_default
        Validator::extend('orbit.empty.language_default', function ($attribute, $value, $parameters) {
            $news = MerchantLanguage::excludeDeleted()
                        ->where('language_id', $value)
                        ->first();

            if (empty($news)) {
                return FALSE;
            }

            App::instance('orbit.empty.language_default', $news);

            return TRUE;
        });

        // Check the existance of widget id
        $user = $this->api->user;
        Validator::extend('orbit.empty.widget', function ($attribute, $value, $parameters) use ($user) {
            $widget = Widget::excludeDeleted()
                        ->where('widget_id', $value)
                        ->first();

            if (empty($widget)) {
                return FALSE;
            }

            App::instance('orbit.empty.widget', $widget);

            return TRUE;
        });

        // Check the existance of merchant id
        $user = $this->api->user;
        Validator::extend('orbit.empty.merchant', function ($attribute, $value, $parameters) use ($user) {
            $merchant = Mall::excludeDeleted()
                        // ->allowedForUser($user)
                        // ->isMall()
                        ->where('merchant_id', $value)
                        ->first();

            if (empty($merchant)) {
                return FALSE;
            }

            App::instance('orbit.empty.merchant', $merchant);

            return TRUE;
        });

        // Check the existstance of each retailer ids
        Validator::extend('orbit.empty.retailer', function ($attribute, $value, $parameters) use ($user) {
            $expectedNumber = count($value);
            $merchant = App::make('orbit.empty.merchant');
            $retailerNumber = Mall::excludeDeleted()
                        // ->allowedForUser($user)
                        ->whereIn('merchant_id', $value)
                        ->where('parent_id', $merchant->merchant_id)
                        ->count();

            if ($expectedNumber !== $retailerNumber) {
                return FALSE;
            }

            return TRUE;
        });

        // Check the existstance of each widget type
        Validator::extend('orbit.exists.widget_type', function ($attribute, $value, $parameters) use ($user) {
            // Available retailer to query
            $listOfRetailerIds = [];
            $user = $this->api->user;

            $widget = Widget::joinRetailer()
                        ->excludeDeleted()
                        ->where('widgets.widget_type', $value)
                        ->where('widgets.merchant_id', $parameters[0]);

            // @To do: Replace this hacks
            if (! $user->isSuperAdmin()) {
                $listOfRetailerIds = $user->getMyRetailerIds();

                if (empty($listOfRetailerIds)) {
                    $listOfRetailerIds = [-1];

                }
                $widget->whereIn('widget_retailer.retailer_id', $listOfRetailerIds);
            } else {
                if (! empty($listOfRetailerIds)) {
                    $widget->whereIn('widget_retailer.retailer_id', $listOfRetailerIds);
                }
            }

            $widget = $widget->first();

            if (!empty($widget)) {
                return FALSE;
            }

            return TRUE;
        });

        // Check the existstance of each widget type on update
        Validator::extend('orbit.exists.widget_type_but_me', function ($attribute, $value, $parameters) use ($user) {
            // Available retailer to query
            $listOfRetailerIds = [];
            $user = $this->api->user;

            $widget = Widget::joinRetailer()
                        ->excludeDeleted()
                        ->where('widgets.widget_type', $value)
                        ->where('widgets.merchant_id', $parameters[0])
                        ->where('widgets.widget_id', '!=', $parameters[1]);

            // @To do: Replace this hacks
            if (! $user->isSuperAdmin()) {
                $listOfRetailerIds = $user->getMyRetailerIds();

                if (empty($listOfRetailerIds)) {
                    $listOfRetailerIds = [-1];
                }
                $widget->whereIn('widget_retailer.retailer_id', $listOfRetailerIds);
            } else {
                if (! empty($listOfRetailerIds)) {
                    $widget->whereIn('widget_retailer.retailer_id', $listOfRetailerIds);
                }
            }

            $widget = $widget->first();

            if (!empty($widget)) {
                return FALSE;
            }

            return TRUE;
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
     * @param Widget $widget
     * @param string $translations_json_string
     * @param string $scenario 'create' / 'update'
     * @throws InvalidArgsException
     */
    private function validateAndSaveTranslations($widget, $translations_json_string, $scenario = 'create')
    {
        /*
         * JSON structure: object with keys = merchant_language_id and values = ProductTranslation object or null
         *
         * Having a value of null means deleting the translation
         *
         * where WidgetTranslation object is object with keys:
         *   widget_slogan
         *
         * No requirement for including fields. If field not included it means not updated. If field included with
         * value null it means set to null (use main language content instead).
         */

        $valid_fields = ['widget_slogan'];
        $user = $this->api->user;
        $operations = [];

        $data = @json_decode($translations_json_string);
        if (json_last_error() != JSON_ERROR_NONE) {
            OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.jsonerror.field.format', ['field' => 'translations']));
        }
        foreach ($data as $merchant_language_id => $translations) {
            $language = MerchantLanguage::excludeDeleted()
                ->where('language_id', '=', $merchant_language_id)
                ->first();

            if (empty($language)) {
                OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.empty.merchant_language'));
            }
            $existing_translation = WidgetTranslation::excludeDeleted()
                ->where('widget_id', '=', $widget->widget_id)
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
                $new_translation = new WidgetTranslation();
                $new_translation->widget_id = $widget->widget_id;
                $new_translation->merchant_language_id = $operation[1];
                $data = $operation[2];
                foreach ($data as $field => $value) {
                    $new_translation->{$field} = $value;
                }
                $new_translation->created_by = $this->api->user->user_id;
                $new_translation->modified_by = $this->api->user->user_id;
                $new_translation->save();

                $widget->setRelation('translation_'. $new_translation->merchant_language_id, $new_translation);
            }
            elseif ($op === 'update') {

                /** @var WidgetTranslation $existing_translation */
                $existing_translation = $operation[1];
                $data = $operation[2];
                foreach ($data as $field => $value) {
                    $existing_translation->{$field} = $value;
                }
                $existing_translation->modified_by = $this->api->user->user_id;
                $existing_translation->save();

                $widget->setRelation('translation_'. $existing_translation->merchant_language_id, $existing_translation);
            }
            elseif ($op === 'delete') {
                /** @var WidgetTranslation $existing_translation */
                $existing_translation = $operation[1];
                $existing_translation->modified_by = $this->api->user->user_id;
                $existing_translation->delete();
            }
        }
    }

    /**
     * Set the called from value.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @author Irianto <irianto@dominopos.com>
     * @param string $from The source of the caller
     * @return UploadAPIController
     */
    public function setCalledFrom($from)
    {
        $this->calledFrom = $from;

        return $this;
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
