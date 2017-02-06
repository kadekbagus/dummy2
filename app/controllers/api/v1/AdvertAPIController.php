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
use \Carbon\Carbon as Carbon;
use \Orbit\Helper\Exception\OrbitCustomException;

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
     * @param string    `advert_placement_id`   (required) - Status. Valid value: active, inactive, deleted.
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
            $country_id = OrbitInput::post('country_id', '');
            $is_all_city = OrbitInput::post('is_all_city', 'N');
            $is_all_location = OrbitInput::post('is_all_location', 'N');
            $city = OrbitInput::post('city');
            $city = (array) $city;

            $validator = Validator::make(
                array(
                    'advert_link_type_id' => $advert_link_type_id,
                    'advert_placement_id' => $advert_placement_id,
                    'advert_name'         => $advert_name,
                    'start_date'          => $start_date,
                    'end_date'            => $end_date,
                    'status'              => $status,
                    'country_id'          => $country_id
                ),
                array(
                    'country_id'          => 'orbit.empty.country_id',
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

            // automatically update country and city when top_banner/footer_banner
            // selected and the advert type is store,coupon and promotion
            $prefix = DB::getTablePrefix();
            $placement = AdvertPlacement::select('placement_type')
                            ->where('advert_placement_id', '=', $advert_placement_id)
                            ->first();

            if (!empty($placement)) {
                if (in_array(($placement->placement_type), ['top_banner', 'footer_banner'])) {
                    $link_type = AdvertLinkType::select('advert_type')
                                                ->where('advert_link_type_id', '=', $advert_link_type_id)
                                                ->first();

                    if ($link_type->advert_type == 'store') {
                        $store = Tenant::select('merchants.name', DB::raw('oms.country'), DB::raw('oms.city'))
                                        ->excludeDeleted('merchants')
                                        ->join('adverts', 'adverts.link_object_id', '=', 'merchants.merchant_id')
                                        ->join(DB::raw("(
                                                        select merchant_id, name, country, city
                                                        from {$prefix}merchants
                                                        where status = 'active'
                                                            and object_type = 'mall'
                                                        ) as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                                        ->where('merchants.merchant_id', '=', $link_object_id)
                                        ->first();

                        $_country = MallCountry::select('country_id')->where('country', '=', $store->country)->first();
                        $_city = MallCity::select('mall_city_id')->where('city', '=', $store->city)->first();
                        $country_id = $_country->country_id;
                        $city = array($_city->mall_city_id);
                    }

                    if ($link_type->advert_type == 'coupon') {
                        $coupon = Coupon::select('mall_countries.country_id','mall_cities.mall_city_id')
                                        ->leftJoin('promotion_retailer', 'promotion_retailer.promotion_id', '=', 'promotions.promotion_id')
                                        ->leftJoin(DB::raw("{$prefix}merchants as om"), function($join){
                                             $join->on(DB::raw('om.merchant_id'), '=', 'promotion_retailer.retailer_id');
                                        })
                                        ->leftJoin(DB::raw("{$prefix}merchants as oms"), function($join){
                                             $join->on(DB::raw('oms.merchant_id'), '=', DB::raw('om.parent_id'));
                                        })
                                        ->leftJoin('mall_countries', 'mall_countries.country', '=', DB::raw('oms.country'))
                                        ->leftJoin('mall_cities', 'mall_cities.city', '=', DB::raw('oms.city'))
                                        ->where('promotions.promotion_id', '=', $link_object_id)
                                        ->get();

                        if (!$coupon->isEmpty()) {
                            $country_id = $coupon[0]->country_id;
                            $_city = array();
                            foreach($coupon as $_coupon){
                                $_city[] = $_coupon->mall_city_id;
                            }
                            $city = $_city;
                        }
                    }

                    if ($link_type->advert_type == 'promotion') {
                        $promotion = News::select('mall_countries.country_id','mall_cities.mall_city_id')
                                        ->leftJoin('news_merchant', 'news_merchant.news_id', '=', 'news.news_id')
                                        ->leftJoin(DB::raw("{$prefix}merchants as om"), function($join){
                                             $join->on(DB::raw('om.merchant_id'), '=', 'news_merchant.merchant_id');
                                        })
                                        ->leftJoin(DB::raw("{$prefix}merchants as oms"), function($join){
                                             $join->on(DB::raw('oms.merchant_id'), '=', DB::raw('om.parent_id'));
                                        })
                                        ->leftJoin('mall_countries', 'mall_countries.country', '=', DB::raw('oms.country'))
                                        ->leftJoin('mall_cities', 'mall_cities.city', '=', DB::raw('oms.city'))
                                        ->where('news.news_id', '=', $link_object_id)
                                        ->where('news.object_type', '=', 'promotion')
                                        ->get();

                        if (!$promotion->isEmpty()) {
                            $country_id = $promotion[0]->country_id;
                            $_city = array();
                            foreach($promotion as $promo){
                                $_city[] = $promo->mall_city_id;
                            }
                            $city = $_city;
                        }
                    }
                }
            }

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
            $newadvert->country_id = $country_id;
            $newadvert->is_all_location = $is_all_location;
            $newadvert->is_all_city = $is_all_city;

            Event::fire('orbit.advert.postnewadvert.before.save', array($this, $newadvert));

            $newadvert->save();

            // save advert locations.
            if ($is_all_location == 'N') {
                $advertLocations = array();
                foreach ($locations as $location_id) {
                    $locationType = 'mall';

                    if ($location_id == 'gtm' || $location_id == 'GTM') {
                        $location_id = '0';
                        $locationType = 'gtm';
                    }

                    $advertLocation = new AdvertLocation();
                    $advertLocation->advert_id = $newadvert->advert_id;
                    $advertLocation->location_id = $location_id;
                    $advertLocation->location_type = $locationType;
                    $advertLocation->save();
                    $advertLocations[] = $advertLocation;
                }
                $newadvert->locations = $advertLocations;
            }

            //save to user campaign
            $usercampaign = new UserCampaign();
            $usercampaign->user_id = $user->user_id;
            $usercampaign->campaign_id = $newadvert->advert_id;
            $usercampaign->campaign_type = 'advert';
            $usercampaign->save();

            //save to advert cities
            if ($is_all_city == 'N') {
                $advertCities = array();
                foreach($city as $city_id) {
                    $advertCity = new AdvertCity();
                    $advertCity->advert_id = $newadvert->advert_id;
                    $advertCity->mall_city_id = $city_id;
                    $advertCity->save();
                    $advertCities[] = $advertCity;
                }
                $newadvert->cities = $advertCities;
            }

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
            $country_id = OrbitInput::post('country_id');
            $is_all_city = OrbitInput::post('is_all_city', 'N');
            $is_all_location = OrbitInput::post('is_all_location', 'N');

            $validator = Validator::make(
                array(
                    'advert_id' => $advert_id,
                    'end_date'  => $end_date,
                    'status'    => $status,
                    'country_id'=> $country_id
                ),
                array(
                    'country_id'=> 'orbit.empty.country_id',
                    'advert_id' => 'required|orbit.empty.advert_id',
                    'end_date'  => 'date||orbit.empty.hour_format',
                    'status'    => 'required|in:active,inactive|orbit.empty.advert_updatestatus:' . $advert_id
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

            $updatedadvert = Advert::excludeDeleted()->where('advert_id', $advert_id)->first();

            OrbitInput::post('notes', function($notes) use ($updatedadvert) {
                $updatedadvert->notes = $notes;
            });

            OrbitInput::post('end_date', function($end_date) use ($updatedadvert) {
                $updatedadvert->end_date = $end_date;
            });

            OrbitInput::post('status', function($status) use ($updatedadvert) {
                $updatedadvert->status = $status;
            });

            OrbitInput::post('country_id', function($country_id) use ($updatedadvert) {
                $updatedadvert->country_id = $country_id;
            });

            OrbitInput::post('is_all_location', function($all_location) use ($updatedadvert, $is_all_location, $advert_id) {
                if ($is_all_location == 'Y') {
                    // Delete old data
                    $delete_retailer = AdvertLocation::where('advert_id', '=', $advert_id);
                    $delete_retailer->delete();
                }

                $updatedadvert->is_all_location = $all_location;
            });

            OrbitInput::post('is_all_city', function($all_city) use ($updatedadvert, $is_all_city, $advert_id) {
                if ($is_all_city == 'Y') {
                    // Delete old data
                    $deleteAdvertCity = AdvertCity::where('advert_id', '=', $advert_id);
                    $deleteAdvertCity->delete();
                }

                $updatedadvert->is_all_city = $all_city;
            });

            OrbitInput::post('locations', function($locations) use ($updatedadvert, $advert_id, $is_all_location) {
                if ($is_all_location == 'N') {
                    // Delete old data
                    $delete_retailer = AdvertLocation::where('advert_id', '=', $advert_id);
                    $delete_retailer->delete();

                    // Insert new data
                    $advertLocations = array();

                    foreach ($locations as $location_id) {
                        $locationType = 'mall';
                        if ($location_id == 'gtm' || $location_id == 'GTM') {
                            $location_id = '0';
                            $locationType = 'gtm';
                        }
                        $advertLocation = new AdvertLocation();
                        $advertLocation->advert_id = $advert_id;
                        $advertLocation->location_id = $location_id;
                        $advertLocation->location_type = $locationType;
                        $advertLocation->save();
                        $advertLocations[] = $advertLocation;
                    }

                    $updatedadvert->is_all_location = 'N';
                    $updatedadvert->locations = $advertLocations;
                }
            });

            OrbitInput::post('city', function($city) use ($updatedadvert, $advert_id, $is_all_city) {
                if ($is_all_city == 'N') {
                    $city = (array) $city;
                    // Delete old data
                    $deleteAdvertCity = AdvertCity::where('advert_id', '=', $advert_id);
                    $deleteAdvertCity->delete();

                    // Insert new data
                    $advertCities = array();
                    foreach ($city as $city_id) {
                        $advertCity = new AdvertCity();
                        $advertCity->advert_id = $advert_id;
                        $advertCity->mall_city_id = $city_id;
                        $advertCity->save();
                        $advertCities[] = $advertCity;
                    }

                    $updatedadvert->is_all_city = 'N';
                    $updatedadvert->cities = $advertCities;
                }
            });

            $updatedadvert->touch();

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

            Event::fire('orbit.advert.postupdateadvert.after.commit', array($this, $updatedadvert));
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
        } catch (\Orbit\Helper\Exception\OrbitCustomException $e) {
            Event::fire('orbit.advert.postupdateadvert.query.error', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';

            // Only shows full query error when we are in debug mode
            $this->response->message = $e->getMessage();

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
            $this->response->data = null;

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
                    'sort_by' => 'in:advert_id,advert_link_name,placement_name,advert_name,start_date,end_date,status,created_at,updated_at,total_location,link_to,link_url',
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

            $now = Carbon::now('Asia/Jakarta'); // now with jakarta timezone
            // Builder object
            $prefix = DB::getTablePrefix();
            $advert = Advert::allowedForPMPUser($user, 'advert')
                            ->select('adverts.advert_id',
                                     'adverts.advert_name',
                                     'adverts.link_url',
                                     'adverts.start_date',
                                     'adverts.end_date',
                                     'adverts.notes',
                                     'adverts.advert_placement_id',
                                     'adverts.updated_at',
                                     'adverts.created_at',
                                     'adverts.link_object_id',
                                     'adverts.advert_link_type_id',
                                     'advert_placements.placement_name',
                                     'advert_link_types.advert_link_name',
                                     'adverts.country_id',
                                     'countries.name as country_name',
                                     'adverts.is_all_city',
                                     'adverts.is_all_location',
                                     DB::raw("CASE
                                                WHEN {$prefix}adverts.end_date < {$this->quote($now)} THEN 'inactive'
                                                ELSE {$prefix}adverts.status
                                             END AS 'status'"),
                                     DB::raw("COUNT(DISTINCT {$prefix}advert_locations.location_id) as total_location"),
                                     DB::raw("CASE
                                                WHEN advert_link_name = 'Store' THEN store.name
                                                WHEN advert_link_name = 'Coupon' THEN {$prefix}promotions.promotion_name
                                                WHEN advert_link_name = 'Promotion' THEN {$prefix}news.news_name
                                                ELSE link_url
                                             END AS 'link_to'"),
                                     DB::raw("media.path as image_path"))
                            ->excludeDeleted('adverts')
                            ->join('advert_placements', 'advert_placements.advert_placement_id', '=', 'adverts.advert_placement_id')
                            ->join('advert_link_types', 'advert_link_types.advert_link_type_id', '=', 'adverts.advert_link_type_id')
                            ->leftJoin('advert_locations', 'advert_locations.advert_id', '=', 'adverts.advert_id')
                            ->leftJoin('promotions', 'promotions.promotion_id', '=', 'adverts.link_object_id')
                            ->leftJoin('news', 'news.news_id', '=', 'adverts.link_object_id')
                            ->leftJoin('merchants as store', DB::raw('store.merchant_id'), '=', DB::raw("{$prefix}adverts.link_object_id"))
                            ->leftJoin(DB::raw("( SELECT * FROM {$prefix}media WHERE media_name_long = 'advert_image_resized_default' ) as media"), DB::raw('media.object_id'), '=', 'adverts.advert_id')
                            ->leftJoin('countries', 'countries.country_id', '=', 'adverts.country_id')
                            ->groupBy('adverts.advert_id');

            // Filter advert by Ids
            OrbitInput::get('advert_id', function($advertIds) use ($advert)
            {
                $advert->whereIn('adverts.advert_id', $advertIds);
            });

            // Filter advert by advert name
            OrbitInput::get('advert_name', function($advertname) use ($advert)
            {
                $advert->where('adverts.advert_name', '=', $advertname);
            });

            // Filter advert by matching advert name pattern
            OrbitInput::get('advert_name_like', function($advertname) use ($advert)
            {
                $advert->where('adverts.advert_name', 'like', "%$advertname%");
            });

            // Filter advert by date
            OrbitInput::get('start_date', function($start_date) use ($advert)
            {
                $advert->where('adverts.start_date', '>=', $start_date);
            });

            // Filter advert by end date
            OrbitInput::get('end_date', function($end_date) use ($advert)
            {
                $advert->where('adverts.end_date', '<=', $end_date);
            });

            // Filter advert by status
            OrbitInput::get('status', function($status) use ($advert)
            {
                $advert->where('adverts.status', '=', $status);
            });

            // Add new relation based on request
            OrbitInput::get('with', function ($with) use ($advert) {
                $with = (array) $with;
                foreach ($with as $relation) {
                    if ($relation === 'media') {
                        $advert->with('media');
                    }
                    if ($relation === 'locations') {
                        $advert->with('locations');
                    }
                    if ($relation === 'advertLocations') {
                        $advert->with('advertLocations');
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
            $sortBy = 'status';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'advert_id'       => 'adverts.advert_id',
                    'advert_name'     => 'adverts.advert_name',
                    'total_location'  => 'total_location',
                    'placement_name'  => 'placement_name',
                    'advert_link_name'=> 'advert_link_name',
                    'start_date'      => 'adverts.start_date',
                    'end_date'        => 'adverts.end_date',
                    'created_at'      => 'adverts.created_at',
                    'updated_at'      => 'adverts.updated_at',
                    'status'          => 'status',
                    'link_to'         => 'link_to',
                    'link_url'        => 'adverts.link_url'
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
            if ($sortBy !== 'adverts.advert_name') {
                $advert->orderBy('adverts.advert_name', 'asc');
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
        Validator::extend('orbit.empty.advert_id', function ($attribute, $value, $parameters) {
            $advert = Advert::where('advert_id', $value)
                        ->first();

            if (empty($advert)) {
                return false;
            }

            App::instance('orbit.empty.advert_id', $advert);

            return true;
        });

        // Check the existance of advert id
        Validator::extend('orbit.empty.advert_updatestatus', function ($attribute, $value, $parameters) {

            if ($value === 'active') {

                $advert = Advert::where('advert_id', $parameters[0])
                            ->join('advert_link_types', 'advert_link_types.advert_link_type_id', '=', 'adverts.advert_link_type_id')
                            ->whereIn('advert_link_types.advert_type',['store', 'promotion', 'coupon'])
                            ->first();

                if (is_object($advert)) {
                    if ($advert->advert_type === 'promotion') {
                        $advertCheck = News::where('status', 'active')
                                        ->where('news_id', $advert->link_object_id)
                                        ->first();
                        if (empty($advertCheck)) {
                            $errorMessage = 'Promotion is stopped or paused';
                            throw new OrbitCustomException($errorMessage, Advert::ADVERT_PROMOTION_ERROR_CODE, NULL);
                        }
                    } elseif ($advert->advert_type === 'coupon') {
                        $advertCheck = Coupon::where('status', 'active')
                                        ->where('promotion_id', $advert->link_object_id)
                                        ->first();
                        if (empty($advertCheck)) {
                            $errorMessage = 'Coupon is stopped or paused';
                            throw new OrbitCustomException($errorMessage, Advert::ADVERT_COUPON_ERROR_CODE, NULL);
                        }
                    } elseif ($advert->advert_type === 'store') {
                        $advertCheck = TenantStoreAndService::where('status', 'active')
                                        ->where('merchant_id', $advert->link_object_id)
                                        ->first();
                        if (empty($advertCheck)) {
                            $errorMessage = 'Store is inactive';
                            throw new OrbitCustomException($errorMessage, Advert::ADVERT_STORE_ERROR_CODE, NULL);
                        }
                    }
                }

            }

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

        // Check the existance of country_id
        Validator::extend('orbit.empty.country_id', function ($attribute, $value, $parameters) {
            $mallCountry = MallCountry::where('country_id', $value)
                                 ->first();

            if (empty($mallCountry)) {
                return false;
            }

            App::instance('orbit.empty.country_id', $mallCountry);

            return true;
        });
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
