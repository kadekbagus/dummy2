<?php
/**
 * An API controller for managing Advert.
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
use \Queue;

class AdvertAPIController extends ControllerAPI
{
    /**
     * Flag to return the query builder.
     *
     * @var Builder
     */
    protected $returnBuilder = FALSE;

    protected $viewRoles = ['super admin', 'mall admin', 'mall owner', 'campaign owner', 'campaign employee', 'campaign admin'];
    protected $modifiyRoles = ['super admin', 'mall admin', 'mall owner', 'campaign owner', 'campaign employee'];

    /**
     * POST - Create New Advert
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param char      `link_object_id`        (optional) - Object type. Valid value: promotion, advert.
     * @param char      `advert_link_id`        (required) - Advert link to
     * @param string    `advert_placement_id`   (required) - Status. Valid value: active, inactive, pending, blocked, deleted.
     * @param string    `advert_name`           (optional) - name of advert
     * @param string    `link_url`              (optional) - Can be empty
     * @param datetime  `start_date`            (optional) - Start date
     * @param datetime  `end_date`              (optional) - End date
     * @param string    `notes`                 (optional) - Description
     * @param string    `status`                (optional) - active, inactive, deleted
     * @param array     `locations`             (optional) - Location of multiple mall or gtm
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postNewAdvert()
    {
        $activity = Activity::portal()
                            ->setActivityType('create');

        $user = NULL;
        $newadvert = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.advert.postnewadvert.before.auth', array($this));

            $this->checkAuth();

            Event::fire('orbit.advert.postnewadvert.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.advert.postnewadvert.before.authz', array($this, $user));

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->modifiyRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.advert.postnewadvert.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $link_object_id = OrbitInput::post('link_object_id');
            $advert_link_type_id = OrbitInput::post('advert_link_type_id');
            $advert_placement_id = OrbitInput::post('advert_placement_id');
            $advert_name = OrbitInput::post('advert_name');
            $link_url = OrbitInput::post('link_url');
            $start_date = OrbitInput::post('start_date');
            $end_date = OrbitInput::post('end_date');
            $notes = OrbitInput::post('notes');
            $status = OrbitInput::post('status');
            $locations = OrbitInput::post('locations');
            $locations = (array) $locations;

            $validator = Validator::make(
                array(
                    'link_object_id'      => $link_object_id,
                    'advert_link_type_id' => $advert_link_type_id,
                    'advert_placement_id' => $advert_placement_id,
                    'advert_name'         => $advert_name,
                    'start_date'          => $start_date,
                    'end_date'            => $end_date,
                    'status'              => $status
                ),
                array(
                    'link_object_id'      => 'required|max:255',
                    'advert_link_type_id' => 'required|orbit.empty.advert_link_type_id',
                    'advert_placement_id' => 'required|orbit.empty.advert_placement_id',
                    'advert_name'         => 'required',
                    'start_date'          => 'required|date|orbit.empty.hour_format',
                    'end_date'            => 'required|date|orbit.empty.hour_format',
                    'status'              => 'required|in:active,inactive'
                )
            );

            Event::fire('orbit.advert.postnewadvert.before.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            Event::fire('orbit.advert.postnewadvert.after.validation', array($this, $validator));

            $newadvert = new Advert();
            $newadvert->link_object_id = $link_object_id;
            $newadvert->advert_link_type_id = $advert_link_type_id;
            $newadvert->advert_placement_id = $advert_placement_id;
            $newadvert->advert_name = $advert_name;
            $newadvert->link_url = $link_url;
            $newadvert->start_date = $start_date;
            $newadvert->end_date = $end_date;
            $newadvert->notes = $notes;
            $newadvert->status = $status;

            Event::fire('orbit.advert.postnewadvert.before.save', array($this, $newadvert));

            $newadvert->save();

            // save advert locations.
            $advertLocations = array();

            $locationType = 'mall'

            foreach ($locations as $location_id) {

                if ($location_id === 'gtm') {
                    $location_id = '0';
                    $locationType = 'gtm';
                }

                $advertLocation = new AdvertLocation();
                $advertLocation->advert_location_id = $tenant_id;
                $advertLocation->advert_id = $newadvert->advert_id;
                $advertLocation->location_id = $location_id;
                $advertLocation->location_type = $locationType;
                $advertLocation->save();
                $advertLocations[] = $advertLocation;
            }
            $newadvert->tenants = $advertLocations;

            Event::fire('orbit.advert.postnewadvert.after.save', array($this, $newadvert));

            $this->response->data = $newadvert;

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activityNotes = sprintf('Advert Created: %s', $newadvert->advert_name);
            $activity->setUser($user)
                    ->setActivityName('create_advert')
                    ->setActivityNameLong('Create Advert OK')
                    ->setObject($newadvert)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.advert.postnewadvert.after.commit', array($this, $newadvert));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.advert.postnewadvert.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_advert')
                    ->setActivityNameLong('Create Advert Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.advert.postnewadvert.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_advert')
                    ->setActivityNameLong('Create Advert Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.advert.postnewadvert.query.error', array($this, $e));

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
                    ->setActivityName('create_advert')
                    ->setActivityNameLong('Create Advert Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.advert.postnewadvert.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = $e->getLine();

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_advert')
                    ->setActivityNameLong('Create Advert Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save the activity
        $activity->save();

        return $this->render($httpCode);
    }

    /**
     * POST - Update Advert
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `advert_id`             (required) - Advert ID
     * @param string     `link_url`              (optional) - Can be empty
     * @param datetime   `end_date`              (optional) - End date
     * @param string     `notes`                 (optional) - Description
     * @param string     `status`                (optional) - active, inactive, deleted
     * @param array      `locations`             (optional) - Location of multiple mall or gtm
     * @return Illuminate\Support\Facades\Response
     */
    public function postUpdateAdvert()
    {
        $activity = Activity::portal()
                           ->setActivityType('update');

        $user = NULL;
        $updatedadvert = NULL;
        try {
            $httpCode=200;

            Event::fire('orbit.advert.postupdateadvert.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.advert.postupdateadvert.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.advert.postupdateadvert.before.authz', array($this, $user));

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->modifiyRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.advert.postupdateadvert.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $advert_id = OrbitInput::post('advert_id');
            $end_date = OrbitInput::post('end_date');
            $notes = OrbitInput::post('notes');
            $status = OrbitInput::post('status');
            $locations = OrbitInput::post('locations');
            $locations = (array) $locations;

            $validator = Validator::make(
                array(
                    'advert_id' => $advert_id,
                    'end_date'  => $mall_id,
                    'status'    => $status,
                ),
                array(
                    'advert_id' => 'required|orbit.empty.advert_id',
                    'end_date'  => 'date||orbit.empty.hour_format',
                    'status'    => 'required|in:active,inactive'
                )
            );

            Event::fire('orbit.advert.postupdateadvert.before.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.advert.postupdateadvert.after.validation', array($this, $validator));

            $prefix = DB::getTablePrefix();

            $updatedadvert = Advert::with('tenants')->excludeDeleted()->where('advert_id', $advert_id)->first();

            OrbitInput::post('notes', function($notes) use ($updatedadvert) {
                $updatedadvert->notes = $notes;
            });

            OrbitInput::post('end_date', function($end_date) use ($updatedadvert) {
                $updatedadvert->end_date = $end_date;
            });

            $updatedadvert->modified_by = $this->api->user->user_id;
            $updatedadvert->touch();


            OrbitInput::post('locations', function($locations) use ($updatedadvert, $advert_id) {
                // Delete old data
                $delete_retailer = AdvertLocation::where('advert_id', '=', $advert_id);
                $delete_retailer->delete();

                // Insert new data
                $advertLocations = array();

                $locationType = 'mall'

                foreach ($locations as $location_id) {

                    if ($location_id === 'gtm') {
                        $location_id = '0';
                        $locationType = 'gtm';
                    }

                    $advertLocation = new AdvertLocation();
                    $advertLocation->advert_location_id = $tenant_id;
                    $advertLocation->advert_id = $newadvert->advert_id;
                    $advertLocation->location_id = $location_id;
                    $advertLocation->location_type = $locationType;
                    $advertLocation->save();
                    $advertLocations[] = $advertLocation;
                }
                $updatedadvert->locations = $advertLocations;
            });


            Event::fire('orbit.advert.postupdateadvert.after.save', array($this, $updatedadvert));
            $this->response->data = $updatedadvert;

            // Commit the changes
            $this->commit();

            // Successfull Update
            $activityNotes = sprintf('Advert updated: %s', $updatedadvert->advert_name);
            $activity->setUser($user)
                    ->setActivityName('update_advert')
                    ->setActivityNameLong('Update Advert OK')
                    ->setObject($updatedadvert)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.advert.postupdateadvert.after.commit', array($this, $updatedadvert, $tempContent->temporary_content_id));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.advert.postupdateadvert.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_advert')
                    ->setActivityNameLong('Update Advert Failed')
                    ->setObject($updatedadvert)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.advert.postupdateadvert.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_advert')
                    ->setActivityNameLong('Update Advert Failed')
                    ->setObject($updatedadvert)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.advert.postupdateadvert.query.error', array($this, $e));

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
                    ->setActivityName('update_advert')
                    ->setActivityNameLong('Update Advert Failed')
                    ->setObject($updatedadvert)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.advert.postupdateadvert.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = [$e->getMessage(), $e->getFile(), $e->getLine()];

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_advert')
                    ->setActivityNameLong('Update Advert Failed')
                    ->setObject($updatedadvert)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save activity
        $activity->save();

        return $this->render($httpCode);

    }


    /**
     * GET - Search Advert
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string   `with`                  (optional) - Valid value: tenants.
     * @param string   `sortby`                (optional) - column order by
     * @param string   `sortmode`              (optional) - asc or desc
     * @param integer  `take`                  (optional) - limit
     * @param integer  `skip`                  (optional) - limit offset
     * @param integer  `advert_id`             (optional) - Advert ID
     * @param integer  `mall_id`               (optional) - Mall ID
     * @param string   `advert_name`           (optional) - Advert name
     * @param string   `advert_name_like`      (optional) - Advert name like
     * @param string   `object_type`           (optional) - Object type. Valid value: promotion, advert.
     * @param string   `description`           (optional) - Description
     * @param string   `description_like`      (optional) - Description like
     * @param datetime `begin_date`            (optional) - Begin date. Example: 2015-04-15 00:00:00
     * @param datetime `end_date`              (optional) - End date. Example: 2015-04-18 23:59:59
     * @param integer  `sticky_order`          (optional) - Sticky order.
     * @param string   `status`                (optional) - Status. Valid value: active, inactive, pending, blocked, deleted.
     * @param string   `link_object_type`      (optional) - Link object type. Valid value: tenant, tenant_category.
     * @param integer  `retailer_id`           (optional) - Retailer IDs
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchAdvert()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.advert.getsearchadvert.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.advert.getsearchadvert.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.advert.getsearchadvert.before.authz', array($this, $user));

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->viewRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.advert.getsearchadvert.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sortby');
            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                ),
                array(
                    'sort_by' => 'in:advert_id,link_object_id,advert_link_id,placement_id,advert_name,link_url,start_date,end_date,notes,status,created_at,updated_at',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.advert_sortby'),
                )
            );

            Event::fire('orbit.advert.getsearchadvert.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.advert.getsearchadvert.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.advert.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.advert.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            $object_type = OrbitInput::get('object_type');

            $filterName = OrbitInput::get('advert_name_like', '');

            // Builder object
            $prefix = DB::getTablePrefix();
            $advert = Advert::allowedForPMPUser($user, $object_type[0])
                        ->select('advert.*', 'advert.advert_id as campaign_id', 'advert.object_type as campaign_type', 'campaign_status.order', 'campaign_price.campaign_price_id', 'advert_translations.advert_name as display_name', DB::raw('media.path as image_path'),
                            DB::raw("COUNT(DISTINCT {$prefix}advert_merchant.advert_merchant_id) as total_location"),
                            DB::raw("(select GROUP_CONCAT(IF({$prefix}merchants.object_type = 'tenant', CONCAT({$prefix}merchants.name,' at ', pm.name), CONCAT('Mall at ',{$prefix}merchants.name) ) separator ', ')
                                from {$prefix}advert_merchant
                                    left join {$prefix}merchants on {$prefix}merchants.merchant_id = {$prefix}advert_merchant.merchant_id
                                    left join {$prefix}merchants pm on {$prefix}merchants.parent_id = pm.merchant_id
                                    where {$prefix}advert_merchant.advert_id = {$prefix}advert.advert_id) as campaign_location_names"),
                            DB::raw("CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired' THEN {$prefix}campaign_status.campaign_status_name ELSE (CASE WHEN {$prefix}advert.end_date < (SELECT CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', ot.timezone_name) FROM {$prefix}merchants om
                                    LEFT JOIN {$prefix}timezones ot on ot.timezone_id = om.timezone_id
                                    WHERE om.merchant_id = {$prefix}advert.mall_id)
                                THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END) END  AS campaign_status"),
                            DB::raw("CASE WHEN {$prefix}campaign_price.base_price is null THEN 0 ELSE {$prefix}campaign_price.base_price END AS base_price, ((CASE WHEN {$prefix}campaign_price.base_price is null THEN 0 ELSE {$prefix}campaign_price.base_price END) * (DATEDIFF({$prefix}advert.end_date, {$prefix}advert.begin_date) + 1) * (SELECT COUNT(nm.advert_merchant_id) FROM {$prefix}advert_merchant as nm WHERE nm.object_type != 'mall' and nm.advert_id = {$prefix}advert.advert_id)) AS estimated"))
                        ->leftJoin('campaign_price', function ($join) use ($object_type) {
                                $join->on('advert.advert_id', '=', 'campaign_price.campaign_id')
                                     ->where('campaign_price.campaign_type', '=', $object_type);
                          })
                        ->leftJoin('advert_merchant', 'advert_merchant.advert_id', '=', 'advert.advert_id')
                        ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'advert.campaign_status_id')
                        ->leftJoin('advert_translations', 'advert_translations.advert_id', '=', 'advert.advert_id')
                        ->leftJoin('languages', 'languages.language_id', '=', 'advert_translations.merchant_language_id')
                        ->leftJoin(DB::raw("( SELECT * FROM {$prefix}media WHERE media_name_long = 'advert_translation_image_resized_default' ) as media"), DB::raw('media.object_id'), '=', 'advert_translations.advert_translation_id')
                        ->excludeDeleted('advert')
                        ->groupBy('advert.advert_id')
                        ;

            // Filter advert by Ids
            OrbitInput::get('advert_id', function($advertIds) use ($advert)
            {
                $advert->whereIn('advert.advert_id', $advertIds);
            });

            // Filter advert by advert name
            OrbitInput::get('advert_name', function($advertname) use ($advert)
            {
                $advert->where('advert.advert_name', '=', $advertname);
            });

            // Filter advert by matching advert name pattern
            OrbitInput::get('advert_name_like', function($advertname) use ($advert)
            {
                $advert->where('advert_translations.advert_name', 'like', "%$advertname%");
            });

            // Filter advert by date
            OrbitInput::get('end_date', function($end_date) use ($advert)
            {
                $advert->where('advert.begin_date', '<=', $end_date);
            });

            // Filter advert by dates
            OrbitInput::get('start_date', function($start_date) use ($advert)
            {
                $advert->where('advert.start_date', '>=', $start_date);
            });

            // Filter advert by status
            OrbitInput::get('status', function($status) use ($advert)
            {
                $advert->where('advert.status', '=', $status);
            });

            // Add new relation based on request
            OrbitInput::get('with', function ($with) use ($advert) {
                $with = (array) $with;
                foreach ($with as $relation) {
                    if ($relation === 'locations') {
                        $advert->with('locations');
                    }
                }
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_advert = clone $advert;

            if (! $this->returnBuilder) {
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
                $advert->take($take);

                $skip = 0;
                OrbitInput::get('skip', function($_skip) use (&$skip, $advert)
                {
                    if ($_skip < 0) {
                        $_skip = 0;
                    }

                    $skip = $_skip;
                });
                $advert->skip($skip);
            }

            // Default sort by
            $sortBy = 'campaign_status';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'registered_date' => 'advert.created_at',
                    'advert_name'     => 'advert_translations.advert_name',
                    'object_type'     => 'advert.object_type',
                    'total_location'  => 'total_location',
                    'description'     => 'advert.description',
                    'begin_date'      => 'advert.begin_date',
                    'end_date'        => 'advert.end_date',
                    'updated_at'      => 'advert.updated_at',
                    'status'          => 'campaign_status'
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });
            $advert->orderBy($sortBy, $sortMode);

            //with name
            if ($sortBy !== 'advert_translations.advert_name') {
                $advert->orderBy('advert_translations.advert_name', 'asc');
            }

            // Return the instance of Query Builder
            if ($this->returnBuilder) {
                return ['builder' => $advert, 'count' => RecordCounter::create($_advert)->count()];
            }

            $totalAdvert = RecordCounter::create($_advert)->count();
            $listOfAdvert = $advert->get();

            $data = new stdclass();
            $data->total_records = $totalAdvert;
            $data->returned_records = count($listOfAdvert);
            $data->records = $listOfAdvert;

            if ($totalAdvert === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.advert');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.advert.getsearchadvert.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.advert.getsearchadvert.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.advert.getsearchadvert.query.error', array($this, $e));

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
            Event::fire('orbit.advert.getsearchadvert.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.advert.getsearchadvert.before.render', array($this, &$output));

        return $output;
    }


    protected function registerCustomValidation()
    {

        // Validate the time format for over 23 hour
        Validator::extend('orbit.empty.hour_format', function ($attribute, $value, $parameters) {
            // explode the format Y-m-d H:i:s
            $dateTimeExplode = explode(' ', $value);
            // explode the format H:i:s
            $timeExplode = explode(':', $dateTimeExplode[1]);
            // get the Hour format
            if($timeExplode[0] > 23){
                return false;
            }

            return true;
        });

        // Check the existance of advert id
        Validator::extend('orbit.empty.advert', function ($attribute, $value, $parameters) {
            $advert = Advert::where('status', 'active')
                        ->where('advert_id', $value)
                        ->first();

            if (empty($advert)) {
                return false;
            }

            App::instance('orbit.empty.advert', $advert);

            return true;
        });

        // Check the existance of advert link id
        Validator::extend('orbit.empty.advert_link_type_id', function ($attribute, $value, $parameters) {
            $advert = AdvertLinkType::where('status', 'active')
                        ->where('advert_link_type_id', $value)
                        ->first();

            if (empty($advert)) {
                return false;
            }

            App::instance('orbit.empty.advert_link_type_id', $advert);

            return true;
        });

        // Check the existance of advert placement id
        Validator::extend('orbit.empty.advert_placement_id', function ($attribute, $value, $parameters) {
            $advert = AdvertPlacement::where('status', 'active')
                        ->where('advert_placement_id', $value)
                        ->first();

            if (empty($advert)) {
                return false;
            }

            App::instance('orbit.empty.advert_placement_id', $advert);

            return true;
        });

        // Check the existance of advert id for update with permission check
        Validator::extend('orbit.update.advert', function ($attribute, $value, $parameters) {
            $user = $this->api->user;
            $object_type = $parameters[0];

            $advert = Advert::allowedForPMPUser($user, $object_type)->excludeStoppedOrExpired('advert')
                        ->where('advert_id', $value)
                        ->first();

            if (empty($advert)) {
                return false;
            }

            App::instance('orbit.update.advert', $advert);

            return true;
        });

        // Check the existance of mall id
        Validator::extend('orbit.empty.mall', function ($attribute, $value, $parameters) {
            $mall = Mall::excludeDeleted()
                        ->where('merchant_id', $value)
                        ->first();

            if (empty($mall)) {
                return false;
            }

            App::instance('orbit.empty.mall', $mall);

            return true;
        });

        // Check the existence of the advert status
        Validator::extend('orbit.empty.advert_status', function ($attribute, $value, $parameters) {
            $valid = false;
            $statuses = array('active', 'inactive', 'pending', 'blocked', 'deleted');
            foreach ($statuses as $status) {
                if($value === $status) $valid = $valid || true;
            }

            return $valid;
        });

        // Check the existence of the advert object type
        Validator::extend('orbit.empty.advert_object_type', function ($attribute, $value, $parameters) {
            $valid = false;
            $objectTypes = array('promotion', 'advert');
            foreach ($objectTypes as $objectType) {
                if($value === $objectType) $valid = $valid || true;
            }

            return $valid;
        });

        // Check the existence of the link object type
        Validator::extend('orbit.empty.link_object_type', function ($attribute, $value, $parameters) {
            $valid = false;
            $linkobjecttypes = array('tenant', 'tenant_category');
            foreach ($linkobjecttypes as $linkobjecttype) {
                if($value === $linkobjecttype) $valid = $valid || true;
            }

            return $valid;
        });

        // Check the existance of tenant id
        Validator::extend('orbit.empty.tenant', function ($attribute, $value, $parameters) {
            $tenant = Tenant::excludeDeleted()
                        ->where('merchant_id', $value)
                        ->first();

            if (empty($tenant)) {
                return false;
            }

            App::instance('orbit.empty.tenant', $tenant);

            return true;
        });
    }


    protected function getTimezone($current_mall)
    {
        $timezone = Mall::leftJoin('timezones','timezones.timezone_id','=','merchants.timezone_id')
            ->where('merchants.merchant_id','=', $current_mall)
            ->first();

        return $timezone->timezone_name;
    }

    protected function getTimezoneOffset($timezone)
    {
        $dt = new DateTime('now', new DateTimeZone($timezone));

        return $dt->format('P');
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }

    public function setReturnBuilder($bool)
    {
        $this->returnBuilder = $bool;

        return $this;
    }
}
