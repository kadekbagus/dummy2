<?php
/**
 * An API controller for managing mall groups.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Text\Util\LineChecker;
use Helper\EloquentRecordCounter as RecordCounter;

class MallGroupAPIController extends ControllerAPI
{
     /**
     * POST - Add new mall group
     *
     * @author Kadek <kadek@dominopos.com>
     * @author Rio Astamal <me@rioastamal.net>
     * @author Tian <tian@dominopos.com>
     * @author Irianto <irianto@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string     `email`                   (required) - Email address of the merchant
     * @param string     `name`                    (required) - Name of the merchant
     * @param string     `status`                  (required) - Status of the merchant
     * @param string     `country`                 (required) - Country ID
     * @param string     `description`             (optional) - Merchant description
     * @param string     `address_line1`           (optional) - Address 1
     * @param string     `address_line2`           (optional) - Address 2
     * @param string     `address_line3`           (optional) - Address 3
     * @param integer    `postal_code`             (optional) - Postal code
     * @param integer    `city_id`                 (optional) - City id
     * @param string     `city`                    (optional) - Name of the city
     * @param string     `province`                (optional) - Name of the province
     * @param string     `phone`                   (optional) - Phone of the merchant
     * @param string     `fax`                     (optional) - Fax of the merchant
     * @param string     `start_date_activity`     (optional) - Start date activity of the merchant
     * @param string     `end_date_activity`       (optional) - End date activity of the merchant
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
     * @param string     `logo`                    (optional) - Logo of the mall group
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postNewMallGroup()
    {
        $activity = Activity::portal()
                            ->setActivityType('create');

        $user = NULL;
        $newmallgroup = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.mallgroup.postnewmallgroup.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.mallgroup.postnewmallgroup.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.mallgroup.postnewmallgroup.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('create_mall_group')) {
                Event::fire('orbit.mallgroup.postnewmallgroup.authz.notallowed', array($this, $user));
                $createMallLang = Lang::get('validation.orbit.actionlist.new_mall_group');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $createMallLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.mallgroup.postnewmallgroup.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $email = OrbitInput::post('email');
            $name = OrbitInput::post('name');
            $password = OrbitInput::post('password');
            $password2 = OrbitInput::post('password_confirmation');
            $description = OrbitInput::post('description');
            $address_line1 = OrbitInput::post('address_line1');
            $address_line2 = OrbitInput::post('address_line2');
            $address_line3 = OrbitInput::post('address_line3');
            $postal_code = OrbitInput::post('postal_code');
            $city_id = OrbitInput::post('city_id');
            $city = OrbitInput::post('city');
            $province = OrbitInput::post('province');
            $country = OrbitInput::post('country');
            $phone = OrbitInput::post('phone');
            $fax = OrbitInput::post('fax');
            $start_date_activity = OrbitInput::post('start_date_activity');
            $end_date_activity = OrbitInput::post('end_date_activity');
            $status = OrbitInput::post('status');
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
            $url = OrbitInput::post('url');
            $masterbox_number = OrbitInput::post('masterbox_number');
            $slavebox_number = OrbitInput::post('slavebox_number');
            $mobile_default_language = OrbitInput::post('mobile_default_language');
            $pos_language = OrbitInput::post('pos_language');
            $logo = OrbitInput::post('logo');

            $validator = Validator::make(
                array(
                    'email'                 => $email,
                    'name'                  => $name,
                    'status'                => $status,
                    'country'               => $country,
                    'url'                   => $url,
                    'password'              => $password,
                    'password_confirmation' => $password2,
                ),
                array(
                    'email'         => 'required|email|orbit.exists.email',
                    'name'          => 'required',
                    'status'        => 'required|orbit.empty.mall_status',
                    'country'       => 'required|orbit.empty.country',
                    'url'           => 'orbit.formaterror.url.web',
                    'password'      => 'required|min:6|confirmed'
                )
            );

            Event::fire('orbit.mallgroup.postnewmallgroup.before.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.mallgroup.postnewmallgroup.after.validation', array($this, $validator));

            $roleMerchant = Role::where('role_name', 'mall owner')->first();
            if (empty($roleMerchant)) {
                OrbitShopAPI::throwInvalidArgument('Could not find role named "Mall Owner".');
            }

            $newuser = new User();
            $newuser->username = $email;
            $newuser->user_email = $email;
            $newuser->user_password = Hash::make($password);
            $newuser->status = $status;
            $newuser->user_role_id = $roleMerchant->role_id;
            $newuser->user_ip = $_SERVER['REMOTE_ADDR'];
            $newuser->modified_by = $user->user_id;
            $newuser->save();

            $newuser->createAPiKey();

            $userdetail = new UserDetail();
            $userdetail = $newuser->userdetail()->save($userdetail);

            $countryName = '';
            $countryObject = App::make('orbit.empty.country');;
            if (is_object($countryObject)) {
                $countryName = $countryObject->name;
            }

            $newmallgroup = new MallGroup();
            $newmallgroup->user_id = $newuser->user_id;
            $newmallgroup->orid = '';
            $newmallgroup->email = $email;
            $newmallgroup->name = $name;
            $newmallgroup->description = $description;
            $newmallgroup->address_line1 = $address_line1;
            $newmallgroup->address_line2 = $address_line2;
            $newmallgroup->address_line3 = $address_line3;
            $newmallgroup->postal_code = $postal_code;
            $newmallgroup->city_id = $city_id;
            $newmallgroup->city = $city;
            $newmallgroup->province = $province;
            $newmallgroup->country_id = $country;
            $newmallgroup->country = $countryName;
            $newmallgroup->phone = $phone;
            $newmallgroup->fax = $fax;
            $newmallgroup->start_date_activity = $start_date_activity;
            $newmallgroup->end_date_activity = $end_date_activity;
            $newmallgroup->status = $status;
            $newmallgroup->currency = $currency;
            $newmallgroup->currency_symbol = $currency_symbol;
            $newmallgroup->tax_code1 = $tax_code1;
            $newmallgroup->tax_code2 = $tax_code2;
            $newmallgroup->tax_code3 = $tax_code3;
            $newmallgroup->slogan = $slogan;
            $newmallgroup->vat_included = $vat_included;
            $newmallgroup->contact_person_firstname = $contact_person_firstname;
            $newmallgroup->contact_person_lastname = $contact_person_lastname;
            $newmallgroup->contact_person_position = $contact_person_position;
            $newmallgroup->contact_person_phone = $contact_person_phone;
            $newmallgroup->contact_person_phone2 = $contact_person_phone2;
            $newmallgroup->contact_person_email = $contact_person_email;
            $newmallgroup->sector_of_activity = $sector_of_activity;
            $newmallgroup->url = $url;
            $newmallgroup->masterbox_number = $masterbox_number;
            $newmallgroup->slavebox_number = $slavebox_number;
            $newmallgroup->mobile_default_language = $mobile_default_language;
            $newmallgroup->pos_language = $pos_language;
            $newmallgroup->modified_by = $this->api->user->user_id;
            $newmallgroup->logo = $logo;

            Event::fire('orbit.mallgroup.postnewmallgroup.before.save', array($this, $newmallgroup));

            $newmallgroup->save();

            // add omid to newly created mall
            $newmallgroup->omid = MallGroup::OMID_INCREMENT + $newmallgroup->merchant_id;
            $newmallgroup->save();

            Event::fire('orbit.mallgroup.postnewmallgroup.after.save', array($this, $newmallgroup));
            $this->response->data = $newmallgroup;

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activityNotes = sprintf('Mall Group Created: %s', $newmallgroup->name);
            $activity->setUser($user)
                    ->setActivityName('create_mall')
                    ->setActivityNameLong('Create Mall OK')
                    ->setObject($newmallgroup)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.mallgroup.postnewmallgroup.after.commit', array($this, $newmallgroup));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.mallgroup.postnewmallgroup.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_mall_group')
                    ->setActivityNameLong('Create Mall Group Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.mallgroup.postnewmallgroup.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_mall_group')
                    ->setActivityNameLong('Create Mall Group Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.mallgroup.postnewmallgroup.query.error', array($this, $e));

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
                    ->setActivityName('create_mall_group')
                    ->setActivityNameLong('Create Mall Group Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.mallgroup.postnewmallgroup.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_mall_group')
                    ->setActivityNameLong('Create Mall Group Failed')
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
     *
     * List of API Parameters
     * ----------------------
     * @param string            `sort_by`                       (optional) - Column order by. Valid value: merchant_omid, registered_date, merchant_name, merchant_email, merchant_userid, merchant_description, merchantid, merchant_address1, merchant_address2, merchant_address3, merchant_cityid, merchant_city, merchant_countryid, merchant_country, merchant_phone, merchant_fax, merchant_status, merchant_currency, start_date_activity, total_mall.
     * @param string            `sort_mode`                     (optional) - asc or desc
     * @param integer           `take`                          (optional) - limit
     * @param integer           `skip`                          (optional) - limit offset
     * @param string            `merchant_id`                   (optional)
     * @param string            `omid`                          (optional)
     * @param string            `user_id`                       (optional)
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
    public function getSearchMallGroup()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.mallgroup.getsearchmallgroup.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.mallgroup.getsearchmallgroup.after.auth', array($this));

            // Try to check access control list, does this mall allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.mallgroup.getsearchmallgroup.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('view_mall')) {
                Event::fire('orbit.mall.getsearchmallgroup.authz.notallowed', array($this, $user));
                $viewUserLang = Lang::get('validation.orbit.actionlist.view_mall');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewUserLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.mallgroup.getsearchmallgroup.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sortby');
            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                ),
                array(
                    'sort_by' => 'in:merchant_omid,registered_date,merchant_name,merchant_email,merchant_userid,merchant_description,merchantid,merchant_address1,merchant_address2,merchant_address3,merchant_cityid,merchant_city,merchant_countryid,merchant_country,merchant_phone,merchant_fax,merchant_status,merchant_currency,start_date_activity,total_mall',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.merchant_sortby'),
                )
            );

            Event::fire('orbit.mallgroup.getsearchmallgroup.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            Event::fire('orbit.mallgroup.getsearchmallgroup.after.validation', array($this, $validator));

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

            $mallgroups = MallGroup::excludeDeleted('merchants')
                                ->allowedForUser($user)
                                ->select('merchants.*', DB::raw('count(mall.merchant_id) AS total_mall'))
                                ->leftJoin('merchants AS mall', function($join) {
                                        $join->on(DB::raw('mall.parent_id'), '=', 'merchants.merchant_id')
                                            ->where(DB::raw('mall.status'), '!=', 'deleted')
                                            ->where(DB::raw('mall.object_type'), '=', 'mall');
                                    })
                                ->groupBy('merchants.merchant_id');

            // Filter mall by Ids
            OrbitInput::get('merchant_id', function ($merchantIds) use ($mallgroups) {
                $mallgroups->whereIn('merchants.merchant_id', $merchantIds);
            });

            // Filter mall by omid
            OrbitInput::get('omid', function ($omid) use ($mallgroups) {
                $mallgroups->whereIn('merchants.omid', $omid);
            });

            // Filter mall by user Ids
            OrbitInput::get('user_id', function ($userIds) use ($mallgroups) {
                $mallgroups->whereIn('merchants.user_id', $userIds);
            });

            // Filter mall by name
            OrbitInput::get('name', function ($name) use ($mallgroups) {
                $mallgroups->whereIn('merchants.name', $name);
            });

            // Filter mall by name pattern
            OrbitInput::get('name_like', function ($name) use ($mallgroups) {
                $mallgroups->where('merchants.name', 'like', "%$name%");
            });

            // Filter mall by description
            OrbitInput::get('description', function ($description) use ($mallgroups) {
                $mallgroups->whereIn('merchants.description', $description);
            });

            // Filter mall by description pattern
            OrbitInput::get('description_like', function ($description) use ($mallgroups) {
                $mallgroups->where('merchants.description', 'like', "%$description%");
            });

            // Filter mall by email
            OrbitInput::get('email', function ($email) use ($mallgroups) {
                $mallgroups->whereIn('merchants.email', $email);
            });

            // Filter mall by email pattern
            OrbitInput::get('email_like', function ($email) use ($mallgroups) {
                $mallgroups->where('merchants.email', 'like', "%$email%");
            });

            // Filter mall by address1
            OrbitInput::get('address1', function ($address1) use ($mallgroups) {
                $mallgroups->whereIn('merchants.address_line1', $address1);
            });

            // Filter mall by address1 pattern
            OrbitInput::get('address1_like', function ($address1) use ($mallgroups) {
                $mallgroups->where('merchants.address_line1', 'like', "%$address1%");
            });

            // Filter mall by address2
            OrbitInput::get('address2', function ($address2) use ($mallgroups) {
                $mallgroups->whereIn('merchants.address_line2', $address2);
            });

            // Filter mall by address2 pattern
            OrbitInput::get('address2_like', function ($address2) use ($mallgroups) {
                $mallgroups->where('merchants.address_line2', 'like', "%$address2%");
            });

            // Filter mall by address3
            OrbitInput::get('address3', function ($address3) use ($mallgroups) {
                $mallgroups->whereIn('merchants.address_line3', $address3);
            });

            // Filter mall by address3 pattern
            OrbitInput::get('address3_like', function ($address3) use ($mallgroups) {
                $mallgroups->where('merchants.address_line3', 'like', "%$address3%");
            });

            // Filter mall by postal code
            OrbitInput::get('postal_code', function ($postalcode) use ($mallgroups) {
                $mallgroups->whereIn('merchants.postal_code', $postalcode);
            });

            // Filter mall by cityID
            OrbitInput::get('city_id', function ($cityIds) use ($mallgroups) {
                $mallgroups->whereIn('merchants.city_id', $cityIds);
            });

            // Filter mall by city
            OrbitInput::get('city', function ($city) use ($mallgroups) {
                $mallgroups->whereIn('merchants.city', $city);
            });

            // Filter mall by city pattern
            OrbitInput::get('city_like', function ($city) use ($mallgroups) {
                $mallgroups->where('merchants.city', 'like', "%$city%");
            });

            // Filter mall by countryID
            OrbitInput::get('country_id', function ($countryId) use ($mallgroups) {
                $mallgroups->whereIn('merchants.country_id', $countryId);
            });

            // Filter mall by country
            OrbitInput::get('country', function ($country) use ($mallgroups) {
                $mallgroups->whereIn('merchants.country', $country);
            });

            // Filter mall by country pattern
            OrbitInput::get('country_like', function ($country) use ($mallgroups) {
                $mallgroups->where('merchants.country', 'like', "%$country%");
            });

            // Filter mall by phone
            OrbitInput::get('phone', function ($phone) use ($mallgroups) {
                $mallgroups->whereIn('merchants.phone', $phone);
            });

            // Filter mall by fax
            OrbitInput::get('fax', function ($fax) use ($mallgroups) {
                $mallgroups->whereIn('merchants.fax', $fax);
            });

            // Filter mall by status
            OrbitInput::get('status', function ($status) use ($mallgroups) {
                $mallgroups->whereIn('merchants.status', $status);
            });

            // Filter mall by currency
            OrbitInput::get('currency', function ($currency) use ($mallgroups) {
                $mallgroups->whereIn('merchants.currency', $currency);
            });

            // Filter mall by contact person firstname
            OrbitInput::get('contact_person_firstname', function ($contact_person_firstname) use ($mallgroups) {
                $mallgroups->whereIn('merchants.contact_person_firstname', $contact_person_firstname);
            });

            // Filter mall by contact person firstname like
            OrbitInput::get('contact_person_firstname_like', function ($contact_person_firstname) use ($mallgroups) {
                $mallgroups->where('merchants.contact_person_firstname', 'like', "%$contact_person_firstname%");
            });

            // Filter mall by contact person lastname
            OrbitInput::get('contact_person_lastname', function ($contact_person_lastname) use ($mallgroups) {
                $mallgroups->whereIn('merchants.contact_person_lastname', $contact_person_lastname);
            });

            // Filter mall by contact person lastname like
            OrbitInput::get('contact_person_lastname_like', function ($contact_person_lastname) use ($mallgroups) {
                $mallgroups->where('merchants.contact_person_lastname', 'like', "%$contact_person_lastname%");
            });

            // Filter mall by contact person position
            OrbitInput::get('contact_person_position', function ($contact_person_position) use ($mallgroups) {
                $mallgroups->whereIn('merchants.contact_person_position', $contact_person_position);
            });

            // Filter mall by contact person position like
            OrbitInput::get('contact_person_position_like', function ($contact_person_position) use ($mallgroups) {
                $mallgroups->where('merchants.contact_person_position', 'like', "%$contact_person_position%");
            });

            // Filter mall by contact person phone
            OrbitInput::get('contact_person_phone', function ($contact_person_phone) use ($mallgroups) {
                $mallgroups->whereIn('merchants.contact_person_phone', $contact_person_phone);
            });

            // Filter mall by contact person phone2
            OrbitInput::get('contact_person_phone2', function ($contact_person_phone2) use ($mallgroups) {
                $mallgroups->whereIn('merchants.contact_person_phone2', $contact_person_phone2);
            });

            // Filter mall by contact person email
            OrbitInput::get('contact_person_email', function ($contact_person_email) use ($mallgroups) {
                $mallgroups->whereIn('merchants.contact_person_email', $contact_person_email);
            });

            // Filter mall by sector of activity
            OrbitInput::get('sector_of_activity', function ($sector_of_activity) use ($mallgroups) {
                $mallgroups->whereIn('merchants.sector_of_activity', $sector_of_activity);
            });

            // Filter mall by url
            OrbitInput::get('url', function ($url) use ($mallgroups) {
                $mallgroups->whereIn('merchants.url', $url);
            });

            // Filter mall by masterbox_number
            OrbitInput::get('masterbox_number', function ($masterbox_number) use ($mallgroups) {
                $mallgroups->whereIn('merchants.masterbox_number', $masterbox_number);
            });

            // Filter mall by slavebox_number
            OrbitInput::get('slavebox_number', function ($slavebox_number) use ($mallgroups) {
                $mallgroups->whereIn('merchants.slavebox_number', $slavebox_number);
            });

            // Filter mall by mobile_default_language
            OrbitInput::get('mobile_default_language', function ($mobile_default_language) use ($mallgroups) {
                $mallgroups->whereIn('merchants.mobile_default_language', $mobile_default_language);
            });

            // Filter mall by pos_language
            OrbitInput::get('pos_language', function ($pos_language) use ($mallgroups) {
                $mallgroups->whereIn('merchants.pos_language', $pos_language);
            });

            // Add new relation based on request
            OrbitInput::get('with', function ($with) use ($mallgroups) {
                $with = (array) $with;

                // Make sure the with_count also in array format
                $withCount = array();
                OrbitInput::get('with_count', function ($_wcount) use (&$withCount) {
                    $withCount = (array) $_wcount;
                });

                foreach ($with as $relation) {
                    $mallgroups->with($relation);

                    // Also include number of count if consumer ask it
                    if (in_array($relation, $withCount)) {
                        $countRelation = $relation . 'Number';
                        $mallgroups->with($countRelation);
                    }
                }
            });

            $_mallgroups = clone $mallgroups;

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
            $mallgroups->take($take);

            $skip = 0;
            OrbitInput::get('skip', function ($_skip) use (&$skip, $mallgroups) {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $mallgroups->skip($skip);

            // Default sort by
            $sortBy = 'merchants.name';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function ($_sortBy) use (&$sortBy) {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'merchant_omid'        => 'merchants.omid',
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
                    'total_mall'           => 'total_mall',
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function ($_sortMode) use (&$sortMode) {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });
            $mallgroups->orderBy($sortBy, $sortMode);

            $totalRec = RecordCounter::create($_mallgroups)->count();
            $listOfRec = $mallgroups->get();

            $data = new stdclass();
            $data->total_records = $totalRec;
            $data->returned_records = count($listOfRec);
            $data->records = $listOfRec;

            if ($totalRec === 0) {
                $data->records = null;
                $this->response->message = Lang::get('statuses.orbit.nodata.mallgroup');
            }

            $this->response->data = $data;

        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.mallgroup.getsearchmallgroup.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.mallgroup.getsearchmallgroup.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.mallgroup.getsearchmallgroup.query.error', array($this, $e));

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
            Event::fire('orbit.mallgroup.getsearchmallgroup.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }
        $output = $this->render($httpCode);
        Event::fire('orbit.mallgroup.getsearchmallgroup.before.render', array($this, &$output));

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
     * @param integer    `current_mall`             (required) - Mall group ID
     * @param string     `email`                    (optional) - Email address of the merchant
     * @param string     `omid`                     (optional) - OMID
     * @param string     `name`                     (optional) - Name of the merchant
     * @param string     `description`              (optional) - Merchant description
     * @param string     `address_line1`            (optional) - Address 1
     * @param string     `address_line2`            (optional) - Address 2
     * @param string     `address_line3`            (optional) - Address 3
     * @param integer    `postal_code`              (optional) - Postal code
     * @param integer    `city_id`                  (optional) - City id
     * @param string     `city`                     (optional) - Name of the city
     * @param string     `country`                  (optional) - Country ID
     * @param string     `phone`                    (optional) - Phone of the merchant
     * @param string     `fax`                      (optional) - Fax of the merchant
     * @param string     `start_date_activity`      (optional) - Start date activity of the merchant
     * @param string     `end_date_activity`        (optional) - End date activity of the merchant
     * @param string     `status`                   (optional) - Status of the merchant
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
     * @param string     `url`                      (optional) - URL
     * @param string     `masterbox_number`         (optional) - Masterbox number
     * @param string     `slavebox_number`          (optional) - Slavebox number
     * @param string     `mobile_default_language`  (optional) - Mobile default language
     * @param string     `pos_language`             (optional) - POS language
     * @param string     `logo`                     (optional) - logo
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postUpdateMallGroup()
    {
        $activity = Activity::portal()
                           ->setActivityType('update');

        $user = NULL;
        $updatedmallgroup = NULL;
        try {
            $httpCode=200;

            Event::fire('orbit.mallgroup.postupdatemallgroup.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.mallgroup.postupdatemallgroup.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.mallgroup.postupdatemallgroup.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('update_mall_group')) {
                Event::fire('orbit.mallgroup.postupdatemallgroup.authz.notallowed', array($this, $user));
                $updateMallGroupLang = Lang::get('validation.orbit.actionlist.update_mall_group');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $updateMallGroupLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.mallgroup.postupdatemallgroup.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $merchant_id = OrbitInput::post('current_mall');;
            // $user_id = OrbitInput::post('user_id');
            $email = OrbitInput::post('email');
            $status = OrbitInput::post('status');
            $omid = OrbitInput::post('omid');
            $url = OrbitInput::post('url');
            $password = OrbitInput::post('password');
            $password2 = OrbitInput::post('password_confirmation');

            $validator = Validator::make(
                array(
                    'current_mall'      => $merchant_id,
                    // 'user_id'           => $user_id,
                    'email'             => $email,
                    'status'            => $status,
                    'omid'              => $omid,
                    'url'               => $url,
                    'password'                => $password,
                    'password_confirmation'   => $password2,
                ),
                array(
                    'current_mall'      => 'required|orbit.empty.mallgroup',
                    // 'user_id'           => 'orbit.empty.user',
                    'email'             => 'email|email_exists_but_me',
                    'status'            => 'orbit.empty.mall_status',
                    'omid'              => 'omid_exists_but_me',
                    'url'               => 'orbit.formaterror.url.web',
                    'password'                => 'min:6|confirmed'
                ),
                array(
                   'email_exists_but_me'      => Lang::get('validation.orbit.exists.email'),
                   'omid_exists_but_me'       => Lang::get('validation.orbit.exists.omid'),
               )
            );

            Event::fire('orbit.mallgroup.postupdatemallgroup.before.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.mallgroup.postupdatemallgroup.after.validation', array($this, $validator));

            $updatedmallgroup = MallGroup::with('taxes')->excludeDeleted()->allowedForUser($user)->where('merchant_id', $merchant_id)->first();

            $updatedUser = User::excludeDeleted()
                            ->where('user_id', '=', $updatedmallgroup->user_id)
                            ->first();

            OrbitInput::post('password', function($password) use ($updatedUser) {
                if (! empty(trim($password))) {
                    $updatedUser->user_password = Hash::make($password);
                }
            });

            $updatedUser->modified_by = $this->api->user->user_id;

            Event::fire('orbit.mallgroup.postupdateuser.before.save', array($this, $updatedUser));

            $updatedUser->save();

            OrbitInput::post('omid', function($omid) use ($updatedmallgroup) {
                $updatedmallgroup->omid = $omid;
            });

            OrbitInput::post('email', function($email) use ($updatedmallgroup) {
                $updatedmallgroup->email = $email;
            });

            OrbitInput::post('name', function($name) use ($updatedmallgroup) {
                $updatedmallgroup->name = $name;
            });

            OrbitInput::post('description', function($description) use ($updatedmallgroup) {
                $updatedmallgroup->description = $description;
            });

            OrbitInput::post('address_line1', function($address_line1) use ($updatedmallgroup) {
                $updatedmallgroup->address_line1 = $address_line1;
            });

            OrbitInput::post('address_line2', function($address_line2) use ($updatedmallgroup) {
                $updatedmallgroup->address_line2 = $address_line2;
            });

            OrbitInput::post('address_line3', function($address_line3) use ($updatedmallgroup) {
                $updatedmallgroup->address_line3 = $address_line3;
            });

            OrbitInput::post('postal_code', function($postal_code) use ($updatedmallgroup) {
                $updatedmallgroup->postal_code = $postal_code;
            });

            OrbitInput::post('city_id', function($city_id) use ($updatedmallgroup) {
                $updatedmallgroup->city_id = $city_id;
            });

            OrbitInput::post('city', function($city) use ($updatedmallgroup) {
                $updatedmallgroup->city = $city;
            });

            OrbitInput::post('province', function($province) use ($updatedmallgroup) {
                $updatedmallgroup->province = $province;
            });

            OrbitInput::post('country', function($country_id) use ($updatedmallgroup) {
                $countryName = '';
                $countryObject = Country::find($country_id);
                if (is_object($countryObject)) {
                    $countryName = $countryObject->name;
                }

                $updatedmallgroup->country_id = $country_id;
                $updatedmallgroup->country = $countryName;
            });

            OrbitInput::post('phone', function($phone) use ($updatedmallgroup) {
                $updatedmallgroup->phone = $phone;
            });

            OrbitInput::post('fax', function($fax) use ($updatedmallgroup) {
                $updatedmallgroup->fax = $fax;
            });

            OrbitInput::post('start_date_activity', function($start_date_activity) use ($updatedmallgroup) {
                $updatedmallgroup->start_date_activity = $start_date_activity;
            });

            OrbitInput::post('end_date_activity', function($end_date_activity) use ($updatedmallgroup) {
                $updatedmallgroup->end_date_activity = $end_date_activity;
            });

            OrbitInput::post('status', function($status) use ($updatedmallgroup) {
                $updatedmallgroup->status = $status;
            });

            OrbitInput::post('logo', function($logo) use ($updatedmallgroup) {
                $updatedmallgroup->logo = $logo;
            });

            OrbitInput::post('currency', function($currency) use ($updatedmallgroup) {
                $updatedmallgroup->currency = $currency;
            });

            OrbitInput::post('currency_symbol', function($currency_symbol) use ($updatedmallgroup) {
                $updatedmallgroup->currency_symbol = $currency_symbol;
            });

            OrbitInput::post('tax_code1', function($tax_code1) use ($updatedmallgroup) {
                $updatedmallgroup->tax_code1 = $tax_code1;
            });

            OrbitInput::post('tax_code2', function($tax_code2) use ($updatedmallgroup) {
                $updatedmallgroup->tax_code2 = $tax_code2;
            });

            OrbitInput::post('tax_code3', function($tax_code3) use ($updatedmallgroup) {
                $updatedmallgroup->tax_code3 = $tax_code3;
            });

            OrbitInput::post('slogan', function($slogan) use ($updatedmallgroup) {
                $updatedmallgroup->slogan = $slogan;
            });

            OrbitInput::post('vat_included', function($vat_included) use ($updatedmallgroup) {
                $updatedmallgroup->vat_included = $vat_included;
            });

            OrbitInput::post('contact_person_firstname', function($contact_person_firstname) use ($updatedmallgroup) {
                $updatedmallgroup->contact_person_firstname = $contact_person_firstname;
            });

            OrbitInput::post('contact_person_lastname', function($contact_person_lastname) use ($updatedmallgroup) {
                $updatedmallgroup->contact_person_lastname = $contact_person_lastname;
            });

            OrbitInput::post('contact_person_position', function($contact_person_position) use ($updatedmallgroup) {
                $updatedmallgroup->contact_person_position = $contact_person_position;
            });

            OrbitInput::post('contact_person_phone', function($contact_person_phone) use ($updatedmallgroup) {
                $updatedmallgroup->contact_person_phone = $contact_person_phone;
            });

            OrbitInput::post('contact_person_phone2', function($contact_person_phone2) use ($updatedmallgroup) {
                $updatedmallgroup->contact_person_phone2 = $contact_person_phone2;
            });

            OrbitInput::post('contact_person_email', function($contact_person_email) use ($updatedmallgroup) {
                $updatedmallgroup->contact_person_email = $contact_person_email;
            });

            OrbitInput::post('sector_of_activity', function($sector_of_activity) use ($updatedmallgroup) {
                $updatedmallgroup->sector_of_activity = $sector_of_activity;
            });

            OrbitInput::post('url', function($url) use ($updatedmallgroup) {
                $updatedmallgroup->url = $url;
            });

            OrbitInput::post('masterbox_number', function($masterbox_number) use ($updatedmallgroup) {
                $updatedmallgroup->masterbox_number = $masterbox_number;
            });

            OrbitInput::post('slavebox_number', function($slavebox_number) use ($updatedmallgroup) {
                $updatedmallgroup->slavebox_number = $slavebox_number;
            });

            OrbitInput::post('mobile_default_language', function($mobile_default_language) use ($updatedmallgroup) {
                $updatedmallgroup->mobile_default_language = $mobile_default_language;
            });

            OrbitInput::post('pos_language', function($pos_language) use ($updatedmallgroup) {
                if (trim($pos_language) === '') {
                    $pos_language = NULL;
                }
                $updatedmallgroup->pos_language = $pos_language;
            });

            OrbitInput::post('logo', function($logo) use ($updatedmallgroup) {
                // do nothing
            });

            $updatedmallgroup->modified_by = $this->api->user->user_id;

            Event::fire('orbit.mallgroup.postupdatemall.before.save', array($this, $updatedmallgroup));

            $updatedmallgroup->save();

            // update user status
            OrbitInput::post('status', function($status) use ($updatedmallgroup) {
                $updateuser = User::with(array('role'))->excludeDeleted()->find($updatedmallgroup->user_id);
                if (! $updateuser->isSuperAdmin()) {
                    $updateuser->status = $status;
                    $updateuser->modified_by = $this->api->user->user_id;

                    $updateuser->save();
                }
            });

            Event::fire('orbit.mallgroup.postupdatemallgroup.after.save', array($this, $updatedmallgroup));
            $this->response->data = $updatedmallgroup;

            // Commit the changes
            $this->commit();

            // Successfull Update
            $activityNotes = sprintf('Mall Group updated: %s', $updatedmallgroup->name);
            $activity->setUser($user)
                    ->setActivityName('update_mall_group')
                    ->setActivityNameLong('Update Mall Group OK')
                    ->setObject($updatedmallgroup)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.mallgroup.postupdatemallgroup.after.commit', array($this, $updatedmallgroup));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.mallgroup.postupdatemallgroup.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_mall_group')
                    ->setActivityNameLong('Update Mall Group Failed')
                    ->setObject($updatedmallgroup)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.mallgroup.postupdatemallgroup.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_mall_group')
                    ->setActivityNameLong('Update Mall Group Failed')
                    ->setObject($updatedmallgroup)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.mallgroup.postupdatemallgroup.query.error', array($this, $e));

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
                    ->setActivityName('update_mall_group')
                    ->setActivityNameLong('Update Mall Group Failed')
                    ->setObject($updatedmallgroup)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.mallgroup.postupdatemallgroup.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_mall_group')
                    ->setActivityNameLong('Update Mall Group Failed')
                    ->setObject($updatedmallgroup)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save activity
        $activity->save();

        return $this->render($httpCode);
    }

    /**
     * POST - Delete Mall Group
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @author Kadek <kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `merchant_id`                 (required) - Mall group ID
     * @param string     `password`                    (required) - Password of the user for confirmation
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeleteMallGroup()
    {
        $activity = Activity::portal()
                          ->setActivityType('delete');

        $user = NULL;
        $deletemallgroup = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.mallgroup.postdeletemallgroup.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.mallgroup.postdeletemallgroup.after.auth', array($this));

            // Try to check access control list, does this merchant allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.mallgroup.postdeletemallgroup.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('delete_mall_group')) {
                Event::fire('orbit.mallgroup.postdeletemallgroup.authz.notallowed', array($this, $user));
                $deleteMallGroupLang = Lang::get('validation.orbit.actionlist.delete_mall_group');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $deleteMallGroupLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.mallgroup.postdeletemallgroup.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $merchant_id = OrbitInput::post('merchant_id');;
            $password = OrbitInput::post('password');

            $validator = Validator::make(
                array(
                    'merchant_id'  => $merchant_id,
                    'password'     => $password,
                ),
                array(
                    'merchant_id'  => 'required|orbit.empty.mallgroup|orbit.exists.mallgroup_have_mall',
                    'password'     => 'required|orbit.access.wrongpassword',
                )
            );

            Event::fire('orbit.mallgroup.postdeletemallgroup.before.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.mallgroup.postdeletemallgroup.after.validation', array($this, $validator));

            // soft delete merchant.
            $deletemallgroup = MallGroup::excludeDeleted()->allowedForUser($user)->where('merchant_id', $merchant_id)->first();
            $deletemallgroup->status = 'deleted';
            $deletemallgroup->modified_by = $this->api->user->user_id;

            Event::fire('orbit.mallgroup.postdeletemallgroup.before.save', array($this, $deletemallgroup));

            $deletemallgroup->save();

            // soft delete user.
            $deleteuser = User::with(array('apikey', 'role'))->excludeDeleted()->find($deletemallgroup->user_id);
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

            Event::fire('orbit.mallgroup.postdeletemallgroup.after.save', array($this, $deletemallgroup));
            $this->response->data = null;
            $this->response->message = Lang::get('statuses.orbit.deleted.mallgroup');

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activityNotes = sprintf('Mall Group Deleted: %s', $deletemallgroup->name);
            $activity->setUser($user)
                    ->setActivityName('delete_mall')
                    ->setActivityNameLong('Delete Mall OK')
                    ->setObject($deletemallgroup)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.mallgroup.postdeletemallgroup.after.commit', array($this, $deletemallgroup));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.mallgroup.postdeletemallgroup.access.forbidden', array($this, $e));

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
                    ->setObject($deletemallgroup)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.mallgroup.postdeletemallgroup.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_mall_group')
                    ->setActivityNameLong('Delete Mall Group Failed')
                    ->setObject($deletemallgroup)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.mallgroup.postdeletemallgroup.query.error', array($this, $e));

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
                    ->setActivityName('delete_mall_group')
                    ->setActivityNameLong('Delete Mall Group Failed')
                    ->setObject($deletemallgroup)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.mallgroup.postdeletemallgroup.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_mall_group')
                    ->setActivityNameLong('Delete Mall Group Failed')
                    ->setObject($deletemallgroup)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.mallgroup.postdeletemallgroup.before.render', array($this, $output));

        // Save the activity
        $activity->save();

        return $output;
    }

    protected function registerCustomValidation()
    {
        // Check the existance of merchant id
        $user = $this->api->user;
        Validator::extend('orbit.empty.mallgroup', function ($attribute, $value, $parameters) use ($user) {
            $mall = MallGroup::excludeDeleted()
                        ->where('merchant_id', $value)
                        ->first();

            if (empty($mall)) {
                return FALSE;
            }

            App::instance('orbit.empty.mallgroup', $mall);

            return TRUE;
        });

        // Check user email address, it should not exists
        Validator::extend('orbit.exists.email', function ($attribute, $value, $parameters) {
            $mall = MallGroup::excludeDeleted()
                        ->where('email', $value)
                        ->first();

            if (! empty($mall)) {
                return FALSE;
            }

            App::instance('orbit.validation.mallgroup', $mall);

            return TRUE;
        });

        // Check country not empty
        Validator::extend('orbit.empty.country', function ($attribute, $value, $parameters) {
            $country = Country::where('country_id', $value)
                        ->first();

            if (empty($country)) {
                return FALSE;
            }

            App::instance('orbit.empty.country', $country);

            return TRUE;
        });

        // Check user email address, it should not exists (for update)
        Validator::extend('email_exists_but_me', function ($attribute, $value, $parameters) {
            $mall_id = OrbitInput::post('current_mall');;
            $mall = MallGroup::excludeDeleted()
                        ->where('email', $value)
                        ->where('merchant_id', '!=', $mall_id)
                        ->first();

            if (! empty($mall)) {
                return FALSE;
            }

            App::instance('orbit.validation.mallgroup', $mall);

            return TRUE;
        });

        // Check OMID, it should not exists (for update)
        Validator::extend('omid_exists_but_me', function ($attribute, $value, $parameters) {
            $mall_id = OrbitInput::post('current_mall');;
            $mall = MallGroup::excludeDeleted()
                        ->where('omid', $value)
                        ->where('merchant_id', '!=', $mall_id)
                        ->first();

            if (! empty($mall)) {
                return FALSE;
            }

            App::instance('orbit.validation.mallgroup', $mall);

            return TRUE;
        });

        // Check omid, it should not exists
        Validator::extend('orbit.exists.omid', function ($attribute, $value, $parameters) {
            $mall = MallGroup::excludeDeleted()
                        ->where('omid', $value)
                        ->first();

            if (! empty($mall)) {
                return FALSE;
            }

            App::instance('orbit.validation.mallgroup', $mall);

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

            App::instance('orbit.validation.mallgroup', $value);

            return FALSE;
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
        Validator::extend('orbit.exists.mallgroup_have_mall', function ($attribute, $value, $parameters) {
            $tenant = Mall::excludeDeleted()
                            ->where('parent_id', $value)
                            ->first();
            if (! empty($tenant)) {
                return FALSE;
            }

            App::instance('orbit.exists.mallgroup_have_mall', $tenant);

            return TRUE;
        });
    }
}
