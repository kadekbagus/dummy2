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
use Carbon\Carbon as Carbon;

class LuckyDrawAPIController extends ControllerAPI
{
    /**
     * Maximum number of the lucky draw
     */
    const MAX_NUMBER = 99999999;

    /**
     * Maximum number of the lucky draw
     */
    const MIN_NUMBER = 1;

    /**
     * Default language name used if none are sent
     */
    const DEFAULT_LANG = 'en';

    private function getCampaignStatusTable()
    {
        $campaignStatus = new CampaignStatus;
        return $campaignStatus->getTable();
    }

    /**
     * New & Update handler for Status related items
     *
     * @author Qosdil A. <qosdil@dominopos.com>
     * @return array
     */
    private function handleStatus() {
        // The campaign status: not started, ongoing, paused, stopped, expired
        $campaignStatusName = OrbitInput::post('campaign_status');
        $campaignStatusId = CampaignStatus::whereCampaignStatusName($campaignStatusName)->pluck('campaign_status_id');

        // The Active / Inactive status of the campaigns
        $status = ($campaignStatusName == 'ongoing') ? 'active' : 'inactive';

        return [$campaignStatusId, $status];
    }

    /**
     * Flag to return the query builder.
     *
     * @var Builder
     */
    protected $returnBuilder = FALSE;

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
            $mall_id = OrbitInput::post('mall_id', OrbitInput::post('merchant_id'));
            $listOfMallIds = $user->getUserMallIds($mall_id);
            if (empty($listOfMallIds)) { // invalid mall id
                $errorMessage = 'Invalid mall id.';
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            } else {
                $mall_id = $listOfMallIds[0];
            }

            $lucky_draw_name = OrbitInput::post('lucky_draw_name');
            $description = OrbitInput::post('description');
            $start_date = OrbitInput::post('start_date');
            $end_date = OrbitInput::post('end_date');

            $draw_date = OrbitInput::post('draw_date');
            // set default value for draw date. if draw_date is empty, then set its value with end_date plus one second
            if ((trim($draw_date) === '') && (trim($end_date) !== '')) {
                $draw_date = Carbon::createFromFormat('Y-m-d H:i:s', $end_date);
                $draw_date = $draw_date->addSeconds(1)->__toString();
            }

            $minimum_amount = OrbitInput::post('minimum_amount');
            $min_number = OrbitInput::post('min_number');
            $max_number = OrbitInput::post('max_number');
            $external_lucky_draw_id = OrbitInput::post('external_lucky_draw_id');
            $grace_period_date = OrbitInput::post('grace_period_date');
            $grace_period_in_days = OrbitInput::post('grace_period_in_days');

            $default_merchant_language_id = MerchantLanguage::getLanguageIdByMerchant($mall_id, static::DEFAULT_LANG);
            $id_language_default = OrbitInput::post('id_language_default', $default_merchant_language_id);

            // Begin database transaction
            $this->beginTransaction();

