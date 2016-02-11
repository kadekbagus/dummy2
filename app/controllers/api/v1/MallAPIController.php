<?php
/**
 * An API controller for managing merchants.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Text\Util\LineChecker;
use Helper\EloquentRecordCounter as RecordCounter;

class MallAPIController extends ControllerAPI
{
    /**
     * Flag to return the query builder.
     *
     * @var Builder
     */
    protected $returnBuilder = FALSE;
    protected $withMallGroup = FALSE;


     /**
     * POST - Add new mall
     *
     * @author Kadek <kadek@dominopos.com>
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `user_id`                 (required) - User id for the merchant
     * @param string     `email`                   (required) - Email address of the merchant
     * @param string     `name`                    (optional) - Name of the merchant
     * @param string     `description`             (optional) - Merchant description
     * @param string     `address_line1`           (optional) - Address 1
     * @param string     `address_line2`           (optional) - Address 2
     * @param string     `address_line3`           (optional) - Address 3
     * @param integer    `postal_code`             (optional) - Postal code
     * @param integer    `city_id`                 (optional) - City id
     * @param string     `city`                    (optional) - Name of the city
     * @param integer    `country_id`              (optional) - Country id
     * @param string     `country`                 (optional) - Name of the country
     * @param string     `phone`                   (optional) - Phone of the merchant
     * @param string     `fax`                     (optional) - Fax of the merchant
     * @param string     `start_date_activity`     (optional) - Start date activity of the merchant
     * @param string     `end_date_activity`       (optional) - End date activity of the merchant
     * @param string     `status`                  (optional) - Status of the merchant
     * @param string     `logo`                    (optional) - Logo of the merchant
     * @param string     `currency`                (optional) - Currency used by the merchant
     * @param string     `currency_symbol`         (optional) - Currency symbol
     * @param string     `tax_code1`               (optional) - Tax code 1
     * @param string     `tax_code2`               (optional) - Tax code 2
     * @param string     `tax_code3`               (optional) - Tax code 3
     * @param string     `slogan`                  (optional) - Slogan for the merchant
     * @param string     `vat_included`            (optional) - Vat included
     * @param string     `contact_person_firstname`(optional) - Contact person first name
     * @param string     `contact_person_lastname` (optional) - Contact person last name
     * @param string     `contact_person_position` (optional) - Contact person position
     * @param string     `contact_person_phone`    (optional) - Contact person phone
     * @param string     `contact_person_phone2`   (optional) - Contact person second phone
     * @param string     `contact_person_email`    (optional) - Contact person email
     * @param string     `sector_of_activity`      (optional) - Sector of activity
     * @param string     `url`                     (optional) - Url
     * @param string     `masterbox_number`        (optional) - Masterbox number
     * @param string     `slavebox_number`         (optional) - Slavebox number
     * @param string     `mobile_default_language` (optional) - Mobile default language
     * @param string     `pos_language`            (optional) - POS language
     * @param file       `images`                  (optional) - Merchant logo
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postNewMall()
    {
        $activity = Activity::portal()
                            ->setActivityType('create');

        $user = NULL;
        $newmall = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.mall.postnewmall.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.mall.postnewmall.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.mall.postnewmall.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('create_mall')) {
                Event::fire('orbit.mall.postnewmall.authz.notallowed', array($this, $user));
                $createMallLang = Lang::get('validation.orbit.actionlist.new_mall');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $createMallLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.mall.postnewmall.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $email = OrbitInput::post('email');
            $name = OrbitInput::post('name');
            $password = OrbitInput::post('password');
            $description = OrbitInput::post('description');
            $address_line1 = OrbitInput::post('address_line1');
            $address_line2 = OrbitInput::post('address_line2');
            $address_line3 = OrbitInput::post('address_line3');
            $postal_code = OrbitInput::post('postal_code');
            $city_id = OrbitInput::post('city_id');
            $city = OrbitInput::post('city');
            $country_id = OrbitInput::post('country_id');
            $country = OrbitInput::post('country');
            $phone = OrbitInput::post('phone');
            $fax = OrbitInput::post('fax');
            $start_date_activity = OrbitInput::post('start_date_activity');
            $end_date_activity = OrbitInput::post('end_date_activity');
            $status = OrbitInput::post('status');
            $logo = OrbitInput::post('logo');
            $currency = OrbitInput::post('currency');
            $currency_symbol = OrbitInput::post('currency_symbol');
            $tax_code1 = OrbitInput::post('tax_code1');
            $tax_code2 = OrbitInput::post('tax_code2');
            $tax_code3 = OrbitInput::post('tax_code3');
            $slogan = OrbitInput::post('slogan');
            $vat_included = OrbitInput::post('vat_included');
            $contact_person_firstname = OrbitInput::post('contact_person_firstname');
            $contact_person_lastname = OrbitInput::post('contact_person_lastname');
            $contact_person_position = OrbitInput::post('contact_person_position');
            $contact_person_phone = OrbitInput::post('contact_person_phone');
            $contact_person_phone2 = OrbitInput::post('contact_person_phone2');
            $contact_person_email = OrbitInput::post('contact_person_email');
            $sector_of_activity = OrbitInput::post('sector_of_activity');
            $object_type = OrbitInput::post('object_type');
            $parent_id = OrbitInput::post('parent_id');
            $url = OrbitInput::post('url');
            $masterbox_number = OrbitInput::post('masterbox_number');
            $slavebox_number = OrbitInput::post('slavebox_number');
            $mobile_default_language = OrbitInput::post('mobile_default_language');
            $pos_language = OrbitInput::post('pos_language');

            $validator = Validator::make(
                array(
                    'email'         => $email,
                    'name'          => $name,
                    'status'        => $status,
                    'country'       => $country,
                    'url'           => $url,
                ),
                array(
                    'email'         => 'required|email|orbit.exists.email',
                    'name'          => 'required',
                    'status'        => 'required|orbit.empty.mall_status',
                    'country'       => 'required|numeric',
                    'url'           => 'orbit.formaterror.url.web'
                )
            );

            Event::fire('orbit.mall.postnewmall.before.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.mall.postnewmall.after.validation', array($this, $validator));

            $roleMerchant = Role::where('role_name', 'mall owner')->first();
            if (empty($roleMerchant)) {
                OrbitShopAPI::throwInvalidArgument('Could not find role named "Mall Owner".');
            }

            $newuser = new User();
            $newuser->username = $email;
            $newuser->user_email = $email;
            // lock the password unless specified
            $newuser->user_password = '!';
            OrbitInput::post('password', function ($password) use ($newuser) {
                $newuser->user_password = Hash::make($password);
            });
            $newuser->status = $status;
            $newuser->user_role_id = $roleMerchant->role_id;
            $newuser->user_ip = $_SERVER['REMOTE_ADDR'];
            $newuser->modified_by = $user->user_id;
            $newuser->save();

            $newuser->createAPiKey();

            $userdetail = new UserDetail();
            $userdetail = $newuser->userdetail()->save($userdetail);

            $countryName = '';
            $countryObject = Country::find($country);
            if (is_object($countryObject)) {
                $countryName = $countryObject->name;
            }

            $newmall = new Mall();
            $newmall->user_id = $newuser->user_id;
            $newmall->omid = '';
            $newmall->email = $email;
            $newmall->name = $name;
            $newmall->description = $description;
            $newmall->address_line1 = $address_line1;
            $newmall->address_line2 = $address_line2;
            $newmall->address_line3 = $address_line3;
            $newmall->postal_code = $postal_code;
            $newmall->city_id = $city_id;
            $newmall->city = $city;
            $newmall->country_id = $country;
            $newmall->country = $countryName;
            $newmall->phone = $phone;
            $newmall->fax = $fax;
            $newmall->start_date_activity = $start_date_activity;
            $newmall->end_date_activity = $end_date_activity;
            $newmall->status = $status;
            $newmall->logo = $logo;
            $newmall->currency = $currency;
            $newmall->currency_symbol = $currency_symbol;
            $newmall->tax_code1 = $tax_code1;
            $newmall->tax_code2 = $tax_code2;
            $newmall->tax_code3 = $tax_code3;
            $newmall->slogan = $slogan;
            $newmall->vat_included = $vat_included;
            $newmall->contact_person_firstname = $contact_person_firstname;
            $newmall->contact_person_lastname = $contact_person_lastname;
            $newmall->contact_person_position = $contact_person_position;
            $newmall->contact_person_phone = $contact_person_phone;
            $newmall->contact_person_phone2 = $contact_person_phone2;
            $newmall->contact_person_email = $contact_person_email;
            $newmall->sector_of_activity = $sector_of_activity;
            $newmall->object_type = $object_type;
            $newmall->parent_id = $parent_id;
            $newmall->url = $url;
            $newmall->masterbox_number = $masterbox_number;
            $newmall->slavebox_number = $slavebox_number;
            $newmall->mobile_default_language = $mobile_default_language;
            $newmall->pos_language = $pos_language;
            $newmall->modified_by = $this->api->user->user_id;

            Event::fire('orbit.mall.postnewmall.before.save', array($this, $newmall));

            $newmall->save();

            // add orid to newly created mall (mall have orid and mall group have omid)
            $newmall->orid = Mall::ORID_INCREMENT + $newmall->merchant_id;
            $newmall->save();

            Event::fire('orbit.mall.postnewmall.after.save', array($this, $newmall));
            $this->response->data = $newmall;

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activityNotes = sprintf('Mall Created: %s', $newmall->name);
            $activity->setUser($user)
                    ->setActivityName('create_mall')
                    ->setActivityNameLong('Create Mall OK')
                    ->setObject($newmall)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.mall.postnewmall.after.commit', array($this, $newmall));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.mall.postnewmall.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_mall')
                    ->setActivityNameLong('Create Mall Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.mall.postnewmall.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_mall')
                    ->setActivityNameLong('Create Mall Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.mall.postnewmall.query.error', array($this, $e));

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
                    ->setActivityName('create_mall')
                    ->setActivityNameLong('Create Mall Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.mall.postnewmall.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_mall')
                    ->setActivityNameLong('Create Mall Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save the activity
        $activity->save();

        return $this->render($httpCode);
    }

    /**
     * GET - Search mall
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @author Rio Astamal <me@rioastamal.net>
     * @author kadek <kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string            `sort_by`                       (optional) - column order by
     * @param string            `sort_mode`                     (optional) - asc or desc
     * @param integer           `take`                          (optional) - limit
     * @param integer           `skip`                          (optional) - limit offset
     * @param integer           `merchant_id`                   (optional)
     * @param string            `orid`                          (optional)
     * @param integer           `user_id`                       (optional)
     * @param string            `email`                         (optional)
     * @param string            `name`                          (optional)
     * @param string            `description`                   (optional)
     * @param string            `address1`                      (optional)
     * @param string            `address2`                      (optional)
     * @param string            `address3`                      (optional)
     * @param integer           `postal_code`                   (optional) - Postal code
     * @param string            `city_id`                       (optional)
     * @param string            `city`                          (optional)
     * @param string            `country_id`                    (optional)
     * @param string            `country`                       (optional)
     * @param string            `phone`                         (optional)
     * @param string            `fax`                           (optional)
     * @param string            `status`                        (optional)
     * @param string            `currency`                      (optional)
     * @param string            `name_like`                     (optional)
     * @param string            `email_like`                    (optional)
     * @param string            `description_like`              (optional)
     * @param string            `address1_like`                 (optional)
     * @param string            `address2_like`                 (optional)
     * @param string            `address3_like`                 (optional)
     * @param string            `city_like`                     (optional)
     * @param string            `country_like`                  (optional)
     * @param string            `contact_person_firstname`      (optional) - Contact person firstname
     * @param string            `contact_person_firstname_like` (optional) - Contact person firstname like
     * @param string            `contact_person_lastname`       (optional) - Contact person lastname
     * @param string            `contact_person_lastname_like`  (optional) - Contact person lastname like
     * @param string            `contact_person_position`       (optional) - Contact person position
     * @param string            `contact_person_position_like`  (optional) - Contact person position like
     * @param string            `contact_person_phone`          (optional) - Contact person phone
     * @param string            `contact_person_phone2`         (optional) - Contact person phone2
     * @param string            `contact_person_email`          (optional) - Contact person email
     * @param string            `url`                           (optional) - Url
     * @param string            `masterbox_number`              (optional) - Masterbox number
     * @param string            `slavebox_number`               (optional) - Slavebox number
     * @param string            `mobile_default_language`       (optional) - Mobile default language
     * @param string            `pos_language`                  (optional) - POS language
     * @param string|array      `with`                          (optional) - Relation which need to be included
     * @param string|array      `with_count`                    (optional) - Also include the "count" relation or not, should be used in conjunction with `with`
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchMall()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.mall.getsearchmall.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.mall.getsearchmall.after.auth', array($this));

            // Try to check access control list, does this mall allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.mall.getsearchmall.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('view_mall')) {
                Event::fire('orbit.mall.getsearchmall.authz.notallowed', array($this, $user));
                $viewUserLang = Lang::get('validation.orbit.actionlist.view_mall');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewUserLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.mall.getsearchmall.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sortby');
            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                ),
                array(
                    'sort_by' => 'in:merchant_orid,registered_date,merchant_name,merchant_email,merchant_userid,merchant_description,merchantid,merchant_address1,merchant_address2,merchant_address3,merchant_cityid,merchant_city,merchant_countryid,merchant_country,merchant_phone,merchant_fax,merchant_status,merchant_currency,start_date_activity,total_retailer',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.merchant_sortby'),
                )
            );

            Event::fire('orbit.mall.getsearchmall.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            Event::fire('orbit.mall.getsearchmall.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.mall.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.mall.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            $prefix = DB::getTablePrefix();

            $malls = Mall::excludeDeleted('merchants')
                                ->select('merchants.*', DB::raw('count(tenant.merchant_id) AS total_tenant'))
                                ->leftJoin('merchants AS tenant', function($join) {
                                        $join->on(DB::raw('tenant.parent_id'), '=', 'merchants.merchant_id')
                                            ->where(DB::raw('tenant.status'), '!=', 'deleted')
                                            ->where(DB::raw('tenant.object_type'), '=', 'tenant');
                                    })
                                ->groupBy('merchants.merchant_id');

            // for print and export
            if ($this->withMallGroup) {
                $malls->addSelect(DB::raw('(mallgroup.name) AS mallgroup'))
                      ->leftJoin('merchants AS mallgroup', function($join) {
                        $join->on(DB::raw('mallgroup.merchant_id'), '=', 'merchants.parent_id')
                            ->where(DB::raw('mallgroup.status'), '!=', 'deleted')
                            ->where(DB::raw('mallgroup.object_type'), '=', 'mall_group');
                    });
            }

            // Filter mall by Ids
            OrbitInput::get('merchant_id', function ($merchantIds) use ($malls) {
                $malls->whereIn('merchants.merchant_id', $merchantIds);
            });

            // Filter mall by orid
            OrbitInput::get('orid', function ($orid) use ($malls) {
                $malls->whereIn('merchants.orid', $orid);
            });

            // Filter mall by user Ids
            OrbitInput::get('user_id', function ($userIds) use ($malls) {
                $malls->whereIn('merchants.user_id', $userIds);
            });

            // Filter mall by name
            OrbitInput::get('name', function ($name) use ($malls) {
                $malls->whereIn('merchants.name', $name);
            });

            // Filter mall by name pattern
            OrbitInput::get('name_like', function ($name) use ($malls) {
                $malls->where('merchants.name', 'like', "%$name%");
            });

            // Filter mall by description
            OrbitInput::get('description', function ($description) use ($malls) {
                $malls->whereIn('merchants.description', $description);
            });

            // Filter mall by description pattern
            OrbitInput::get('description_like', function ($description) use ($malls) {
                $malls->where('merchants.description', 'like', "%$description%");
            });

            // Filter mall by email
            OrbitInput::get('email', function ($email) use ($malls) {
                $malls->whereIn('merchants.email', $email);
            });

            // Filter mall by email pattern
            OrbitInput::get('email_like', function ($email) use ($malls) {
                $malls->where('merchants.email', 'like', "%$email%");
            });

            // Filter mall by address1
            OrbitInput::get('address1', function ($address1) use ($malls) {
                $malls->whereIn('merchants.address_line1', $address1);
            });

            // Filter mall by address1 pattern
            OrbitInput::get('address1_like', function ($address1) use ($malls) {
                $malls->where('merchants.address_line1', 'like', "%$address1%");
            });

            // Filter mall by address2
            OrbitInput::get('address2', function ($address2) use ($malls) {
                $malls->whereIn('merchants.address_line2', $address2);
            });

            // Filter mall by address2 pattern
            OrbitInput::get('address2_like', function ($address2) use ($malls) {
                $malls->where('merchants.address_line2', 'like', "%$address2%");
            });

            // Filter mall by address3
            OrbitInput::get('address3', function ($address3) use ($malls) {
                $malls->whereIn('merchants.address_line3', $address3);
            });

            // Filter mall by address3 pattern
            OrbitInput::get('address3_like', function ($address3) use ($malls) {
                $malls->where('merchants.address_line3', 'like', "%$address3%");
            });

            // Filter mall by postal code
            OrbitInput::get('postal_code', function ($postalcode) use ($malls) {
                $malls->whereIn('merchants.postal_code', $postalcode);
            });

            // Filter mall by cityID
            OrbitInput::get('city_id', function ($cityIds) use ($malls) {
                $malls->whereIn('merchants.city_id', $cityIds);
            });

            // Filter mall by city
            OrbitInput::get('city', function ($city) use ($malls) {
                $malls->whereIn('merchants.city', $city);
            });

            // Filter mall by city pattern
            OrbitInput::get('city_like', function ($city) use ($malls) {
                $malls->where('merchants.city', 'like', "%$city%");
            });

            // Filter mall by countryID
            OrbitInput::get('country_id', function ($countryId) use ($malls) {
                $malls->whereIn('merchants.country_id', $countryId);
            });

            // Filter mall by country
            OrbitInput::get('country', function ($country) use ($malls) {
                $malls->whereIn('merchants.country', $country);
            });

            // Filter mall by country pattern
            OrbitInput::get('country_like', function ($country) use ($malls) {
                $malls->where('merchants.country', 'like', "%$country%");
            });

            // Filter mall by phone
            OrbitInput::get('phone', function ($phone) use ($malls) {
                $malls->whereIn('merchants.phone', $phone);
            });

            // Filter mall by fax
            OrbitInput::get('fax', function ($fax) use ($malls) {
                $malls->whereIn('merchants.fax', $fax);
            });

            // Filter mall by status
            OrbitInput::get('status', function ($status) use ($malls) {
                $malls->whereIn('merchants.status', $status);
            });

            // Filter mall by currency
            OrbitInput::get('currency', function ($currency) use ($malls) {
                $malls->whereIn('merchants.currency', $currency);
            });

            // Filter mall by contact person firstname
            OrbitInput::get('contact_person_firstname', function ($contact_person_firstname) use ($malls) {
                $malls->whereIn('merchants.contact_person_firstname', $contact_person_firstname);
            });

            // Filter mall by contact person firstname like
            OrbitInput::get('contact_person_firstname_like', function ($contact_person_firstname) use ($malls) {
                $malls->where('merchants.contact_person_firstname', 'like', "%$contact_person_firstname%");
            });

            // Filter mall by contact person lastname
            OrbitInput::get('contact_person_lastname', function ($contact_person_lastname) use ($malls) {
                $malls->whereIn('merchants.contact_person_lastname', $contact_person_lastname);
            });

            // Filter mall by contact person lastname like
            OrbitInput::get('contact_person_lastname_like', function ($contact_person_lastname) use ($malls) {
                $malls->where('merchants.contact_person_lastname', 'like', "%$contact_person_lastname%");
            });

            // Filter mall by contact person position
            OrbitInput::get('contact_person_position', function ($contact_person_position) use ($malls) {
                $malls->whereIn('merchants.contact_person_position', $contact_person_position);
            });

            // Filter mall by contact person position like
            OrbitInput::get('contact_person_position_like', function ($contact_person_position) use ($malls) {
                $malls->where('merchants.contact_person_position', 'like', "%$contact_person_position%");
            });

            // Filter mall by contact person phone
            OrbitInput::get('contact_person_phone', function ($contact_person_phone) use ($malls) {
                $malls->whereIn('merchants.contact_person_phone', $contact_person_phone);
            });

            // Filter mall by contact person phone2
            OrbitInput::get('contact_person_phone2', function ($contact_person_phone2) use ($malls) {
                $malls->whereIn('merchants.contact_person_phone2', $contact_person_phone2);
            });

            // Filter mall by contact person email
            OrbitInput::get('contact_person_email', function ($contact_person_email) use ($malls) {
                $malls->whereIn('merchants.contact_person_email', $contact_person_email);
            });

            // Filter mall by sector of activity
            OrbitInput::get('sector_of_activity', function ($sector_of_activity) use ($malls) {
                $malls->whereIn('merchants.sector_of_activity', $sector_of_activity);
            });

            // Filter mall by url
            OrbitInput::get('url', function ($url) use ($malls) {
                $malls->whereIn('merchants.url', $url);
            });

            // Filter mall by masterbox_number
            OrbitInput::get('masterbox_number', function ($masterbox_number) use ($malls) {
                $malls->whereIn('merchants.masterbox_number', $masterbox_number);
            });

            // Filter mall by slavebox_number
            OrbitInput::get('slavebox_number', function ($slavebox_number) use ($malls) {
                $malls->whereIn('merchants.slavebox_number', $slavebox_number);
            });

            // Filter mall by mobile_default_language
            OrbitInput::get('mobile_default_language', function ($mobile_default_language) use ($malls) {
                $malls->whereIn('merchants.mobile_default_language', $mobile_default_language);
            });

            // Filter mall by pos_language
            OrbitInput::get('pos_language', function ($pos_language) use ($malls) {
                $malls->whereIn('merchants.pos_language', $pos_language);
            });

            // Filter mall by location (city country)
            OrbitInput::get('location', function($data) use ($malls, $prefix) {
                $malls->where(DB::raw("CONCAT(COALESCE({$prefix}merchants.city, ''), ' ', COALESCE({$prefix}merchants.country, ''))"), 'like', "%$data%");
            });

            // Add new relation based on request
            OrbitInput::get('with', function ($with) use ($malls) {
                $with = (array) $with;

                if (in_array('settings', $with)) {
                    $malls->addSelect('media.path as mall_image');
                    $malls->leftJoin('media', function($join) {
                        $join->on('media.object_id', '=', 'merchants.merchant_id')
                            ->where('media.media_name_id', '=', 'retailer_background')
                            ->where('media.media_name_long', '=', 'retailer_background_orig')
                            ->where('media.object_name', '=', 'mall');
                    });
                }

                // Make sure the with_count also in array format
                $withCount = array();
                OrbitInput::get('with_count', function ($_wcount) use (&$withCount) {
                    $withCount = (array) $_wcount;
                });

                foreach ($with as $relation) {
                    $malls->with($relation);

                    // Also include number of count if consumer ask it
                    if (in_array($relation, $withCount)) {
                        $countRelation = $relation . 'Number';
                        $malls->with($countRelation);
                    }
                }
            });


            $_malls = clone $malls;

            // if not printing / exporting data then do pagination.
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
                $malls->take($take);

                $skip = 0;
                OrbitInput::get('skip', function ($_skip) use (&$skip, $malls) {
                    if ($_skip < 0) {
                        $_skip = 0;
                    }

                    $skip = $_skip;
                });
                $malls->skip($skip);
            }

            // Default sort by
            $sortBy = 'merchants.name';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function ($_sortBy) use (&$sortBy) {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'merchant_orid'        => 'merchants.orid',
                    'registered_date'      => 'merchants.created_at',
                    'merchant_name'        => 'merchants.name',
                    'merchant_email'       => 'merchants.email',
                    'merchant_userid'      => 'merchants.user_id',
                    'merchant_description' => 'merchants.description',
                    'merchantid'           => 'merchants.merchant_id',
                    'merchant_address1'    => 'merchants.address_line1',
                    'merchant_address2'    => 'merchants.address_line2',
                    'merchant_address3'    => 'merchants.address_line3',
                    'merchant_cityid'      => 'merchants.city_id',
                    'merchant_city'        => 'merchants.city',
                    'merchant_countryid'   => 'merchants.country_id',
                    'merchant_country'     => 'merchants.country',
                    'merchant_phone'       => 'merchants.phone',
                    'merchant_fax'         => 'merchants.fax',
                    'merchant_status'      => 'merchants.status',
                    'merchant_currency'    => 'merchants.currency',
                    'start_date_activity'  => 'merchants.start_date_activity',
                    'total_retailer'       => 'total_retailer',
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function ($_sortMode) use (&$sortMode) {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });
            $malls->orderBy($sortBy, $sortMode);

            // Return the instance of Query Builder
            if ($this->returnBuilder) {
                return ['builder' => $malls, 'count' => RecordCounter::create($_malls)->count()];
            }

            $totalRec = RecordCounter::create($_malls)->count();
            $listOfRec = $malls->get();

            // Get start button translations
            OrbitInput::get('startbuttontranslation', function ($startButtonTranslation) use (&$listOfRec) {
                if (isset($listOfRec[0])) {
                    if ( $startButtonTranslation === 'on' && count($listOfRec[0]->settings) > 0){
                        foreach ($listOfRec[0]->settings as $key => $value) {
                            if ($value->setting_name === 'start_button_label') {
                                $listOfRec[0]->start_button_translations = $value->hasMany('SettingTranslation', 'setting_id', 'setting_id')
                                                                                ->whereHas('language', function($has) {
                                                                                $has->where('merchant_languages.status', 'active');
                                                                            })->get();
                            }
                        }
                    }
                }
            });

            $data = new stdclass();
            $data->total_records = $totalRec;
            $data->returned_records = count($listOfRec);
            $data->records = $listOfRec;

            if ($totalRec === 0) {
                $data->records = null;
                $this->response->message = Lang::get('statuses.orbit.nodata.mall');
            }

            $this->response->data = $data;

        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.mall.getsearchmall.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.mall.getsearchmall.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.mall.getsearchmall.query.error', array($this, $e));

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
            Event::fire('orbit.mall.getsearchmall.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }
        $output = $this->render($httpCode);
        Event::fire('orbit.mall.getsearchmall.before.render', array($this, &$output));

        return $output;
    }

    /**
     * POST - Update merchant (or mall)
     *
     * @author Kadek <kadek@dominopos.com>
     * @author Tian <tian@dominopos.com>
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `merchant_id`              (required) - ID of the merchant
     * @param integer    `user_id`                  (optional) - User id for the merchant
     * @param string     `email`                    (optional) - Email address of the merchant
     * @param string     `name`                     (optional) - Name of the merchant
     * @param string     `description`              (optional) - Merchant description
     * @param string     `address_line1`            (optional) - Address 1
     * @param string     `address_line2`            (optional) - Address 2
     * @param string     `address_line3`            (optional) - Address 3
     * @param integer    `postal_code`              (optional) - Postal code
     * @param integer    `city_id`                  (optional) - City id
     * @param string     `city`                     (optional) - Name of the city
     * @param integer    `country_id`               (optional) - Country id
     * @param string     `country`                  (optional) - Name of the country
     * @param string     `phone`                    (optional) - Phone of the merchant
     * @param string     `fax`                      (optional) - Fax of the merchant
     * @param string     `start_date_activity`      (optional) - Start date activity of the merchant
     * @param string     `status`                   (optional) - Status of the merchant
     * @param string     `logo`                     (optional) - Logo of the merchant
     * @param string     `currency`                 (optional) - Currency used by the merchant
     * @param string     `currency_symbol`          (optional) - Currency symbol
     * @param string     `tax_code1`                (optional) - Tax code 1
     * @param string     `tax_code2`                (optional) - Tax code 2
     * @param string     `tax_code3`                (optional) - Tax code 3
     * @param string     `slogan`                   (optional) - Slogan for the merchant
     * @param string     `vat_included`             (optional) - Vat included
     * @param string     `contact_person_firstname` (optional) - Contact person firstname
     * @param string     `contact_person_lastname`  (optional) - Contact person lastname
     * @param string     `contact_person_position`  (optional) - Contact person position
     * @param string     `contact_person_phone`     (optional) - Contact person phone
     * @param string     `contact_person_phone2`    (optional) - Contact person phone2
     * @param string     `contact_person_email`     (optional) - Contact person email
     * @param string     `sector_of_activity`       (optional) - Sector of activity
     * @param string     `object_type`              (optional) - Object type
     * @param string     `parent_id`                (optional) - The merchant id
     * @param file       `images`                   (optional) - Merchant logo
     * @param string     `mobile_default_language`  (optional) - Mobile default language
     * @param string     `pos_language`             (optional) - POS language
     * @param array      `merchant_taxes`           (optional) - Merchant taxes array
     * @param integer    `merchant_tax_id`          (optional) - Merchant Tax ID
     * @param string     `tax_name`                 (optional) - Tax name
     * @param string     `tax_type`                 (optional) - Tax type. Valid value: government, service, luxury.
     * @param decimal    `tax_value`                (optional) - Tax value
     * @param string     `is_delete`                (optional) - Soft delete flag. Valid value: Y.
     * @param string     `ticket_header`            (optional) - Ticket header
     * @param string     `ticket_footer`            (optional) - Ticket footer
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postUpdateMall()
    {
        $activity = Activity::portal()
                           ->setActivityType('update');

        $user = NULL;
        $updatedmall = NULL;
        try {
            $httpCode=200;

            Event::fire('orbit.mall.postupdatemall.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.mall.postupdatemall.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.mall.postupdatemall.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('update_mall')) {
                Event::fire('orbit.mall.postupdatemall.authz.notallowed', array($this, $user));
                $updateMallLang = Lang::get('validation.orbit.actionlist.update_mall');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $updateMallLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.mall.postupdatemall.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $merchant_id = OrbitInput::post('current_mall');;
            $user_id = OrbitInput::post('user_id');
            $email = OrbitInput::post('email');
            $status = OrbitInput::post('status');
            $orid = OrbitInput::post('orid');
            $ticket_header = OrbitInput::post('ticket_header');
            $ticket_footer = OrbitInput::post('ticket_footer');
            $url = OrbitInput::post('url');

            $validator = Validator::make(
                array(
                    'current_mall'      => $merchant_id,
                    'user_id'           => $user_id,
                    'email'             => $email,
                    'status'            => $status,
                    'orid'              => $orid,
                    'ticket_header'     => $ticket_header,
                    'ticket_footer'     => $ticket_footer,
                    'url'               => $url,
                ),
                array(
                    'current_mall'      => 'required|orbit.empty.mall',
                    'user_id'           => 'orbit.empty.user',
                    'email'             => 'email|email_exists_but_me',
                    'status'            => 'orbit.empty.mall_status',//|orbit.exists.merchant_retailers_is_box_current_retailer:'.$merchant_id,
                    'orid'              => 'orid_exists_but_me',
                    'ticket_header'     => 'ticket_header_max_length',
                    'ticket_footer'     => 'ticket_footer_max_length',
                    'url'               => 'orbit.formaterror.url.web'
                ),
                array(
                   'email_exists_but_me'      => Lang::get('validation.orbit.exists.email'),
                   'orid_exists_but_me'       => Lang::get('validation.orbit.exists.orid'),
                   'ticket_header_max_length' => Lang::get('validation.orbit.formaterror.merchant.ticket_header.max_length'),
                   'ticket_footer_max_length' => Lang::get('validation.orbit.formaterror.merchant.ticket_footer.max_length')
               )
            );

            Event::fire('orbit.mall.postupdatemall.before.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.mall.postupdatemall.after.validation', array($this, $validator));

            $updatedmall = Mall::with('taxes')->excludeDeleted()->allowedForUser($user)->where('merchant_id', $merchant_id)->first();

            OrbitInput::post('orid', function($orid) use ($updatedmall) {
                $updatedmall->orid = $orid;
            });

            OrbitInput::post('user_id', function($user_id) use ($updatedmall) {
                // Right know the interface does not provide a way to change
                // the user so it's better to skip it.
                // $updatedmall->user_id = $user_id;
            });

            OrbitInput::post('email', function($email) use ($updatedmall) {
                $updatedmall->email = $email;
            });

            OrbitInput::post('name', function($name) use ($updatedmall) {
                $updatedmall->name = $name;
            });

            OrbitInput::post('description', function($description) use ($updatedmall) {
                $updatedmall->description = $description;
            });

            OrbitInput::post('address_line1', function($address_line1) use ($updatedmall) {
                $updatedmall->address_line1 = $address_line1;
            });

            OrbitInput::post('address_line2', function($address_line2) use ($updatedmall) {
                $updatedmall->address_line2 = $address_line2;
            });

            OrbitInput::post('address_line3', function($address_line3) use ($updatedmall) {
                $updatedmall->address_line3 = $address_line3;
            });

            OrbitInput::post('postal_code', function($postal_code) use ($updatedmall) {
                $updatedmall->postal_code = $postal_code;
            });

            OrbitInput::post('city_id', function($city_id) use ($updatedmall) {
                $updatedmall->city_id = $city_id;
            });

            OrbitInput::post('city', function($city) use ($updatedmall) {
                $updatedmall->city = $city;
            });

            OrbitInput::post('country', function($country_id) use ($updatedmall) {
                $countryName = '';
                $countryObject = Country::find($country_id);
                if (is_object($countryObject)) {
                    $countryName = $countryObject->name;
                }

                $updatedmall->country_id = $country_id;
                $updatedmall->country = $countryName;
            });

            OrbitInput::post('phone', function($phone) use ($updatedmall) {
                $updatedmall->phone = $phone;
            });

            OrbitInput::post('fax', function($fax) use ($updatedmall) {
                $updatedmall->fax = $fax;
            });

            OrbitInput::post('start_date_activity', function($start_date_activity) use ($updatedmall) {
                $updatedmall->start_date_activity = $start_date_activity;
            });

            OrbitInput::post('end_date_activity', function($end_date_activity) use ($updatedmall) {
                $updatedmall->end_date_activity = $end_date_activity;
            });

            OrbitInput::post('status', function($status) use ($updatedmall) {
                $updatedmall->status = $status;
            });

            OrbitInput::post('logo', function($logo) use ($updatedmall) {
                $updatedmall->logo = $logo;
            });

            OrbitInput::post('currency', function($currency) use ($updatedmall) {
                $updatedmall->currency = $currency;
            });

            OrbitInput::post('currency_symbol', function($currency_symbol) use ($updatedmall) {
                $updatedmall->currency_symbol = $currency_symbol;
            });

            OrbitInput::post('tax_code1', function($tax_code1) use ($updatedmall) {
                $updatedmall->tax_code1 = $tax_code1;
            });

            OrbitInput::post('tax_code2', function($tax_code2) use ($updatedmall) {
                $updatedmall->tax_code2 = $tax_code2;
            });

            OrbitInput::post('tax_code3', function($tax_code3) use ($updatedmall) {
                $updatedmall->tax_code3 = $tax_code3;
            });

            OrbitInput::post('slogan', function($slogan) use ($updatedmall) {
                $updatedmall->slogan = $slogan;
            });

            OrbitInput::post('vat_included', function($vat_included) use ($updatedmall) {
                $updatedmall->vat_included = $vat_included;
            });

            OrbitInput::post('contact_person_firstname', function($contact_person_firstname) use ($updatedmall) {
                $updatedmall->contact_person_firstname = $contact_person_firstname;
            });

            OrbitInput::post('contact_person_lastname', function($contact_person_lastname) use ($updatedmall) {
                $updatedmall->contact_person_lastname = $contact_person_lastname;
            });

            OrbitInput::post('contact_person_position', function($contact_person_position) use ($updatedmall) {
                $updatedmall->contact_person_position = $contact_person_position;
            });

            OrbitInput::post('contact_person_phone', function($contact_person_phone) use ($updatedmall) {
                $updatedmall->contact_person_phone = $contact_person_phone;
            });

            OrbitInput::post('contact_person_phone2', function($contact_person_phone2) use ($updatedmall) {
                $updatedmall->contact_person_phone2 = $contact_person_phone2;
            });

            OrbitInput::post('contact_person_email', function($contact_person_email) use ($updatedmall) {
                $updatedmall->contact_person_email = $contact_person_email;
            });

            OrbitInput::post('sector_of_activity', function($sector_of_activity) use ($updatedmall) {
                $updatedmall->sector_of_activity = $sector_of_activity;
            });

            OrbitInput::post('parent_id', function($parent_id) use ($updatedmall) {
                $updatedmall->parent_id = $parent_id;
            });

            OrbitInput::post('url', function($url) use ($updatedmall) {
                $updatedmall->url = $url;
            });

            OrbitInput::post('masterbox_number', function($masterbox_number) use ($updatedmall) {
                $updatedmall->masterbox_number = $masterbox_number;
            });

            OrbitInput::post('slavebox_number', function($slavebox_number) use ($updatedmall) {
                $updatedmall->slavebox_number = $slavebox_number;
            });

            OrbitInput::post('mobile_default_language', function($mobile_default_language) use ($updatedmall) {
                $updatedmall->mobile_default_language = $mobile_default_language;
            });

            OrbitInput::post('pos_language', function($pos_language) use ($updatedmall) {
                if (trim($pos_language) === '') {
                    $pos_language = NULL;
                }
                $updatedmall->pos_language = $pos_language;
            });

            OrbitInput::post('ticket_header', function($ticket_header) use ($updatedmall) {
                $updatedmall->ticket_header = $ticket_header;
            });

            OrbitInput::post('ticket_footer', function($ticket_footer) use ($updatedmall) {
                $updatedmall->ticket_footer = $ticket_footer;
            });

            $updatedmall->modified_by = $this->api->user->user_id;

            Event::fire('orbit.mall.postupdatemall.before.save', array($this, $updatedmall));

            $updatedmall->save();

            // update user status
            OrbitInput::post('status', function($status) use ($updatedmall) {
                $updateuser = User::with(array('role'))->excludeDeleted()->find($updatedmall->user_id);
                if (! $updateuser->isSuperAdmin()) {
                    $updateuser->status = $status;
                    $updateuser->modified_by = $this->api->user->user_id;

                    $updateuser->save();
                }
            });

            // do insert/update/delete merchant_taxes
            OrbitInput::post('merchant_taxes', function($merchant_taxes) use ($updatedmall) {
                $merchant_taxes = (array) $merchant_taxes;
                foreach ($merchant_taxes as $merchant_tax) {
                    // validate merchant_taxes
                    $validator = Validator::make(
                        array(
                            'merchant_tax_id'        => $merchant_tax['merchant_tax_id'],
                            'tax_name'               => $merchant_tax['tax_name'],
                            'tax_type'               => $merchant_tax['tax_type'],
                            'is_delete'              => $merchant_tax['is_delete'],
                        ),
                        array(
                            'merchant_tax_id'        => 'orbit.empty.tax',
                            'tax_name'               => 'required|max:50|tax_name_exists_but_me:'.$merchant_tax['merchant_tax_id'].','.$updatedmall->merchant_id,
                            'tax_type'               => 'orbit.empty.tax_type',
                            'is_delete'              => 'orbit.exists.tax_link_to_product:'.$merchant_tax['merchant_tax_id'],
                        ),
                        array(
                            'tax_name_exists_but_me' => Lang::get('validation.orbit.exists.tax_name'),
                        )
                    );

                    Event::fire('orbit.mall.postupdatemall.before.merchanttaxesvalidation', array($this, $validator));

                    // Run the validation
                    if ($validator->fails()) {
                        $errorMessage = $validator->messages()->first();
                        OrbitShopAPI::throwInvalidArgument($errorMessage);
                    }

                    Event::fire('orbit.mall.postupdatemall.after.merchanttaxesvalidation', array($this, $validator));

                    //save merchant_taxes
                    if (trim($merchant_tax['merchant_tax_id']) === '') {
                        // do insert
                        $merchanttax = new MerchantTax();
                        $merchanttax->merchant_id = $updatedmall->merchant_id;
                        $merchanttax->tax_name = $merchant_tax['tax_name'];
                        $merchanttax->tax_type = $merchant_tax['tax_type'];
                        $merchanttax->tax_value = $merchant_tax['tax_value'];
                        $merchanttax->status = 'active';
                        $merchanttax->created_by = $this->api->user->user_id;
                        $merchanttax->save();
                    } else {
                        if ($merchant_tax['is_delete'] === 'Y') {
                            // do soft delete
                            $merchanttax = MerchantTax::excludeDeleted()->where('merchant_tax_id', $merchant_tax['merchant_tax_id'])->first();
                            $merchanttax->status = 'deleted';
                            $merchanttax->modified_by = $this->api->user->user_id;
                            $merchanttax->save();
                        } else {
                            // do update
                            $merchanttax = MerchantTax::excludeDeleted()->where('merchant_tax_id', $merchant_tax['merchant_tax_id'])->first();
                            $merchanttax->tax_name = $merchant_tax['tax_name'];
                            $merchanttax->tax_type = $merchant_tax['tax_type'];
                            $merchanttax->tax_value = $merchant_tax['tax_value'];
                            $merchanttax->modified_by = $this->api->user->user_id;
                            $merchanttax->save();
                        }
                    }
                }

                // reload taxes relation
                $updatedmall->load('taxes');
            });

            Event::fire('orbit.mall.postupdatemall.after.save', array($this, $updatedmall));
            $this->response->data = $updatedmall;

            // Commit the changes
            $this->commit();

            // Successfull Update
            $activityNotes = sprintf('Mall updated: %s', $updatedmall->name);
            $activity->setUser($user)
                    ->setActivityName('update_mall')
                    ->setActivityNameLong('Update Mall OK')
                    ->setObject($updatedmall)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.mall.postupdatemall.after.commit', array($this, $updatedmall));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.mall.postupdatemall.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_mall')
                    ->setActivityNameLong('Update Mall Failed')
                    ->setObject($updatedmall)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.mall.postupdatemall.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_mall')
                    ->setActivityNameLong('Update Mall Failed')
                    ->setObject($updatedmall)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.mall.postupdatemall.query.error', array($this, $e));

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
                    ->setActivityName('update_mall')
                    ->setActivityNameLong('Update Mall Failed')
                    ->setObject($updatedmall)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.mall.postupdatemall.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_mall')
                    ->setActivityNameLong('Update Mall Failed')
                    ->setObject($updatedmall)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save activity
        $activity->save();

        return $this->render($httpCode);
    }

    /**
     * POST - Delete Mall
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @author Kadek <kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `merchant_id`                 (required) - ID of the merchant
     * @param string     `password`                    (required) - Password of the user for confirmation
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeleteMall()
    {
        $activity = Activity::portal()
                          ->setActivityType('delete');

        $user = NULL;
        $deletemall = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.mall.postdeletemall.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.mall.postdeletemall.after.auth', array($this));

            // Try to check access control list, does this merchant allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.mall.postdeletemall.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('delete_mall')) {
                Event::fire('orbit.mall.postdeletemall.authz.notallowed', array($this, $user));
                $deleteMallLang = Lang::get('validation.orbit.actionlist.delete_mall');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $deleteMallLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.mall.postdeletemall.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $merchant_id = OrbitInput::post('current_mall');;
            $password = OrbitInput::post('password');

            $validator = Validator::make(
                array(
                    'current_mall'=> $merchant_id,
                    'password'    => $password,
                ),
                array(
                    'current_mall'=> 'required|orbit.empty.mall|orbit.exists.mall_have_tenant',
                    'password'    => 'required|orbit.access.wrongpassword',
                )
            );

            Event::fire('orbit.mall.postdeletemall.before.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.mall.postdeletemall.after.validation', array($this, $validator));

            // soft delete merchant.
            $deletemall = Mall::excludeDeleted()->allowedForUser($user)->where('merchant_id', $merchant_id)->first();
            $deletemall->status = 'deleted';
            $deletemall->modified_by = $this->api->user->user_id;

            Event::fire('orbit.mall.postdeletemall.before.save', array($this, $deletemall));

            $deletemall->save();

            // soft delete user.
            $deleteuser = User::with(array('apikey', 'role'))->excludeDeleted()->find($deletemall->user_id);
            // don't delete linked user if linked user is super admin.
            if (! $deleteuser->isSuperAdmin()) {
                $deleteuser->status = 'deleted';
                $deleteuser->modified_by = $this->api->user->user_id;

                // soft delete api key.
                if (! empty($deleteuser->apikey)) {
                    $deleteapikey = Apikey::where('apikey_id', '=', $deleteuser->apikey->apikey_id)->first();
                    $deleteapikey->status = 'deleted';
                    $deleteapikey->save();
                }

                $deleteuser->save();
            }

            Event::fire('orbit.mall.postdeletemall.after.save', array($this, $deletemall));
            $this->response->data = null;
            $this->response->message = Lang::get('statuses.orbit.deleted.mall');

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activityNotes = sprintf('Mall Deleted: %s', $deletemall->name);
            $activity->setUser($user)
                    ->setActivityName('delete_mall')
                    ->setActivityNameLong('Delete Mall OK')
                    ->setObject($deletemall)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.mall.postdeletemall.after.commit', array($this, $deletemall));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.mall.postdeletemall.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_mall')
                    ->setActivityNameLong('Delete Mall Failed')
                    ->setObject($deletemall)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.mall.postdeletemall.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_mall')
                    ->setActivityNameLong('Delete Mall Failed')
                    ->setObject($deletemall)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.mall.postdeletemall.query.error', array($this, $e));

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
                    ->setActivityName('delete_mall')
                    ->setActivityNameLong('Delete Mall Failed')
                    ->setObject($deletemall)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.mall.postdeletemall.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_mall')
                    ->setActivityNameLong('Delete Mall Failed')
                    ->setObject($deletemall)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.mall.postdeletemall.before.render', array($this, $output));

        // Save the activity
        $activity->save();

        return $output;
    }

    protected function registerCustomValidation()
    {
        // Check the existance of merchant id
        $user = $this->api->user;
        Validator::extend('orbit.empty.mall', function ($attribute, $value, $parameters) use ($user) {
            $mall = Mall::excludeDeleted()
                        ->where('merchant_id', $value)
                        ->first();

            if (empty($mall)) {
                return FALSE;
            }

            App::instance('orbit.empty.mall', $mall);

            return TRUE;
        });

        // Check user email address, it should not exists
        Validator::extend('orbit.exists.email', function ($attribute, $value, $parameters) {
            $mall = Mall::excludeDeleted()
                        ->where('email', $value)
                        ->first();

            if (! empty($mall)) {
                return FALSE;
            }

            App::instance('orbit.validation.mall', $mall);

            return TRUE;
        });

        // Check user email address, it should not exists (for update)
        Validator::extend('email_exists_but_me', function ($attribute, $value, $parameters) {
            $mall_id = OrbitInput::post('current_mall');;
            $mall = Mall::excludeDeleted()
                        ->where('email', $value)
                        ->where('merchant_id', '!=', $mall_id)
                        ->first();

            if (! empty($mall)) {
                return FALSE;
            }

            App::instance('orbit.validation.mall', $mall);

            return TRUE;
        });

        // Check ORID, it should not exists (for update)
        Validator::extend('orid_exists_but_me', function ($attribute, $value, $parameters) {
            $mall_id = OrbitInput::post('current_mall');;
            $mall = Mall::excludeDeleted()
                        ->where('orid', $value)
                        ->where('merchant_id', '!=', $mall_id)
                        ->first();

            if (! empty($mall)) {
                return FALSE;
            }

            App::instance('orbit.validation.mall', $mall);

            return TRUE;
        });

        // Check orid, it should not exists
        Validator::extend('orbit.exists.orid', function ($attribute, $value, $parameters) {
            $mall = Mall::excludeDeleted()
                        ->where('orid', $value)
                        ->first();

            if (! empty($mall)) {
                return FALSE;
            }

            App::instance('orbit.validation.mall', $mall);

            return TRUE;
        });

        // Check the existance of user id
        Validator::extend('orbit.empty.user', function ($attribute, $value, $parameters) {
            $user = User::excludeDeleted()
                        ->where('user_id', $value)
                        ->first();

            if (empty($user)) {
                return FALSE;
            }

            App::instance('orbit.empty.user', $user);

            return TRUE;
        });

        // Check the existance of the merchant status
        Validator::extend('orbit.empty.mall_status', function ($attribute, $value, $parameters) {
            $valid = false;
            $statuses = array('active', 'inactive', 'pending', 'blocked', 'deleted');
            foreach ($statuses as $status) {
                if($value === $status) $valid = $valid || TRUE;
            }

            return $valid;
        });

        // Check if the password correct
        Validator::extend('orbit.access.wrongpassword', function ($attribute, $value, $parameters) {
            if (Hash::check($value, $this->api->user->user_password)) {
                return TRUE;
            }

            App::instance('orbit.validation.mall', $value);

            return FALSE;
        });

        // Check the existance of merchant_tax_id
        Validator::extend('orbit.empty.tax', function ($attribute, $value, $parameters) {
            $merchanttax = MerchantTax::excludeDeleted()
                        ->where('merchant_tax_id', $value)
                        ->first();

            if (empty($merchanttax)) {
                return FALSE;
            }

            App::instance('orbit.empty.tax', $merchanttax);

            return TRUE;
        });

        // Check the existance of the tax type
        Validator::extend('orbit.empty.tax_type', function ($attribute, $value, $parameters) {
            $valid = false;
            $taxTypes = array('government', 'service', 'luxury');
            foreach ($taxTypes as $taxType) {
                if($value === $taxType) $valid = $valid || TRUE;
            }

            return $valid;
        });

        // Check tax name for duplication
        Validator::extend('tax_name_exists_but_me', function ($attribute, $value, $parameters) {
            $merchant_tax_id = trim($parameters[0]);
            $merchant_id = trim($parameters[1]);
            // if new tax
            if ($merchant_tax_id === '') {
                $tax_name = MerchantTax::excludeDeleted()
                    ->where('tax_name', $value)
                    ->where('merchant_id', $merchant_id)
                    ->first();
            } else { // if update tax
                $tax_name = MerchantTax::excludeDeleted()
                    ->where('tax_name', $value)
                    ->where('merchant_id', $merchant_id)
                    ->where('merchant_tax_id', '!=', $merchant_tax_id)
                    ->first();
            }

            if (! empty($tax_name)) {
                return FALSE;
            }

            App::instance('orbit.validation.tax_name', $tax_name);

            return TRUE;
        });

        // Check if tax have linked to product
        Validator::extend('orbit.exists.tax_link_to_product', function ($attribute, $value, $parameters) {

            // check tax if exists in products.
            $merchant_tax_id = trim($parameters[0]);
            $product = Product::excludeDeleted()
                ->where(function ($query) use ($merchant_tax_id) {
                    $query->where('merchant_tax_id1', $merchant_tax_id)
                        ->orWhere('merchant_tax_id2', $merchant_tax_id);
                })
                ->first();

            if (! empty($product)) {
                return FALSE;
            }

            App::instance('orbit.exists.tax_link_to_product', $product);

            return TRUE;
        });

        // Check ticket header max length
        Validator::extend('ticket_header_max_length', function ($attribute, $value, $parameters) {
            $ticketHeader = LineChecker::create($value)->noMoreThan(40);

            if (!empty($ticketHeader)) {
                return FALSE;
            }

            App::instance('orbit.formaterror.mall.ticket_header.max_length', $ticketHeader);

            return TRUE;
        });

        // Check ticket footer max length
        Validator::extend('ticket_footer_max_length', function ($attribute, $value, $parameters) {
            $ticketFooter = LineChecker::create($value)->noMoreThan(40);

            if (!empty($ticketFooter)) {
                return FALSE;
            }

            App::instance('orbit.formaterror.mall.ticket_footer.max_length', $ticketFooter);

            return TRUE;
        });

        // Check the validity of URL
        Validator::extend('orbit.formaterror.url.web', function ($attribute, $value, $parameters) {
            $url = $value;
            $pattern = '@^([a-z0-9]+)([a-z0-9\-]+)(\.([a-z0-9]){2}){1}@';

            if (! preg_match($pattern, $url)) {
                return FALSE;
            }

            App::instance('orbit.formaterror.url.web', $url);

            return TRUE;
        });

        // Check if mall have tenant.
        Validator::extend('orbit.exists.mall_have_tenant', function ($attribute, $value, $parameters) {
            $tenant = Tenant::excludeDeleted()
                            ->where('parent_id', $value)
                            ->first();
            if (! empty($tenant)) {
                return FALSE;
            }

            App::instance('orbit.exists.mall_have_tenant', $tenant);

            return TRUE;
        });

        // if merchant status is updated to inactive, then reject if its retailers is current retailer.
        Validator::extend('orbit.exists.merchant_retailers_is_box_current_retailer', function ($attribute, $value, $parameters) {
            if ($value === 'inactive') {
                $merchant_id = $parameters[0];
                $retailer_id = Setting::where('setting_name', 'current_retailer')->first()->setting_value;
                $currentRetailer = Tenant::excludeDeleted()
                                    ->where('parent_id', $merchant_id)
                                    ->where('merchant_id', $retailer_id)
                                    ->first();

                if (! empty($currentRetailer)) {
                    return FALSE;
                }

                App::instance('orbit.exists.merchant_retailers_is_box_current_retailer', $currentRetailer);
            }

            return TRUE;
        });
    }


    /**
     * GET - Mall City List
     *
     * @author kadek <kadek@dominopos.com>
     *
     * @return Illuminate\Support\Facades\Response
     *
     */
    public function getCityList()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.mall.getcitylist.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.mall.getcitylist.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.mall.getcitylist.before.authz', array($this, $user));

            // if (! ACL::create($user)->isAllowed('view_tenant')) {
            //     Event::fire('orbit.mall.getcitylist.authz.notallowed', array($this, $user));
            //     $viewTenantLang = Lang::get('validation.orbit.actionlist.view_tenant');
            //     $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewTenantLang));
            //     ACL::throwAccessForbidden($message);
            // }

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = ['super admin', 'mall admin', 'mall owner', 'consumer'];
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.mall.getcitylist.after.authz', array($this, $user));

            $tenants = Mall::excludeDeleted()
                ->select('city')
                ->where('city', '!=', 'null')
                ->orderBy('city', 'asc')
                ->groupBy('city')
                ->get();

            $data = new stdclass();
            $data->records = $tenants;

            if ($tenants->count() === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.city');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.mall.getcitylist.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.mall.getcitylist.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.mall.getcitylist.query.error', array($this, $e));

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
            Event::fire('orbit.mall.getcitylist.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.mall.getcitylist.before.render', array($this, &$output));

        return $output;
    }

    /**
     * GET - Search merchant
     *
     * @author Shelgi Prasetyo <shelgi@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string merchant_id
     * @param string (optional) - campaign_type
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getMallBasePrice()
    {
        $httpCode = 200;
        try {

            $this->checkAuth();

            $this->registerCustomValidation();
            $merchant_id = OrbitInput::get('merchant_id', null);

            $base_price = CampaignBasePrices::where('merchant_id', '=', $merchant_id);

            // Filter base price by campaign_type
            OrbitInput::get('campaign_type', function ($campaign_type) use ($base_price) {
                $base_price->where('campaign_type', '=', $campaign_type);
            });

            $listbaseprice = $base_price->get();
            $count = count($listbaseprice);

            $this->response->data = new stdClass();
            $this->response->data->total_records = $count;
            $this->response->data->returned_records = $count;
            $this->response->data->records = $listbaseprice;
        } catch (ACLForbiddenException $e) {

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {

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

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 500;
        }

        $output = $this->render($httpCode);

        return $output;
    }

    public function setReturnBuilder($bool)
    {
        $this->returnBuilder = $bool;

        return $this;
    }

    public function setWithMallGroup($bool)
    {
        $this->withMallGroup = $bool;

        return $this;
    }
}