            $validator = Validator::make(
                array(
                    'mall_id'                  => $mall_id,
                    'lucky_draw_name'          => $lucky_draw_name,
                    'description'              => $description,
                    'start_date'               => $start_date,
                    'end_date'                 => $end_date,
                    'draw_date'                => $draw_date,
                    'minimum_amount'           => $minimum_amount,
                    'min_number'               => $min_number,
                    'max_number'               => $max_number,
                    'external_lucky_draw_id'   => $external_lucky_draw_id,
                    'grace_period_date'        => $grace_period_date,
                    'grace_period_in_days'     => $grace_period_in_days,
                    'campaign_status'          => OrbitInput::post('campaign_status'),
                    'id_language_default'      => $id_language_default,
                ),
                array(
                    'mall_id'                  => 'orbit.empty.mall',
                    'lucky_draw_name'          => 'required|max:255|orbit.exists.lucky_draw_name:' . $mall_id,
                    'description'              => 'required',
                    'start_date'               => 'required|date_format:Y-m-d H:i:s',
                    'end_date'                 => 'required|date_format:Y-m-d H:i:s|after:' . $start_date,
                    'draw_date'                => 'required|date_format:Y-m-d H:i:s|after:' . $end_date,
                    'minimum_amount'           => 'required|numeric',
                    'min_number'               => 'required|numeric|min:' . static::MIN_NUMBER,
                    'max_number'               => 'required|numeric|max:' . static::MAX_NUMBER,
                    'external_lucky_draw_id'   => 'required',
                    'grace_period_date'        => 'date_format:Y-m-d H:i:s|after:' . $end_date,
                    'grace_period_in_days'     => 'numeric',
                    'campaign_status'          => 'required|exists:'.$this->getCampaignStatusTable().',campaign_status_name',
                    'id_language_default'      => 'required|orbit.empty.language_default',
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
            $newluckydraw->draw_date = $draw_date;
            $newluckydraw->minimum_amount = $minimum_amount;
            $newluckydraw->min_number = $min_number;
            $newluckydraw->max_number = $max_number;
            $newluckydraw->external_lucky_draw_id = $external_lucky_draw_id;
            $newluckydraw->grace_period_date = $grace_period_date;
            $newluckydraw->grace_period_in_days = $grace_period_in_days;
            $newluckydraw->created_by = $this->api->user->user_id;
            $newluckydraw->modified_by = $this->api->user->user_id;

            list($newluckydraw->campaign_status_id, $newluckydraw->status) = $this->handleStatus();

            Event::fire('orbit.luckydraw.postnewluckydraw.before.save', array($this, $newluckydraw));

            $newluckydraw->save();

            // save default language translation
            $lucky_draw_translation_default = new LuckyDrawTranslation();
            $lucky_draw_translation_default->lucky_draw_id = $newluckydraw->lucky_draw_id;
            $lucky_draw_translation_default->merchant_language_id = $id_language_default;
            $lucky_draw_translation_default->lucky_draw_name = $newluckydraw->lucky_draw_name;
            $lucky_draw_translation_default->description = $newluckydraw->description;
            $lucky_draw_translation_default->status = 'active';
            $lucky_draw_translation_default->created_by = $this->api->user->user_id;
            $lucky_draw_translation_default->modified_by = $this->api->user->user_id;
            $lucky_draw_translation_default->save();

            Event::fire('orbit.luckydraw.after.translation.save', array($this, $lucky_draw_translation_default));

            Event::fire('orbit.luckydraw.postnewluckydraw.after.save', array($this, $newluckydraw));

            OrbitInput::post('translations', function($translation_json_string) use ($newluckydraw) {
                $this->validateAndSaveTranslations($newluckydraw, $translation_json_string, 'create');
            });

            // get default mall language id
            $default = Mall::select('mobile_default_language', 'name')
                            ->where('merchant_id', '=', $mall_id)
                            ->first();

            $idLanguage = Language::select('language_id', 'name_long')
                                ->where('name', '=', $default->mobile_default_language)
                                ->first();

            $isAvailable = LuckyDrawTranslation::where('merchant_language_id', '=', $idLanguage->language_id)
                                            ->where('lucky_draw_id', '=', $newluckydraw->lucky_draw_id)
                                            ->where('lucky_draw_name', '!=', '')
                                            ->where('description', '!=', '')
                                            ->count();

            if ($isAvailable == 0) {
                $errorMessage = Lang::get('validation.orbit.empty.default_language');
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $this->response->data = $newluckydraw;
            $this->response->data->translation_default = $lucky_draw_translation_default;

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
     * @author Irianto Pratama<irianto@dominopos.com>
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
            $mall_id = OrbitInput::post('mall_id', OrbitInput::post('merchant_id'));
            $listOfMallIds = $user->getUserMallIds($mall_id);
            if (empty($listOfMallIds)) { // invalid mall id
                $errorMessage = 'Invalid mall id.';
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            } else {
                $mall_id = $listOfMallIds[0];
            }

            $lucky_draw_id = OrbitInput::post('lucky_draw_id');
            $status = OrbitInput::post('status');
            $start_date = OrbitInput::post('start_date');
            $end_date = OrbitInput::post('end_date');
            $draw_date = OrbitInput::post('draw_date');
            $grace_period_date = OrbitInput::post('grace_period_date');
            $luckydraw_image = OrbitInput::files('images');
            $luckydraw_image_config = Config::get('orbit.upload.lucky_draw.main');
            $luckydraw_image_units = static::bytesToUnits($luckydraw_image_config['file_size']);

            $default_merchant_language_id = MerchantLanguage::getLanguageIdByMerchant($mall_id, static::DEFAULT_LANG);
            $id_language_default = OrbitInput::post('id_language_default', $default_merchant_language_id);

            $now = date('Y-m-d H:i:s');

            $data = array(
                'campaign_status'     => OrbitInput::post('campaign_status'),
                'lucky_draw_id'       => $lucky_draw_id,
                'mall_id'             => $mall_id,
                'start_date'          => $start_date,
                'end_date'            => $end_date,
                'grace_period_date'   => $grace_period_date,
                'id_language_default' => $id_language_default,
                'images_type'         => $luckydraw_image['type'],
                'images_size'         => $luckydraw_image['size'],
            );

            // Validate lucky_draw_name only if exists in POST.
            OrbitInput::post('lucky_draw_name', function($lucky_draw_name) use (&$data) {
                $data['lucky_draw_name'] = $lucky_draw_name;
            });

            // Begin database transaction
            $this->beginTransaction();

            $validator = Validator::make(
                $data,
                array(
                    'campaign_status'     => 'required|exists:'.$this->getCampaignStatusTable().',campaign_status_name',
                    'lucky_draw_id'       => 'required|orbit.empty.lucky_draw:' . $mall_id,
                    'mall_id'             => 'orbit.empty.mall',
                    'lucky_draw_name'     => 'sometimes|required|min:3|max:255|lucky_draw_name_exists_but_me:' . $lucky_draw_id . ',' . $mall_id,
                    'status'              => 'sometimes|required|orbit.empty.lucky_draw_status',
                    'start_date'          => 'date_format:Y-m-d H:i:s',
                    'end_date'            => 'date_format:Y-m-d H:i:s',
                    'draw_date'           => 'date_format:Y-m-d H:i:s',
                    'grace_period_date'   => 'date_format:Y-m-d H:i:s',
                    'id_language_default' => 'required|orbit.empty.language_default',
                    'images_type'         => 'in:image/jpg,image/png,image/jpeg,image/gif',
                    'images_size'         => 'orbit.max.file_size:' . $luckydraw_image_config['file_size'],
                ),
                array(
                    'lucky_draw_name_exists_but_me' => Lang::get('validation.orbit.exists.lucky_draw_name'),
                    'end_date_greater_than_start_date_and_current_date' => 'The end datetime should be greater than the start datetime or current datetime.',
                    'draw_date_greater_than_end_date_and_current_date' => 'The draw datetime should be greater than the end datetime or current datetime.',
                    'orbit.max.file_size' => 'Lucky Draw Image size is too big, maximum size allowed is '. $luckydraw_image_units['newsize'] . $luckydraw_image_units['unit']
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

            $updatedluckydraw_default_language = LuckyDrawTranslation::excludeDeleted()->where('lucky_draw_id', $lucky_draw_id)->where('merchant_language_id', $id_language_default)->first();

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

            OrbitInput::post('draw_date', function($draw_date) use ($updatedluckydraw) {
                $updatedluckydraw->draw_date = $draw_date;
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
                if ((int)$max_number < (int)$updatedluckydraw->max_number) {
                    $errorMessage = 'You can not decrease the maximum number of lucky draw.';
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }
                $updatedluckydraw->max_number = $max_number;
            });

            OrbitInput::post('external_lucky_draw_id', function($data) use ($updatedluckydraw) {
                $updatedluckydraw->external_lucky_draw_id = $data;
            });

            list($updatedluckydraw->campaign_status_id, $updatedluckydraw->status) = $this->handleStatus();

            $updatedluckydraw->modified_by = $this->api->user->user_id;

            Event::fire('orbit.luckydraw.postupdateluckydraw.before.save', array($this, $updatedluckydraw));

            //  save lucky draw default language
            OrbitInput::post('lucky_draw_name', function($lucky_draw_name) use ($updatedluckydraw_default_language) {
                $updatedluckydraw_default_language->lucky_draw_name = $lucky_draw_name;
            });

            OrbitInput::post('description', function($description) use ($updatedluckydraw_default_language) {
                $updatedluckydraw_default_language->description = $description;
            });

            OrbitInput::post('status', function($status) use ($updatedluckydraw_default_language) {
                $updatedluckydraw_default_language->status = $status;
            });

            $updatedluckydraw_default_language->modified_by = $this->api->user->user_id;

            Event::fire('orbit.luckydraw.postupdateluckydraw.before.save', array($this, $updatedluckydraw));

            $updatedluckydraw->save();
            $updatedluckydraw_default_language->save();

            Event::fire('orbit.luckydraw.after.translation.save', array($this, $updatedluckydraw_default_language));

            // return respones if any upload image or no
            $updatedluckydraw_default_language->load('media');

            Event::fire('orbit.luckydraw.postupdateluckydraw.after.save', array($this, $updatedluckydraw));

            OrbitInput::post('translations', function($translation_json_string) use ($updatedluckydraw) {
                $this->validateAndSaveTranslations($updatedluckydraw, $translation_json_string, 'update');
            });

            // get default mall language id
            $default = Mall::select('mobile_default_language', 'name')
                            ->where('merchant_id', '=', $mall_id)
                            ->first();

            $idLanguage = Language::select('language_id', 'name_long')
                                ->where('name', '=', $default->mobile_default_language)
                                ->first();

            $isAvailable = LuckyDrawTranslation::where('merchant_language_id', '=', $idLanguage->language_id)
                                            ->where('lucky_draw_id', '=', $lucky_draw_id)
                                            ->where('lucky_draw_name', '!=', '')
                                            ->where('description', '!=', '')
                                            ->count();

            if ($isAvailable == 0) {
                $errorMessage = Lang::get('validation.orbit.empty.default_language');
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $this->response->data = $updatedluckydraw;
            $this->response->data->translation_default = $updatedluckydraw_default_language;

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

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.luckydraw.postdeleteluckydraw.after.validation', array($this, $validator));

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
            $validRoles = ['super admin', 'mall admin', 'mall owner', 'mall customer service', 'consumer'];
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
                    'sort_by' => 'in:registered_date,lucky_draw_name,description,start_date,end_date,status,total_issued_lucky_draw_number,external_lucky_draw_id,mall_name,minimum_amount,updated_at,draw_date,campaign_status',
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
            $prefix = DB::getTablePrefix();
            $luckydraws = LuckyDraw::excludeDeleted('lucky_draws')
                                    ->select('lucky_draws.*', 'lucky_draw_translations.lucky_draw_name as lucky_draw_name_english', 'lucky_draw_translations.lucky_draw_name as lucky_draw_name_english', 'campaign_status.order', DB::raw('media.path as image_path'), DB::raw("CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired' THEN {$prefix}campaign_status.campaign_status_name ELSE (CASE WHEN {$prefix}lucky_draws.end_date < (SELECT CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', ot.timezone_name)
                                                                FROM {$prefix}merchants om
                                                                LEFT JOIN {$prefix}timezones ot on ot.timezone_id = om.timezone_id
                                                                WHERE om.merchant_id = {$prefix}lucky_draws.mall_id)
                                        THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END) END AS campaign_status"))
                                    ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'lucky_draws.campaign_status_id')
                                    ->leftJoin('lucky_draw_translations', 'lucky_draw_translations.lucky_draw_id', '=', 'lucky_draws.lucky_draw_id')
                                    ->leftJoin('languages', 'languages.language_id', '=', 'lucky_draw_translations.merchant_language_id')
                                    ->leftJoin(DB::raw("( SELECT * FROM {$prefix}media WHERE media_name_long = 'lucky_draw_translation_image_resized_default' ) as media"), DB::raw('media.object_id'), '=', 'lucky_draw_translations.lucky_draw_translation_id')
                                    ->where('languages.name', '=', 'en');
            if ($details_view === 'yes' || $this->returnBuilder) {
                $luckydraws->select('lucky_draws.*', 'lucky_draw_translations.lucky_draw_name as lucky_draw_name_english', 'campaign_status.order', DB::raw("CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired' THEN {$prefix}campaign_status.campaign_status_name ELSE (CASE WHEN {$prefix}lucky_draws.end_date < (SELECT CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', ot.timezone_name)
                    FROM {$prefix}merchants om
                    LEFT JOIN {$prefix}timezones ot on ot.timezone_id = om.timezone_id
                    WHERE om.merchant_id = {$prefix}lucky_draws.mall_id) THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END) END  AS campaign_status"), 'merchants.name',
                                    DB::raw('media.path as image_path'), 
                                    DB::raw("count({$prefix}lucky_draw_numbers.lucky_draw_number_id) as total_issued_lucky_draw_number"))
                                    ->joinLuckyDrawNumbers()
                                    ->joinMerchant()
                                    ->groupBy('lucky_draws.lucky_draw_id');

                // Filter by user_ids
                if ($user->isConsumer()) {
                    $luckydraws->where('lucky_draws.status', 'active');
                    $luckydraws->where('lucky_draw_numbers.user_id', $user->user_id);
                } else {
                    OrbitInput::get('user_id', function ($arg) use ($luckydraws)
                    {
                        $luckydraws->whereIn('lucky_draw_numbers.user_id', (array)$arg);
                    });
                }
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
                $luckydraws->whereIn('lucky_draw_translations.lucky_draw_name', $name);
            });

            // Filter lucky draw by matching name pattern
            OrbitInput::get('lucky_draw_name_like', function($name) use ($luckydraws)
            {
                $luckydraws->where('lucky_draw_translations.lucky_draw_name', 'like', "%$name%");
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
            OrbitInput::get('begin_date', function($beginDate) use ($luckydraws)
            {
                $luckydraws->where('lucky_draws.end_date', '>=', $beginDate);
            });

            // Filter lucky draw by end date
            OrbitInput::get('end_date', function($endDate) use ($luckydraws)
            {
                $luckydraws->where('lucky_draws.start_date', '<=', $endDate);
            });

            // Filter lucky draw by draw date
            OrbitInput::get('draw_date', function($drawDate) use ($luckydraws)
            {
                $luckydraws->where('lucky_draws.draw_date', '>=', $drawDate);
            });

            // Filter news by status
            OrbitInput::get('campaign_status', function ($statuses) use ($luckydraws, $prefix) {
                $luckydraws->whereIn(DB::raw("CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired' THEN {$prefix}campaign_status.campaign_status_name ELSE (CASE WHEN {$prefix}lucky_draws.end_date < (SELECT CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', ot.timezone_name)
                    FROM {$prefix}merchants om
                    LEFT JOIN {$prefix}timezones ot on ot.timezone_id = om.timezone_id
                    WHERE om.merchant_id = {$prefix}lucky_draws.mall_id) THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END) END"), $statuses);
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

            // Filter by minimum amount
            OrbitInput::get('minimum_amount', function($data) use ($luckydraws) {
                $luckydraws->where('lucky_draws.minimum_amount', '=', $data);
            });

            // Filter by starting minimum amount
            OrbitInput::get('from_minimum_amount', function($data) use ($luckydraws) {
                $luckydraws->where('lucky_draws.minimum_amount', '>=', str_replace(',', '', $data));
            });

            // Filter by ending minimum amount
            OrbitInput::get('to_minimum_amount', function($data) use ($luckydraws) {
                $luckydraws->where('lucky_draws.minimum_amount', '<=', str_replace(',', '', $data));
            });

            // Add new relation based on request
            OrbitInput::get('with', function ($with) use ($luckydraws, $user) {
                $with = (array) $with;

                foreach ($with as $relation) {
                    if ($relation === 'mall') {
                        $luckydraws->with('mall');
                    } elseif ($relation === 'media') {
                        $luckydraws->with('media');
                    } elseif ($relation === 'winners') {
                        $luckydraws->with('winners');
                    } elseif ($relation === 'numbers') {
                        $luckydraws->with(array ('numbers' => function($q) use ($user){
                            $q->whereNotNull('lucky_draw_numbers.user_id');
                            if ($user->isConsumer()){
                              $q->where('lucky_draw_numbers.user_id', '=', $user->user_id);
                            }
                        }));
                    } elseif ($relation === 'issued_numbers') {
                        $luckydraws->with('issuedNumbers');
                    } elseif ($relation === 'translations') {
                        $luckydraws->with('translations');
                    } elseif ($relation === 'translations.media') {
                        $luckydraws->with('translations.media');
                    } elseif ($relation === 'translations.language.language') {
                        $luckydraws->with('translations.language.language');
                    } elseif ($relation === 'announcements') {
                        $luckydraws->with('announcements');
                    } elseif ($relation === 'prizes') {
                        $luckydraws->with('prizes');
                    } elseif ($relation === 'prizes.winners') {
                        $luckydraws->with('prizes.winners');
                    } elseif ($relation === 'prizes.winners.number') {
                        $luckydraws->with('prizes.winners.number');
                    } elseif ($relation === 'prizes.winners.number.user') {
                        $luckydraws->with('prizes.winners.number.user');
                    } elseif ($relation === 'announcements.translations') {
                        $luckydraws->with('announcements.translations');
                    } elseif ($relation === 'announcements.translations.language.language') {
                        $luckydraws->with('announcements.translations.language.language');
                    } elseif ($relation === 'announcements.translations.media') {
                        $luckydraws->with('announcements.translations.media');
                    }
                }
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_luckydraws = clone $luckydraws;

            // if not printing / exporting data then do pagination.
            if (! $this->returnBuilder)
            {
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
            }

            // Default sort by
            $sortBy = 'campaign_status';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'registered_date'                => 'lucky_draws.created_at',
                    'lucky_draw_name'                => 'lucky_draw_translations.lucky_draw_name',
                    'description'                    => 'lucky_draws.description',
                    'start_date'                     => 'lucky_draws.start_date',
                    'end_date'                       => 'lucky_draws.end_date',
                    'draw_date'                      => 'lucky_draws.draw_date',
                    'status'                         => 'campaign_status',
                    'campaign_status'                => 'campaign_status',
                    'external_lucky_draw_id'         => 'lucky_draws.external_lucky_draw_id',
                    'minimum_amount'                 => 'lucky_draws.minimum_amount',
                    'updated_at'                     => 'lucky_draws.updated_at',
                    'mall_name'                      => 'merchants.name',
                    'total_issued_lucky_draw_number' => 'total_issued_lucky_draw_number'
                );

                if (array_key_exists($_sortBy, $sortByMapping)) {
                    $sortBy = $sortByMapping[$_sortBy];
                }
            });

            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });

            $luckydraws->orderBy($sortBy, $sortMode);

            //with name
            if ($sortBy !== 'lucky_draw_translations.lucky_draw_name') {
                $luckydraws->orderBy('lucky_draw_translations.lucky_draw_name', 'asc');
            }

            // Return the instance of Query Builder
            if ($this->returnBuilder) {
                return ['builder' => $luckydraws, 'count' => RecordCounter::create($_luckydraws)->count()];
            }

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
                                         // ->joinReceipts()
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

            if ($sortBy === 'lucky_draw_numbers.lucky_draw_number_code') {
                $prefix = DB::getTablePrefix();
                $luckydraws->orderByRaw("CAST({$prefix}{$sortBy} as UNSIGNED) {$sortMode}");
            } else {
                $luckydraws->orderBy($sortBy, $sortMode);
            }

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
                                     //   $join->where('lucky_draw_numbers.user_id', 'in' , DB::raw("('$user->user_id')"));
                                    })
                                    ->whereIn('lucky_draw_numbers.user_id', [$user->user_id])
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

    /**
     * POST - Create New Lucky Draw Announcement
     *
     * @author Ahmad <ahmad@dominopos.com>
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postNewLuckyDrawAnnouncement()
    {
        $activity = Activity::portal()
                            ->setActivityType('create');

        $user = NULL;
        $lucky_draw_announcement = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.luckydraw.postnewluckydrawannouncement.before.auth', array($this));

            $this->checkAuth();

            Event::fire('orbit.luckydraw.postnewluckydrawannouncement.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.luckydraw.postnewluckydrawannouncement.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('create_lucky_draw')) {
                Event::fire('orbit.luckydraw.postnewluckydrawannouncement.authz.notallowed', array($this, $user));
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

            Event::fire('orbit.luckydraw.postnewluckydrawannouncement.after.authz', array($this, $user));

            $this->registerCustomValidation();

            // set mall id
            $mall_id = OrbitInput::post('mall_id', OrbitInput::post('merchant_id'));
            if (trim($mall_id) === '') {
                // if not being sent, then set to current box mall id
                $mall_id = Config::get('orbit.shop.id');
            }

            $lucky_draw_id = OrbitInput::post('lucky_draw_id');
            $id_language_default = OrbitInput::post('id_language_default');
            $title = OrbitInput::post('title');
            $description = OrbitInput::post('description');

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
                    'lucky_draw_id'            => $lucky_draw_id,
                    'id_language_default'      => $id_language_default,
                    'title'                    => $title,
                    'description'              => $description,
                    'status'                   => $status,
                ),
                array(
                    'mall_id'                  => 'required|orbit.empty.mall',
                    'lucky_draw_id'            => 'required|orbit.empty.lucky_draw',
                    'id_language_default'      => 'required|orbit.empty.language_default',
                    'title'                    => 'required|max:255',
                    'description'              => 'required',
                    'status'                   => 'required|orbit.empty.lucky_draw_status',
                )
            );

            Event::fire('orbit.luckydraw.postnewluckydrawannouncement.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            Event::fire('orbit.luckydraw.postnewluckydrawannouncement.after.validation', array($this, $validator));

            $lucky_draw = LuckyDraw::excludeDeleted()->where('lucky_draw_id', $lucky_draw_id)->first();

            $lucky_draw_announcement = new LuckyDrawAnnouncement();
            $lucky_draw_announcement->title = $title;
            $lucky_draw_announcement->description = $description;
            $lucky_draw_announcement->lucky_draw_id = $lucky_draw_id;
            $lucky_draw_announcement->status = $status;
            $lucky_draw_announcement->created_by = $this->api->user->user_id;
            $lucky_draw_announcement->modified_by = $this->api->user->user_id;
            $lucky_draw_announcement->save();

            Event::fire('orbit.luckydraw.after.announcement.save', array($this, $lucky_draw_announcement));

            // save default language translation
            $lucky_draw_announcement_translation_default = new LuckyDrawAnnouncementTranslation();
            $lucky_draw_announcement_translation_default->lucky_draw_announcement_id = $lucky_draw_announcement->lucky_draw_announcement_id;
            $lucky_draw_announcement_translation_default->merchant_language_id = $id_language_default;
            $lucky_draw_announcement_translation_default->title= $lucky_draw_announcement->title;
            $lucky_draw_announcement_translation_default->description = $lucky_draw_announcement->description;
            $lucky_draw_announcement_translation_default->status = 'active';
            $lucky_draw_announcement_translation_default->created_by = $this->api->user->user_id;
            $lucky_draw_announcement_translation_default->modified_by = $this->api->user->user_id;
            $lucky_draw_announcement_translation_default->save();

            Event::fire('orbit.luckydraw.after.announcement.translation.save', array($this, $lucky_draw_announcement_translation_default));

            OrbitInput::post('translations', function ($announcement_translations) use ($lucky_draw_announcement) {
                $this->validateAndSaveAnnouncementTranslations($lucky_draw_announcement, $announcement_translations, 'create');
            });

            // get default mall language id
            $default = Mall::select('mobile_default_language', 'name')
                            ->where('merchant_id', '=', $mall_id)
                            ->first();

            $idLanguage = Language::select('language_id', 'name_long')
                                ->where('name', '=', $default->mobile_default_language)
                                ->first();

            $isAvailable = LuckyDrawAnnouncementTranslation::where('merchant_language_id', '=', $idLanguage->language_id)
                                            ->where('lucky_draw_announcement_id', '=', $lucky_draw_announcement->lucky_draw_announcement_id)
                                            ->where('title', '!=', '')
                                            ->where('description', '!=', '')
                                            ->count();

            if ($isAvailable == 0) {
                $errorMessage = Lang::get('validation.orbit.empty.default_language');
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $prize_winners_response = null;
            // associate prize with number
            OrbitInput::post('prize_winners', function ($prize_winners) use ($lucky_draw_announcement, $lucky_draw_id, &$prize_winners_response) {
                $prize_winners_response = array();
                $data = @json_decode($prize_winners);

                if (json_last_error() != JSON_ERROR_NONE) {
                    $this->rollBack();
                    OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.jsonerror.field.format', ['field' => 'prize_winners']));
                }

                foreach ($data as $prize_winner) {
                    // check prize existance
                    $prize = LuckyDrawPrize::excludeDeleted()->where('lucky_draw_prize_id', $prize_winner->lucky_draw_prize_id)->first();
                    if (! is_object($prize)) {
                        $this->rollBack();
                        $errorMessage = 'Lucky draw prize not found.';
                        OrbitShopAPI::throwInvalidArgument($errorMessage);
                    }

                    // check winning number qty
                    if (count($prize_winner->winners) > $prize->winner_number) {
                        $this->rollBack();
                        $errorMessage = 'Prize winner numbers exceed the predefined value on lucky draw prize.';
                        OrbitShopAPI::throwInvalidArgument($errorMessage);
                    }

                    foreach($prize_winner->winners as $winner) {
                        // check issued number existance
                        $lucky_draw_number = LuckyDrawNumber::excludeDeleted()->where('lucky_draw_id', $lucky_draw_id)->where('lucky_draw_number_code', $winner->lucky_draw_number_code)->first();
                        if (! is_object($lucky_draw_number)) {
                            $this->rollBack();
                            $errorMessage = 'Lucky draw number is not found.';
                            OrbitShopAPI::throwInvalidArgument($errorMessage);
                        }

                        // if the lucky_draw_winner_id is specified then update the lucky draw winner number
                        if (isset($winner->lucky_draw_winner_id)) {
                            // check already existing number for update
                            $lucky_draw_number_winner = LuckyDrawWinner::excludeDeleted()->where('lucky_draw_winner_id', $winner->lucky_draw_winner_id)->first();
                            if (! is_object($lucky_draw_number_winner)) {
                                $this->rollBack();
                                $errorMessage = 'Lucky draw winner number not found.';
                                OrbitShopAPI::throwInvalidArgument($errorMessage);
                            }

                            $lucky_draw_number_winner_prev = LuckyDrawWinner::excludeDeleted()
                                ->where('lucky_draw_id', $lucky_draw_id)
                                ->where('lucky_draw_winner_code', $winner->lucky_draw_number_code)
                                ->where('lucky_draw_winner_id', '<>', $winner->lucky_draw_winner_id)
                                ->first();

                            if (is_object($lucky_draw_number_winner_prev)) {
                                $this->rollBack();
                                $errorMessage = 'Winning number is duplicated.';
                                OrbitShopAPI::throwInvalidArgument($errorMessage);
                            }

                            $lucky_draw_number_winner->lucky_draw_winner_code = $winner->lucky_draw_number_code;
                            $lucky_draw_number_winner->lucky_draw_number_id = $lucky_draw_number->lucky_draw_number_id;
                            $lucky_draw_number_winner->modified_by = $this->api->user->user_id;
                            $lucky_draw_number_winner->save();
                        } else {
                            // if these two conditional maybe included in lucky draw campaign setup then it should be changed from config to LuckyDraw
                            // conditional check for someone has already won another prize
                            if (! Config::get('orbit.lucky_draw.winner.more_than_one_all_prize_enabled', FALSE)) {
                                $lucky_draw_number_winner = LuckyDrawWinner::excludeDeleted()
                                    ->where('lucky_draw_id', $lucky_draw_id)
                                    ->where('lucky_draw_winner_code', $winner->lucky_draw_number_code)
                                    ->first();
                                if (is_object($lucky_draw_number_winner)) {
                                    $this->rollBack();
                                    $errorMessage = $winner->lucky_draw_number_code . ' has already won another prize.';
                                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                                }
                            }

                            // conditional check for someone has already won the same prize
                            if (! Config::get('orbit.lucky_draw.winner.more_than_one_single_prize_enabled', FALSE)) {
                                $lucky_draw_number_winner = LuckyDrawWinner::excludeDeleted()
                                    ->where('lucky_draw_id', $lucky_draw_id)
                                    ->where('lucky_draw_prize_id', $prize->lucky_draw_prize_id)
                                    ->where('lucky_draw_winner_code', $winner->lucky_draw_number_code)
                                    ->first();
                                if (is_object($lucky_draw_number_winner)) {
                                    $this->rollBack();
                                    $errorMessage = $winner->lucky_draw_number_code . ' has already won the same prize.';
                                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                                }
                            }

                            $lucky_draw_number_winner_prev = LuckyDrawWinner::excludeDeleted()
                                ->where('lucky_draw_id', $lucky_draw_id)
                                ->where('lucky_draw_winner_code', $winner->lucky_draw_number_code)
                                ->first();

                            if (is_object($lucky_draw_number_winner_prev)) {
                                $this->rollBack();
                                $errorMessage = 'Winning number is duplicated.';
                                OrbitShopAPI::throwInvalidArgument($errorMessage);
                            }

                            $lucky_draw_number_winner = new LuckyDrawWinner();
                            $lucky_draw_number_winner->lucky_draw_id = $lucky_draw_id;
                            $lucky_draw_number_winner->lucky_draw_winner_code = $winner->lucky_draw_number_code;
                            $lucky_draw_number_winner->lucky_draw_number_id = $lucky_draw_number->lucky_draw_number_id;
                            $lucky_draw_number_winner->lucky_draw_prize_id = $prize->lucky_draw_prize_id;
                            $lucky_draw_number_winner->status = 'active';
                            $lucky_draw_number_winner->created_by = $this->api->user->user_id;
                            $lucky_draw_number_winner->modified_by = $this->api->user->user_id;
                            $lucky_draw_number_winner->save();
                        }
                    }

                    $prize_winners_response[] = $prize->load('winners.number.user');
                }
            });

            $this->response->data = $lucky_draw_announcement;
            $this->response->data->translation_default = $lucky_draw_announcement_translation_default;
            $this->response->data->prize_winners = $prize_winners_response;

            $lucky_draw->touch();
            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activityNotes = sprintf('Lucky Draw Created: %s', $lucky_draw_announcement->lucky_draw_name);
            $activity->setUser($user)
                    ->setActivityName('create_lucky_draw_announcement')
                    ->setActivityNameLong('Create Lucky Draw Announcement OK')
                    ->setObject($lucky_draw_announcement)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.luckydraw.postnewluckydrawannouncement.after.commit', array($this, $lucky_draw_announcement  ));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.luckydraw.postnewluckydrawannouncement.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_lucky_draw_announcement')
                    ->setActivityNameLong('Create Lucky Draw Announcement Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.luckydraw.postnewluckydrawannouncement.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_lucky_draw_announcement')
                    ->setActivityNameLong('Create Lucky Draw Announcement Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.luckydraw.postnewluckydrawannouncement.query.error', array($this, $e));

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
                    ->setActivityName('create_lucky_draw_announcement')
                    ->setActivityNameLong('Create Lucky Draw Announcement Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.luckydraw.postnewluckydrawannouncement.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_lucky_draw_announcement')
                    ->setActivityNameLong('Create Lucky Draw Announcement Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save the activity
        $activity->save();
        return $this->render($httpCode);
    }

    /**
     * POST - Update New Lucky Draw Announcement
     *
     * @author Ahmad <ahmad@dominopos.com>
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postUpdateLuckyDrawAnnouncement()
    {
        $activity = Activity::portal()
                           ->setActivityType('update');

        $user = NULL;
        $lucky_draw_announcement = NULL;
        try {
            $httpCode=200;

            Event::fire('orbit.luckydraw.postupdateluckydrawannouncement.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.luckydraw.postupdateluckydrawannouncement.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.luckydraw.postupdateluckydrawannouncement.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('update_lucky_draw')) {
                Event::fire('orbit.luckydraw.postupdateluckydrawannouncement.authz.notallowed', array($this, $user));
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

            Event::fire('orbit.luckydraw.postupdateluckydrawannouncement.after.authz', array($this, $user));

            $this->registerCustomValidation();

            // set mall id
            $mall_id = OrbitInput::post('mall_id', OrbitInput::post('merchant_id'));
            if (trim($mall_id) === '') {
                // if not being sent, then set to current box mall id
                $mall_id = Config::get('orbit.shop.id');
            }

            $lucky_draw_id = OrbitInput::post('lucky_draw_id');
            $lucky_draw_announcement_id = OrbitInput::post('lucky_draw_announcement_id');
            $status = OrbitInput::post('status');
            $title = OrbitInput::post('title');
            $description = OrbitInput::post('description');
            $id_language_default = OrbitInput::post('id_language_default');

            $now = date('Y-m-d H:i:s');

            $data = array(
                'lucky_draw_id'        => $lucky_draw_id,
                'mall_id'              => $mall_id,
                'lucky_draw_announcement_id'      => $lucky_draw_announcement_id,
                'title'                => $title,
                'status'               => $status,
                'id_language_default'  => $id_language_default,
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
                    'lucky_draw_id'        => 'required|orbit.empty.lucky_draw:' . $mall_id,
                    'mall_id'              => 'orbit.empty.mall',
                    'lucky_draw_announcement_id'      => 'required|orbit.empty.lucky_draw_announcement',
                    'title'                => 'max:255',
                    'status'               => 'orbit.empty.lucky_draw_status',
                    'id_language_default'  => 'required|orbit.empty.language_default',
                )
            );

            Event::fire('orbit.luckydraw.postupdateluckydrawannouncement.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            Event::fire('orbit.luckydraw.postupdateluckydrawannouncement.after.validation', array($this, $validator));

            $lucky_draw = LuckyDraw::excludeDeleted()->where('lucky_draw_id', $lucky_draw_id)->first();
            $lucky_draw_announcement = LuckyDrawAnnouncement::where('lucky_draw_announcement_id', $lucky_draw_announcement_id)->first();
            $lucky_draw_announcement_translation_default = LuckyDrawAnnouncementTranslation::excludeDeleted()->where('lucky_draw_announcement_id', $lucky_draw_announcement_id)->where('merchant_language_id', $id_language_default)->first();

            OrbitInput::post('title', function($title) use ($lucky_draw_announcement, $lucky_draw_announcement_translation_default) {
                $lucky_draw_announcement->title = $title;
                $lucky_draw_announcement_translation_default->title= $title;
            });

            OrbitInput::post('description', function($description) use ($lucky_draw_announcement, $lucky_draw_announcement_translation_default) {
                $lucky_draw_announcement->description = $description;
                $lucky_draw_announcement_translation_default->description = $description;
            });

            OrbitInput::post('status', function($status) use ($lucky_draw_announcement, $lucky_draw_announcement_translation_default) {
                $lucky_draw_announcement->status = $status;
                $lucky_draw_announcement_translation_default->status = $status;
            });

            $lucky_draw_announcement_translation_default->modified_by = $this->api->user->user_id;
            $lucky_draw_announcement->modified_by = $this->api->user->user_id;

            $lucky_draw_announcement->save();
            Event::fire('orbit.luckydraw.after.announcement.save', array($this, $lucky_draw_announcement));

            $lucky_draw_announcement_translation_default->save();
            Event::fire('orbit.luckydraw.after.announcement.translation.save', array($this, $lucky_draw_announcement_translation_default));

            OrbitInput::post('translations', function ($announcement_translations) use ($lucky_draw_announcement) {
                $this->validateAndSaveAnnouncementTranslations($lucky_draw_announcement, $announcement_translations, 'update');
            });

            // get default mall language id
            $default = Mall::select('mobile_default_language', 'name')
                            ->where('merchant_id', '=', $mall_id)
                            ->first();

            $idLanguage = Language::select('language_id', 'name_long')
                                ->where('name', '=', $default->mobile_default_language)
                                ->first();

            $isAvailable = LuckyDrawAnnouncementTranslation::where('merchant_language_id', '=', $idLanguage->language_id)
                                            ->where('lucky_draw_announcement_id', '=', $lucky_draw_announcement_id)
                                            ->where('title', '!=', '')
                                            ->where('description', '!=', '')
                                            ->count();

            if ($isAvailable == 0) {
                $errorMessage = Lang::get('validation.orbit.empty.default_language');
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $prize_winners_response = null;
            // associate prize with number
            OrbitInput::post('prize_winners', function ($prize_winners) use ($lucky_draw_announcement, $lucky_draw_id, &$prize_winners_response) {
                $prize_winners_response = array();
                $data = @json_decode($prize_winners);

                if (json_last_error() != JSON_ERROR_NONE) {
                    OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.jsonerror.field.format', ['field' => 'prize_winners']));
                }

                foreach ($data as $prize_winner) {
                    // check prize existance
                    $prize = LuckyDrawPrize::excludeDeleted()->where('lucky_draw_prize_id', $prize_winner->lucky_draw_prize_id)->first();
                    if (! is_object($prize)) {
                        $errorMessage = 'Lucky draw prize not found.';
                        OrbitShopAPI::throwInvalidArgument($errorMessage);
                    }

                    // check winning number qty
                    if (count($prize_winner->winners) > $prize->winner_number) {
                        $errorMessage = 'Prize winner numbers exceed the predefined value on lucky draw prize.';
                        OrbitShopAPI::throwInvalidArgument($errorMessage);
                    }
                    // delete existing winning number
                    // $existing_winning_numbers = LuckyDrawWinner::where('lucky_draw_id', $lucky_draw_id)->where('lucky_draw_prize_id', $prize->lucky_draw_prize_id)->get();
                    // $existing_winning_numbers->delete(TRUE);

                    foreach($prize_winner->winners as $winner) {
                        // check if empty winner code on existing number
                        //if (! empty($winner->lucky_draw_number_code)) {

                        // if the lucky_draw_winner_id is specified then update the lucky draw winner number
                        if (isset($winner->lucky_draw_winner_id)) {
                            if (! empty($winner->lucky_draw_number_code)) {
                                // check issued number existance
                                $lucky_draw_number = LuckyDrawNumber::excludeDeleted()->where('lucky_draw_id', $lucky_draw_id)->where('lucky_draw_number_code', $winner->lucky_draw_number_code)->first();
                                if (! is_object($lucky_draw_number)) {
                                    $errorMessage = 'Lucky draw number (' . $winner->lucky_draw_number_code . ') is not found.';
                                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                                }
                                // check already existing number for update
                                $lucky_draw_number_winner = LuckyDrawWinner::excludeDeleted()->where('lucky_draw_winner_id', $winner->lucky_draw_winner_id)->first();
                                if (! is_object($lucky_draw_number_winner)) {
                                    $errorMessage = 'Lucky draw winner number not found.';
                                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                                }

                                $lucky_draw_number_winner_prev = LuckyDrawWinner::excludeDeleted()
                                    ->where('lucky_draw_id', $lucky_draw_id)
                                    ->where('lucky_draw_winner_code', $winner->lucky_draw_number_code)
                                    ->where('lucky_draw_winner_id', '<>', $winner->lucky_draw_winner_id)
                                    ->first();

                                if (is_object($lucky_draw_number_winner_prev)) {
                                    $this->rollBack();
                                    $errorMessage = 'Winning number is duplicated.';
                                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                                }

                                $lucky_draw_number_winner->lucky_draw_winner_code = $winner->lucky_draw_number_code;
                                $lucky_draw_number_winner->lucky_draw_number_id = $lucky_draw_number->lucky_draw_number_id;
                                $lucky_draw_number_winner->modified_by = $this->api->user->user_id;
                                $lucky_draw_number_winner->save();
                            } else {
                                // check already existing number for update
                                $lucky_draw_number_winner = LuckyDrawWinner::excludeDeleted()->where('lucky_draw_winner_id', $winner->lucky_draw_winner_id)->first();
                                if (! is_object($lucky_draw_number_winner)) {
                                    $errorMessage = 'Lucky draw winner number not found.';
                                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                                }

                                $lucky_draw_number_winner->lucky_draw_winner_code = '';
                                $lucky_draw_number_winner->lucky_draw_number_id = null;
                                $lucky_draw_number_winner->modified_by = $this->api->user->user_id;
                                $lucky_draw_number_winner->save();
                            }
                        } else {
                            if (! empty($winner->lucky_draw_number_code)) {
                                // check issued number existance
                                $lucky_draw_number = LuckyDrawNumber::excludeDeleted()->where('lucky_draw_id', $lucky_draw_id)->where('lucky_draw_number_code', $winner->lucky_draw_number_code)->first();
                                if (! is_object($lucky_draw_number)) {
                                    $errorMessage = 'Lucky draw number (' . $winner->lucky_draw_number_code . ') is not found.';
                                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                                }

                                // if these two conditional maybe included in lucky draw campaign setup then it should be changed from config to LuckyDraw
                                // conditional check for someone has already won another prize
                                if (! Config::get('orbit.lucky_draw.winner.more_than_one_all_prize_enabled', FALSE)) {
                                    $lucky_draw_number_winner = LuckyDrawWinner::excludeDeleted()
                                        ->where('lucky_draw_id', $lucky_draw_id)
                                        ->where('lucky_draw_winner_code', $winner->lucky_draw_number_code)
                                        ->first();
                                    if (is_object($lucky_draw_number_winner)) {
                                        $errorMessage = $winner->lucky_draw_number_code . ' has already won another prize.';
                                        OrbitShopAPI::throwInvalidArgument($errorMessage);
                                    }
                                }

                                // conditional check for someone has already won the same prize
                                if (! Config::get('orbit.lucky_draw.winner.more_than_one_single_prize_enabled', FALSE)) {
                                    $lucky_draw_number_winner = LuckyDrawWinner::excludeDeleted()
                                        ->where('lucky_draw_id', $lucky_draw_id)
                                        ->where('lucky_draw_prize_id', $prize->lucky_draw_prize_id)
                                        ->where('lucky_draw_winner_code', $winner->lucky_draw_number_code)
                                        ->first();
                                    if (is_object($lucky_draw_number_winner)) {
                                        $errorMessage = $winner->lucky_draw_number_code . ' has already won the same prize.';
                                        OrbitShopAPI::throwInvalidArgument($errorMessage);
                                    }
                                }

                                $lucky_draw_number_winner_prev = LuckyDrawWinner::excludeDeleted()
                                    ->where('lucky_draw_id', $lucky_draw_id)
                                    ->where('lucky_draw_winner_code', $winner->lucky_draw_number_code)
                                    ->first();

                                if (is_object($lucky_draw_number_winner_prev)) {
                                    $this->rollBack();
                                    $errorMessage = 'Winning number is duplicated.';
                                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                                }

                                $lucky_draw_number_winner = new LuckyDrawWinner();
                                $lucky_draw_number_winner->lucky_draw_id = $lucky_draw_id;
                                $lucky_draw_number_winner->lucky_draw_winner_code = $winner->lucky_draw_number_code;
                                $lucky_draw_number_winner->lucky_draw_number_id = $lucky_draw_number->lucky_draw_number_id;
                                $lucky_draw_number_winner->lucky_draw_prize_id = $prize->lucky_draw_prize_id;
                                $lucky_draw_number_winner->status = 'active';
                                $lucky_draw_number_winner->created_by = $this->api->user->user_id;
                                $lucky_draw_number_winner->modified_by = $this->api->user->user_id;
                                $lucky_draw_number_winner->save();
                            }
                        }
                    }

                    $prize_winners_response[] = $prize->load('winners.number.user');
                }
            });

            $this->response->data = $lucky_draw_announcement;
            $this->response->data->translation_default = $lucky_draw_announcement_translation_default;
            $this->response->data->prize_winners = $prize_winners_response;

            $lucky_draw->touch();
            // Commit the changes
            $this->commit();

            // Successful Update
            $activityNotes = sprintf('Lucky Draw Announcement updated: %s', $lucky_draw_announcement->title);
            $activity->setUser($user)
                    ->setActivityName('update_lucky_draw_announcement')
                    ->setActivityNameLong('Update Lucky Draw OK')
                    ->setObject($lucky_draw_announcement)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.luckydraw.postupdateluckydrawannouncement.after.commit', array($this, $lucky_draw_announcement));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.luckydraw.postupdateluckydrawannouncement.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_lucky_draw_announcement')
                    ->setActivityNameLong('Update Lucky Draw Announcement Failed')
                    ->setObject($lucky_draw_announcement)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.luckydraw.postupdateluckydrawannouncement.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_lucky_draw_announcement')
                    ->setActivityNameLong('Update Lucky Draw Announcement Failed')
                    ->setObject($lucky_draw_announcement)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.luckydraw.postupdateluckydrawannouncement.query.error', array($this, $e));

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
                    ->setActivityName('update_lucky_draw_announcement')
                    ->setActivityNameLong('Update Lucky Draw Announcement Failed')
                    ->setObject($lucky_draw_announcement)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.luckydraw.postupdateluckydrawannouncement.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = [$e->getLine()];

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_lucky_draw_announcement')
                    ->setActivityNameLong('Update Lucky Draw Announcement Failed')
                    ->setObject($lucky_draw_announcement)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save activity
        $activity->save();

        return $this->render($httpCode);
    }

    /**
     * POST - Create New Lucky Draw Prize
     *
     * @author Ahmad <ahmad@dominopos.com>
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postNewLuckyDrawPrize()
    {
        $activity = Activity::portal()
                            ->setActivityType('create');

        $user = NULL;
        $lucky_draw_prize = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.luckydraw.postnewluckydrawprize.before.auth', array($this));

            $this->checkAuth();

            Event::fire('orbit.luckydraw.postnewluckydrawprize.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.luckydraw.postnewluckydrawprize.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('create_lucky_draw')) {
                Event::fire('orbit.luckydraw.postnewluckydrawprize.authz.notallowed', array($this, $user));
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

            Event::fire('orbit.luckydraw.postnewluckydrawprize.after.authz', array($this, $user));

            $this->registerCustomValidation();

            // set mall id
            $mall_id = OrbitInput::post('mall_id', OrbitInput::post('merchant_id'));
            if (trim($mall_id) === '') {
                // if not being sent, then set to current box mall id
                $mall_id = Config::get('orbit.shop.id');
            }

            $lucky_draw_id = OrbitInput::post('lucky_draw_id');
            $prize_name = OrbitInput::post('prize_name');
            $winner_number = OrbitInput::post('winner_number');
            $order = OrbitInput::post('order', 0);

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
                    'lucky_draw_id'            => $lucky_draw_id,
                    'prize_name'               => $prize_name,
                    'winner_number'            => $winner_number,
                    'order'                    => $order,
                    'status'                   => $status,
                ),
                array(
                    'mall_id'                  => 'required|orbit.empty.mall',
                    'lucky_draw_id'            => 'required|orbit.empty.lucky_draw',
                    'prize_name'               => 'required|max:255|orbit.exists.lucky_draw_prize_name:' . $lucky_draw_id,
                    'winner_number'            => 'required|numeric',
                    'order'                    => 'numeric',
                    'status'                   => 'required|orbit.empty.lucky_draw_status',
                )
            );

            Event::fire('orbit.luckydraw.postnewluckydrawprize.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            Event::fire('orbit.luckydraw.postnewluckydrawprize.after.validation', array($this, $validator));

            $lucky_draw_prize = new LuckyDrawPrize();
            $lucky_draw_prize->prize_name = $prize_name;
            $lucky_draw_prize->winner_number = $winner_number;
            $lucky_draw_prize->lucky_draw_id = $lucky_draw_id;
            $lucky_draw_prize->order = $order;
            $lucky_draw_prize->status = $status;
            $lucky_draw_prize->created_by = $this->api->user->user_id;
            $lucky_draw_prize->modified_by = $this->api->user->user_id;
            $lucky_draw_prize->save();

            Event::fire('orbit.postnewluckydrawprize.after.save', array($this, $lucky_draw_prize));

            $this->response->data = $lucky_draw_prize;

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activityNotes = sprintf('Lucky Draw Prize Created: %s', $lucky_draw_prize->prize_name);
            $activity->setUser($user)
                    ->setActivityName('create_lucky_draw_prize')
                    ->setActivityNameLong('Create Lucky Draw Prize OK')
                    ->setObject($lucky_draw_prize)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.luckydraw.postnewluckydrawprize.after.commit', array($this, $lucky_draw_prize));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.luckydraw.postnewluckydrawprize.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_lucky_draw_prize')
                    ->setActivityNameLong('Create Lucky Draw Prize Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.luckydraw.postnewluckydrawprize.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_lucky_draw_prize')
                    ->setActivityNameLong('Create Lucky Draw Prize Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.luckydraw.postnewluckydrawprize.query.error', array($this, $e));

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
                    ->setActivityName('create_lucky_draw_prize')
                    ->setActivityNameLong('Create Lucky Draw Prize Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.luckydraw.postnewluckydrawprize.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_lucky_draw_prize')
                    ->setActivityNameLong('Create Lucky Draw Prize Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save the activity
        $activity->save();
        return $this->render($httpCode);
    }

    /**
     * POST - Create Update Lucky Draw Prize
     *
     * @author Ahmad <ahmad@dominopos.com>
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postUpdateLuckyDrawPrize()
    {
        $activity = Activity::portal()
                            ->setActivityType('create');

        $user = NULL;
        $lucky_draw_prize = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.luckydraw.postupdateluckydrawprize.before.auth', array($this));

            $this->checkAuth();

            Event::fire('orbit.luckydraw.postupdateluckydrawprize.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.luckydraw.postupdateluckydrawprize.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('create_lucky_draw')) {
                Event::fire('orbit.luckydraw.postupdateluckydrawprize.authz.notallowed', array($this, $user));
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

            Event::fire('orbit.luckydraw.postupdateluckydrawprize.after.authz', array($this, $user));

            $this->registerCustomValidation();

            // set mall id
            $mall_id = OrbitInput::post('mall_id', OrbitInput::post('merchant_id'));
            if (trim($mall_id) === '') {
                // if not being sent, then set to current box mall id
                $mall_id = Config::get('orbit.shop.id');
            }

            $lucky_draw_prize_id = OrbitInput::post('lucky_draw_prize_id');
            $lucky_draw_id = OrbitInput::post('lucky_draw_id');
            $prize_name = OrbitInput::post('prize_name');
            $winner_number = OrbitInput::post('winner_number');
            $order = OrbitInput::post('order', 0);

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
                    'lucky_draw_prize_id'      => $lucky_draw_prize_id,
                    'lucky_draw_id'            => $lucky_draw_id,
                    'prize_name'               => $prize_name,
                    'winner_number'            => $winner_number,
                    'order'                    => $order,
                    'status'                   => $status,
                ),
                array(
                    'mall_id'                  => 'required|orbit.empty.mall',
                    'lucky_draw_prize_id'      => 'required|orbit.empty.lucky_draw_prize',
                    'lucky_draw_id'            => 'required|orbit.empty.lucky_draw',
                    'prize_name'               => 'required|max:255',
                    'winner_number'            => 'required|numeric',
                    'order'                    => 'numeric',
                    'status'                   => 'required|orbit.empty.lucky_draw_status',
                )
            );

            Event::fire('orbit.luckydraw.postupdateluckydrawprize.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            Event::fire('orbit.luckydraw.postupdateluckydrawprize.after.validation', array($this, $validator));

            $lucky_draw_prize = LuckyDrawPrize::excludeDeleted()->where('lucky_draw_prize_id', $lucky_draw_prize_id)->first();

            OrbitInput::post('prize_name', function($prize_name) use ($lucky_draw_prize) {
                $lucky_draw_prize->prize_name = $prize_name;
            });

            OrbitInput::post('winner_number', function($winner_number) use ($lucky_draw_prize) {
                $lucky_draw_prize->winner_number = $winner_number;
            });

            OrbitInput::post('order', function($order) use ($lucky_draw_prize) {
                $lucky_draw_prize->order = $order;
            });

            OrbitInput::post('status', function($status) use ($lucky_draw_prize) {
                $lucky_draw_prize->status = $status;
            });

            $lucky_draw_prize->modified_by = $this->api->user->user_id;
            $lucky_draw_prize->save();

            Event::fire('orbit.postupdateluckydrawprize.after.save', array($this, $lucky_draw_prize));

            $this->response->data = $lucky_draw_prize;

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activityNotes = sprintf('Lucky Draw Prize Created: %s', $lucky_draw_prize->prize_name);
            $activity->setUser($user)
                    ->setActivityName('create_lucky_draw_prize')
                    ->setActivityNameLong('Create Lucky Draw Prize OK')
                    ->setObject($lucky_draw_prize)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.luckydraw.postupdateluckydrawprize.after.commit', array($this, $lucky_draw_prize));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.luckydraw.postupdateluckydrawprize.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_lucky_draw_prize')
                    ->setActivityNameLong('Create Lucky Draw Prize Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.luckydraw.postupdateluckydrawprize.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_lucky_draw_prize')
                    ->setActivityNameLong('Create Lucky Draw Prize Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.luckydraw.postupdateluckydrawprize.query.error', array($this, $e));

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
                    ->setActivityName('create_lucky_draw_prize')
                    ->setActivityNameLong('Create Lucky Draw Prize Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.luckydraw.postupdateluckydrawprize.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_lucky_draw_prize')
                    ->setActivityNameLong('Create Lucky Draw Prize Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save the activity
        $activity->save();
        return $this->render($httpCode);
    }

    /**
     * GET - Search Lucky Draw Prize
     *
     * @author Ahmad <ahmad@dominopos.com>
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchLuckyDrawPrize()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.luckydraw.getsearchluckydrawprize.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.luckydraw.getsearchluckydrawprize.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.luckydraw.getsearchluckydrawprize.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('view_lucky_draw')) {
                Event::fire('orbit.luckydraw.getsearchluckydrawprize.authz.notallowed', array($this, $user));
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

            Event::fire('orbit.luckydraw.getsearchluckydrawprize.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sortby');
            $details_view = OrbitInput::get('details');
            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                ),
                array(
                    'sort_by' => 'in:registered_date,prize_name,winner_number,order,status',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.lucky_draw_sortby'),
                )
            );

            Event::fire('orbit.luckydraw.getsearchluckydrawprize.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.luckydraw.getsearchluckydrawprize.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.lucky_draw_prize.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.lucky_draw_prize.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            // Builder object
            $luckydrawprizes = LuckyDrawPrize::excludeDeleted();

            // Filter lucky draw by ids
            OrbitInput::get('lucky_draw_id', function($id) use ($luckydrawprizes)
            {
                $luckydrawprizes->whereIn('lucky_draw_id', $id);
            });

            // Filter lucky draw by mall ids
            OrbitInput::get('mall_id', function ($mallId) use ($luckydrawprizes) {
                $luckydrawprizes->whereHas('luckyDraw', function ($q) use ($mallId) {
                    $q->where('lucky_draws.mall_id', $mallId);
                });
            });

            // Filter lucky draw by name
            OrbitInput::get('prize_name', function($name) use ($luckydrawprizes)
            {
                $luckydrawprizes->whereIn('prize_name', $name);
            });

            // Filter lucky draw by matching name pattern
            OrbitInput::get('prize_name_like', function($name) use ($luckydrawprizes)
            {
                $luckydrawprizes->where('prize_name', 'like', "%$name%");
            });

            // Filter lucky draw by description
            OrbitInput::get('description', function($description) use ($luckydrawprizes)
            {
                $luckydrawprizes->whereIn('description', $description);
            });

            // Filter lucky draw by matching description pattern
            OrbitInput::get('description_like', function($description) use ($luckydrawprizes)
            {
                $luckydrawprizes->where('description', 'like', "%$description%");
            });

            // Filter lucky draw by status
            OrbitInput::get('status', function ($status) use ($luckydrawprizes) {
                $luckydrawprizes->whereIn('status', $status);
            });

            // Add new relation based on request
            OrbitInput::get('with', function ($with) use ($luckydrawprizes) {
                $with = (array) $with;

                foreach ($with as $relation) {
                    if ($relation === 'winners') {
                        $luckydrawprizes->with('winners');
                    } elseif ($relation === 'winners.number') {
                        $luckydrawprizes->with('winners.number');
                    } elseif ($relation === 'winners.number.user') {
                        $luckydrawprizes->with('winners.number.user');
                    } elseif ($relation === 'luckydraw') {
                        $luckydrawprizes->with('LuckyDraw');
                    }
                }
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_luckydrawprizes = clone $luckydrawprizes;

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
            $luckydrawprizes->take($take);

            $skip = 0;
            OrbitInput::get('skip', function($_skip) use (&$skip, $luckydrawprizes)
            {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            if (($take > 0) && ($skip > 0)) {
                $luckydrawprizes->skip($skip);
            }

            // Default sort by
            $sortBy = 'lucky_draw_prizes.order';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'registered_date'          => 'lucky_draw_prizes.created_at',
                    'prize_name'               => 'lucky_draw_prizes.prize_name',
                    'winner_number'            => 'lucky_draw_prizes.winner_number',
                    'order'                    => 'lucky_draw_prizes.order',
                    'status'                   => 'lucky_draw_prizes.status',
                );

                if (array_key_exists($_sortBy, $sortByMapping)) {
                    $sortBy = $sortByMapping[$_sortBy];
                }
            });

            if ($sortBy !== 'lucky_draw_prizes.status') {
                $luckydrawprizes->orderBy('lucky_draw_prizes.status', 'asc');
            }

            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });

            $luckydrawprizes->orderBy($sortBy, $sortMode);

            $totalLuckyDrawPrizes = RecordCounter::create($_luckydrawprizes)->count();
            $listOfLuckyDrawPrizes = $luckydrawprizes->get();

            $data = new stdclass();
            $data->total_records = $totalLuckyDrawPrizes;
            $data->returned_records = count($listOfLuckyDrawPrizes);
            $data->records = $listOfLuckyDrawPrizes;

            if ($totalLuckyDrawPrizes === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.lucky_draw_prize');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.luckydraw.getsearchluckydrawprize.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.luckydraw.getsearchluckydrawprize.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.luckydraw.getsearchluckydrawprize.query.error', array($this, $e));

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
            Event::fire('orbit.luckydraw.getsearchluckydrawprize.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.luckydraw.getsearchluckydrawprize.before.render', array($this, &$output));

        return $output;
    }

    /**
     * POST - Create and update Lucky Draw Prize
     *
     * @author Ahmad <ahmad@dominopos.com>
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postNewAndUpdateLuckyDrawPrize()
    {
        $activity = Activity::portal()
                            ->setActivityType('create');

        $user = NULL;
        $lucky_draw_prize = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.luckydraw.postnewupdateluckydrawprize.before.auth', array($this));

            $this->checkAuth();

            Event::fire('orbit.luckydraw.postnewupdateluckydrawprize.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.luckydraw.postnewupdateluckydrawprize.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('create_lucky_draw')) {
                Event::fire('orbit.luckydraw.postnewupdateluckydrawprize.authz.notallowed', array($this, $user));
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

            Event::fire('orbit.luckydraw.postnewupdateluckydrawprize.after.authz', array($this, $user));

            $this->registerCustomValidation();

            // set mall id
            $mall_id = OrbitInput::post('mall_id', OrbitInput::post('merchant_id'));
            if (trim($mall_id) === '') {
                // if not being sent, then set to current box mall id
                $mall_id = Config::get('orbit.shop.id');
            }

            $lucky_draw_id = OrbitInput::post('lucky_draw_id');
            // $prize_name = OrbitInput::post('prize_name');
            // $winner_number = OrbitInput::post('winner_number');
            // $order = OrbitInput::post('order', 0);
            $prizes = OrbitInput::post('prizes');

            // Begin database transaction
            $this->beginTransaction();

            $validator = Validator::make(
                array(
                    'mall_id'                  => $mall_id,
                    'lucky_draw_id'            => $lucky_draw_id,
                ),
                array(
                    'mall_id'                  => 'required|orbit.empty.mall',
                    'lucky_draw_id'            => 'required|orbit.empty.lucky_draw',
                )
            );

            Event::fire('orbit.luckydraw.postnewupdateluckydrawprize.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $prizes_data = @json_decode($prizes);

            if (json_last_error() != JSON_ERROR_NONE) {
                OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.jsonerror.field.format', ['field' => 'prizes']));
            }

            Event::fire('orbit.luckydraw.postnewupdateluckydrawprize.after.validation', array($this, $validator));

            $lucky_draw = LuckyDraw::excludeDeleted()->where('lucky_draw_id', $lucky_draw_id)->first();

            $lucky_draw_prizes = array();
            foreach ($prizes_data as $prize) {
                $prize_name = (empty($prize->prize_name)) ? NULL : $prize->prize_name;
                $winner_number = (empty($prize->winner_number)) ? NULL : $prize->winner_number;
                $order = (empty($prize->order)) ? NULL : $prize->order;
                $status = (empty($prize->status)) ? NULL : $prize->status;

                $validator = Validator::make(
                    array(
                        'prize_name'               => $prize_name,
                        'winner_number'            => $winner_number,
                        'order'                    => $order,
                        'status'                   => $status,
                    ),
                    array(
                        'prize_name'               => 'required|max:255',
                        'winner_number'            => 'required|numeric',
                        'order'                    => 'numeric',
                        'status'                   => 'required|orbit.empty.lucky_draw_status',
                    ),
                    array(
                        'prize_name.required' => 'Prize name is required',
                        'winner_number.required' => 'Number of winners is required',
                    )
                );

                Event::fire('orbit.luckydraw.postnewluckydrawprize.individual.before.validation', array($this, $validator));

                // Run the validation
                if ($validator->fails()) {
                    $errorMessage = $validator->messages()->first();
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }

                Event::fire('orbit.luckydraw.postnewluckydrawprize.individual.after.validation', array($this, $validator));

                if (isset($prize->lucky_draw_prize_id)) {
                    $validator = Validator::make(
                        array(
                            'lucky_draw_prize_id'       => $prize->lucky_draw_prize_id,
                        ),
                        array(
                            'lucky_draw_prize_id'       => 'required|orbit.empty.lucky_draw_prize',
                        )
                    );

                    Event::fire('orbit.luckydraw.postnewluckydrawprize.individual.lucky_draw_prize_id.before.validation', array($this, $validator));

                    // Run the validation
                    if ($validator->fails()) {
                        $errorMessage = $validator->messages()->first();
                        OrbitShopAPI::throwInvalidArgument($errorMessage);
                    }

                    Event::fire('orbit.luckydraw.postnewluckydrawprize.individual.lucky_draw_prize_id.after.validation', array($this, $validator));

                    $lucky_draw_prize = LuckyDrawPrize::excludeDeleted()->where('lucky_draw_prize_id', $prize->lucky_draw_prize_id)->first();

                    // delete the prize
                    if (isset($prize->delete)) {
                        $lucky_draw_prize->delete(TRUE);
                    } else { // update the prize
                        $lucky_draw_prize->prize_name = $prize->prize_name;
                        $lucky_draw_prize->winner_number = $prize->winner_number;
                        $lucky_draw_prize->lucky_draw_id = $lucky_draw_id;
                        $lucky_draw_prize->order = $prize->order;
                        $lucky_draw_prize->status = $prize->status;
                        $lucky_draw_prize->modified_by = $this->api->user->user_id;
                        $lucky_draw_prize->save();
                        $lucky_draw_prizes[] = $lucky_draw_prize;
                    }
                } else { //create the prize
                    $lucky_draw_prize = new LuckyDrawPrize();
                    $lucky_draw_prize->prize_name = $prize->prize_name;
                    $lucky_draw_prize->winner_number = $prize->winner_number;
                    $lucky_draw_prize->lucky_draw_id = $lucky_draw_id;
                    $lucky_draw_prize->order = $prize->order;
                    $lucky_draw_prize->status = $prize->status;
                    $lucky_draw_prize->created_by = $this->api->user->user_id;
                    $lucky_draw_prize->modified_by = $this->api->user->user_id;
                    $lucky_draw_prize->save();
                    $lucky_draw_prizes[] = $lucky_draw_prize;
                }
            }

            Event::fire('orbit.postnewupdateluckydrawprize.after.save', array($this, $lucky_draw_prize));

            $this->response->data = $lucky_draw_prizes;

            $lucky_draw->touch();
            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activityNotes = sprintf('Lucky Draw Prize Update for Lucky Draw: %s', $lucky_draw->lucky_draw_name);
            $activity->setUser($user)
                    ->setActivityName('update_lucky_draw_prize')
                    ->setActivityNameLong('Update Lucky Draw Prize OK')
                    ->setObject(null)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.luckydraw.postnewupdateluckydrawprize.after.commit', array($this, $lucky_draw_prize));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.luckydraw.postnewupdateluckydrawprize.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('update_lucky_draw_prize')
                    ->setActivityNameLong('Update Lucky Draw Prize Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.luckydraw.postnewupdateluckydrawprize.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('update_lucky_draw_prize')
                    ->setActivityNameLong('Update Lucky Draw Prize Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.luckydraw.postnewupdateluckydrawprize.query.error', array($this, $e));

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
                    ->setActivityName('update_lucky_draw_prize')
                    ->setActivityNameLong('Update Lucky Draw Prize Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.luckydraw.postnewupdateluckydrawprize.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('update_lucky_draw_prize')
                    ->setActivityNameLong('Update Lucky Draw Prize Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save the activity
        $activity->save();
        return $this->render($httpCode);
    }

    /**
     * POST - Create New Lucky Draw Announcement
     *
     * @author Ahmad <ahmad@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string   `merchant_id|mall_id`          (required)
     * @param string   `lucky_draw_id`                (required)
     * @param string   `lucky_announcement_id`        (required)
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postBlastLuckyDrawAnnouncement()
    {
        $activity = Activity::portal()
                            ->setActivityType('create');

        $user = NULL;
        $lucky_draw_announcement = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.luckydraw.postblastluckydrawannouncement.before.auth', array($this));

            $this->checkAuth();

            Event::fire('orbit.luckydraw.postblastluckydrawannouncement.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.luckydraw.postblastluckydrawannouncement.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('create_lucky_draw')) {
                Event::fire('orbit.luckydraw.postblastluckydrawannouncement.authz.notallowed', array($this, $user));
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

            Event::fire('orbit.luckydraw.postblastluckydrawannouncement.after.authz', array($this, $user));

            $this->registerCustomValidation();

            // set mall id
            $mall_id = OrbitInput::post('current_mall', OrbitInput::post('mall_id', OrbitInput::post('merchant_id')));
            if (trim($mall_id) === '') {
                // if not being sent, then set to current box mall id
                $mall_id = Config::get('orbit.shop.id');
            }

            $lucky_draw_id = OrbitInput::post('lucky_draw_id');
            $lucky_announcement_id = OrbitInput::post('lucky_draw_announcement_id');

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
                    'lucky_draw_id'            => $lucky_draw_id,
                    'lucky_draw_announcement_id'    => $lucky_announcement_id,
                ),
                array(
                    'mall_id'                  => 'required|orbit.empty.mall',
                    'lucky_draw_id'            => 'required|orbit.empty.lucky_draw',
                    'lucky_draw_announcement_id'    => 'required|orbit.empty.lucky_draw_announcement',
                )
            );

            Event::fire('orbit.luckydraw.postblastluckydrawannouncement.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            Event::fire('orbit.luckydraw.postblastluckydrawannouncement.after.validation', array($this, $validator));

            $mall = Mall::with('timezone')->excludeDeleted()->where('merchant_id', $mall_id)->first();

            $lucky_draw = App::make('orbit.empty.lucky_draw');

            if (Carbon::now($mall->timezone->timezone_name) < $lucky_draw->draw_date) {
                $errorMessage = "Cannot blast the winner. This lucky draw's draw date is not reached yet.";
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            if (Carbon::now($mall->timezone->timezone_name) > $lucky_draw->grace_period_date) {
                $errorMessage = "Cannot blast the winner. This lucky draw has ended.";
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $lucky_draw_announcement = LuckyDrawAnnouncement::with('luckyDraw.prizes.winners.number.user')
                ->excludeDeleted()
                ->where('lucky_draw_id', $lucky_draw_id)
                ->where('lucky_draw_announcement_id', $lucky_announcement_id)
                ->first();

            $lucky_draw_announcement->blasted_at = Carbon::now($mall->timezone->timezone_name);
            $lucky_draw_announcement->save();

            $this->response->data = $lucky_draw_announcement;

            // create notification
            foreach ($lucky_draw_announcement->luckyDraw->prizes as $prize) {
                foreach ($prize->winners as $winner) {
                    $user_id = $winner->number->user->user_id;
                    $inbox = new Inbox();
                    $inbox->addToInbox($user_id, $lucky_draw_announcement, $mall_id, 'lucky_draw_blast');
                }
            }

            $lucky_draw->touch();

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activityNotes = sprintf('Lucky Draw Winner Announcement Blast: %s', $lucky_draw_announcement->lucky_draw_name);
            $activity->setUser($user)
                    ->setActivityName('blast_lucky_draw_announcement')
                    ->setActivityNameLong('Blast Lucky Draw Winner Announcement')
                    ->setObject($lucky_draw_announcement)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.luckydraw.postblastluckydrawannouncement.after.commit', array($this, $lucky_draw_announcement  ));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.luckydraw.postblastluckydrawannouncement.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('blast_lucky_draw_announcement')
                    ->setActivityNameLong('Blast Lucky Draw Winner Announcement')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.luckydraw.postblastluckydrawannouncement.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('blast_lucky_draw_announcement')
                    ->setActivityNameLong('Blast Lucky Draw Winner Announcement')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.luckydraw.postblastluckydrawannouncement.query.error', array($this, $e));

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
                    ->setActivityName('blast_lucky_draw_announcement')
                    ->setActivityNameLong('Blast Lucky Draw Winner Announcement')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.luckydraw.postblastluckydrawannouncement.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = [$e->getLine(), $e->getFile()];

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('blast_lucky_draw_announcement')
                    ->setActivityNameLong('Blast Lucky Draw Winner Announcement')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save the activity
        $activity->save();
        return $this->render($httpCode);
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

        // Check the existance of lucky_draw id
        Validator::extend('orbit.empty.lucky_draw_announcement', function ($attribute, $value, $parameters) {
            $lucky_draw_announcement = LuckyDrawAnnouncement::excludeDeleted()
                                   ->where('lucky_draw_announcement_id', $value)
                                   ->first();

            if (empty($lucky_draw_announcement)) {
                return FALSE;
            }

            App::instance('orbit.empty.lucky_draw_announcement', $lucky_draw_announcement);

            return TRUE;
        });

        // Check the existance of lucky_draw_prize id
        Validator::extend('orbit.empty.lucky_draw_prize', function ($attribute, $value, $parameters) {
            $lucky_draw_prize = LuckyDrawPrize::excludeDeleted()
                                   ->where('lucky_draw_prize_id', $value)
                                   ->first();

            if (empty($lucky_draw_prize)) {
                return FALSE;
            }

            App::instance('orbit.empty.lucky_draw_prize', $lucky_draw_prize);

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

        // Check lucky draw name, it should not exists
        Validator::extend('orbit.exists.lucky_draw_name', function ($attribute, $value, $parameters) {
            $lucky_draw = LuckyDraw::excludeDeleted()
                                   ->where('mall_id', $parameters[0])
                                   ->where('lucky_draw_name', $value)
                                   ->first();

            if (! empty($lucky_draw)) {
                return FALSE;
            }

            App::instance('orbit.validation.lucky_draw_name', $lucky_draw);

            return TRUE;
        });

        // Check lucky draw prize name, it should not exists
        Validator::extend('orbit.exists.lucky_draw_prize_name', function ($attribute, $value, $parameters) {
            $lucky_draw_prize = LuckyDrawPrize::excludeDeleted()
                                   ->where('lucky_draw_id', $parameters[0])
                                   ->where('prize_name', $value)
                                   ->first();

            if (! empty($lucky_draw_prize)) {
                return FALSE;
            }

            App::instance('orbit.validation.lucky_draw_prize_name', $lucky_draw_prize);

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

        // Check lucky draw name, it should not exists (for update)
        Validator::extend('lucky_draw_prize_name_exists_but_me', function ($attribute, $value, $parameters) {
            $lucky_draw_prize_id = $parameters[0];
            $lucky_draw_id = $parameters[1];
            $lucky_draw_prize = LuckyDrawPrize::excludeDeleted()
                                   ->where('prize_name', $value)
                                   ->where('lucky_draw_id', $lucky_draw_id)
                                   ->where('lucky_draw_prize_id', '!=', $lucky_draw_prize_id)
                                   ->first();

            if (! empty($lucky_draw_prize)) {
                return FALSE;
            }

            App::instance('orbit.validation.lucky_draw_prize_name', $lucky_draw_prize);

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

        // Check draw date should be greater than end date and current date
        Validator::extend('draw_date_greater_than_end_date_and_current_date', function ($attribute, $value, $parameters) {
            $end_date = strtotime($parameters[0]);
            $draw_date = strtotime($value);
            $current_date = strtotime($parameters[1]);

            if (($draw_date > $end_date) && ($draw_date > $current_date)) {
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
     * @param LuckyDraw $luckydraw
     * @param string $translations_json_string
     * @param string $scenario 'create' / 'update'
     * @throws InvalidArgsException
     */
    private function validateAndSaveTranslations($lucky_draw, $translations_json_string, $scenario = 'create')
    {
        /*
         * JSON structure: object with keys = merchant_language_id and values = ProductTranslation object or null
         *
         * Having a value of null means deleting the translation
         *
         * where LuckyDraw object is object with keys:
         *   promotion_name, description, long_description
         *
         * No requirement for including fields. If field not included it means not updated. If field included with
         * value null it means set to null (use main language content instead).
         */

        $valid_fields = ['lucky_draw_name', 'description'];
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
            $existing_translation = LuckyDrawTranslation::excludeDeleted()
                ->where('lucky_draw_id', '=', $lucky_draw->lucky_draw_id)
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
                    if (! empty(trim($translations->lucky_draw_name))) {
                        $lucky_draw_translation = LuckyDrawTranslation::excludeDeleted()
                                                    ->where('merchant_language_id', '=', $merchant_language_id)
                                                    ->where('lucky_draw_name', '=', $translations->lucky_draw_name)
                                                    ->first();
                        if (! empty($lucky_draw_translation)) {
                            OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.exists.lucky_draw_name'));
                        }
                    }
                    $operations[] = ['create', $merchant_language_id, $translations];
                } else {
                    if (! empty(trim($translations->lucky_draw_name))) {
                        $lucky_draw_translation_but_not_me = LuckyDrawTranslation::excludeDeleted()
                                                    ->where('merchant_language_id', '=', $merchant_language_id)
                                                    ->where('lucky_draw_id', '!=', $lucky_draw->lucky_draw_id)
                                                    ->where('lucky_draw_name', '=', $translations->lucky_draw_name)
                                                    ->first();
                        if (! empty($lucky_draw_translation_but_not_me)) {
                            OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.exists.lucky_draw_name'));
                        }
                    }
                    $operations[] = ['update', $existing_translation, $translations];
                }
            }
        }

        foreach ($operations as $operation) {
            $op = $operation[0];
            if ($op === 'create') {
                $new_translation = new LuckyDrawTranslation();
                $new_translation->lucky_draw_id = $lucky_draw->lucky_draw_id;
                $new_translation->merchant_language_id = $operation[1];
                $data = $operation[2];
                foreach ($data as $field => $value) {
                    $new_translation->{$field} = $value;
                }
                $new_translation->status = 'active';
                $new_translation->created_by = $this->api->user->user_id;
                $new_translation->modified_by = $this->api->user->user_id;
                $new_translation->save();

                // Fire an event which listen on orbit.lucky_draw.after.translation.save
                // @param ControllerAPI $this
                // @param EventTranslation $new_transalation
                Event::fire('orbit.luckydraw.after.translation.save', array($this, $new_translation));

                $lucky_draw->setRelation('translation_' . $new_translation->merchant_language_id, $new_translation);
            }
            elseif ($op === 'update') {
                /** @var LuckyDrawTranslation $existing_translation */
                $existing_translation = $operation[1];
                $data = $operation[2];
                foreach ($data as $field => $value) {
                    $existing_translation->{$field} = $value;
                }
                $existing_translation->status = $lucky_draw->status;
                $existing_translation->modified_by = $this->api->user->user_id;
                $existing_translation->save();

                // Fire an event which listen on orbit.lucky_draw.after.translation.save
                // @param ControllerAPI $this
                // @param LuckyDrawTranslation $new_transalation
                Event::fire('orbit.luckydraw.after.translation.save', array($this, $existing_translation));

                // return respones if any upload image or no
                $existing_translation->load('media');

                $lucky_draw->setRelation('translation_' . $existing_translation->merchant_language_id, $existing_translation);
            }
            elseif ($op === 'delete') {
                /** @var LuckyDrawTranslation $existing_translation */
                $existing_translation = $operation[1];
                $existing_translation->modified_by = $this->api->user->user_id;
                $existing_translation->delete();
            }
        }
    }

    /**
     * @param LuckyDrawAnnouncement $luckydrawannouncement
     * @param string $translations_json_string
     * @param string $scenario 'create' / 'update'
     * @throws InvalidArgsException
     */
    private function validateAndSaveAnnouncementTranslations($lucky_draw_announcement, $translations_json_string, $scenario = 'create')
    {
        /*
         * JSON structure: object with keys = merchant_language_id and values = LuckyDrawAnnouncementTranslation object or null
         *
         * Having a value of null means deleting the translation
         *
         * where LuckyDrawAnnouncement object is object with keys:
         *   title, description
         *
         * No requirement for including fields. If field not included it means not updated. If field included with
         * value null it means set to null (use main language content instead).
         */

        $valid_fields = ['title', 'description'];
        $user = $this->api->user;
        $operations = [];

        $data = @json_decode($translations_json_string);

        if (json_last_error() != JSON_ERROR_NONE) {
            OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.jsonerror.field.format', ['field' => 'announcement_translations']));
        }
        foreach ($data as $merchant_language_id => $translations) {
            $language = MerchantLanguage::excludeDeleted()
                ->where('language_id', '=', $merchant_language_id)
                ->first();
            if (empty($language)) {
                OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.empty.merchant_language'));
            }
            $existing_translation = LuckyDrawAnnouncementTranslation::excludeDeleted()
                ->where('lucky_draw_announcement_id', '=', $lucky_draw_announcement->lucky_draw_announcement_id)
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
                $new_translation = new LuckyDrawAnnouncementTranslation();
                $new_translation->lucky_draw_announcement_id = $lucky_draw_announcement->lucky_draw_announcement_id;
                $new_translation->merchant_language_id = $operation[1];
                $data = $operation[2];
                foreach ($data as $field => $value) {
                    $new_translation->{$field} = $value;
                }
                $new_translation->status = $lucky_draw_announcement->status;
                $new_translation->created_by = $this->api->user->user_id;
                $new_translation->modified_by = $this->api->user->user_id;
                $new_translation->save();

                // Fire an event which listen on orbit.lucky_draw_announcement.after.translation.save
                // @param ControllerAPI $this
                // @param EventTranslation $new_transalation
                Event::fire('orbit.luckydraw.after.announcement.translation.save', array($this, $new_translation));

                $lucky_draw_announcement->setRelation('translation_' . $new_translation->merchant_language_id, $new_translation);
            }
            elseif ($op === 'update') {
                /** @var LuckyDrawTranslation $existing_translation */
                $existing_translation = $operation[1];
                $data = $operation[2];
                foreach ($data as $field => $value) {
                    $existing_translation->{$field} = $value;
                }
                $existing_translation->status = $lucky_draw_announcement->status;
                $existing_translation->modified_by = $this->api->user->user_id;
                $existing_translation->save();

                // Fire an event which listen on orbit.lucky_draw_announcement.after.translation.save
                // @param ControllerAPI $this
                // @param LuckyDrawTranslation $new_transalation
                Event::fire('orbit.luckydraw.after.announcement.translation.save', array($this, $existing_translation));

                // return respones if any upload image or no
                $existing_translation->load('media');

                $lucky_draw_announcement->setRelation('translation_' . $existing_translation->merchant_language_id, $existing_translation);
            }
            elseif ($op === 'delete') {
                /** @var LuckyDrawTranslation $existing_translation */
                $existing_translation = $operation[1];
                $existing_translation->modified_by = $this->api->user->user_id;
                $existing_translation->delete();
            }
        }
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
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

    public function setReturnBuilder($bool)
    {
        $this->returnBuilder = $bool;

        return $this;
    }
}
