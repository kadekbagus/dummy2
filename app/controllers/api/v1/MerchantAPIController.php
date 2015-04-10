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

class MerchantAPIController extends ControllerAPI
{
    /**
     * POST - Delete Merchant
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
    public function postDeleteMerchant()
    {
        $activity = Activity::portal()
                          ->setActivityType('delete');

        $user = NULL;
        $deletemerchant = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.merchant.postdeletemerchant.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.merchant.postdeletemerchant.after.auth', array($this));

            // Try to check access control list, does this merchant allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.merchant.postdeletemerchant.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('delete_merchant')) {
                Event::fire('orbit.merchant.postdeletemerchant.authz.notallowed', array($this, $user));
                $deleteMerchantLang = Lang::get('validation.orbit.actionlist.delete_merchant');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $deleteMerchantLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.merchant.postdeletemerchant.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $merchant_id = OrbitInput::post('merchant_id');
            $password = OrbitInput::post('password');

            $validator = Validator::make(
                array(
                    'merchant_id' => $merchant_id,
                    'password'    => $password,
                ),
                array(
                    'merchant_id' => 'required|numeric|orbit.empty.merchant|orbit.exists.merchant_have_retailer',
                    'password'    => 'required|orbit.access.wrongpassword',
                )
            );

            Event::fire('orbit.merchant.postdeletemerchant.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.merchant.postdeletemerchant.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // soft delete merchant.
            $deletemerchant = Merchant::excludeDeleted()->allowedForUser($user)->where('merchant_id', $merchant_id)->first();
            $deletemerchant->status = 'deleted';
            $deletemerchant->modified_by = $this->api->user->user_id;

            Event::fire('orbit.merchant.postdeletemerchant.before.save', array($this, $deletemerchant));

            $deletemerchant->save();

            // soft delete user.
            $deleteuser = User::with(array('apikey', 'role'))->excludeDeleted()->find($deletemerchant->user_id);
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

            Event::fire('orbit.merchant.postdeletemerchant.after.save', array($this, $deletemerchant));
            $this->response->data = null;
            $this->response->message = Lang::get('statuses.orbit.deleted.merchant');

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activityNotes = sprintf('Merchant Deleted: %s', $deletemerchant->name);
            $activity->setUser($user)
                    ->setActivityName('delete_merchant')
                    ->setActivityNameLong('Delete Merchant OK')
                    ->setObject($deletemerchant)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.merchant.postdeletemerchant.after.commit', array($this, $deletemerchant));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.merchant.postdeletemerchant.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_merchant')
                    ->setActivityNameLong('Delete Merchant Failed')
                    ->setObject($deletemerchant)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.merchant.postdeletemerchant.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_merchant')
                    ->setActivityNameLong('Delete Merchant Failed')
                    ->setObject($deletemerchant)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.merchant.postdeletemerchant.query.error', array($this, $e));

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
                    ->setActivityName('delete_merchant')
                    ->setActivityNameLong('Delete Merchant Failed')
                    ->setObject($deletemerchant)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.merchant.postdeletemerchant.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_merchant')
                    ->setActivityNameLong('Delete Merchant Failed')
                    ->setObject($deletemerchant)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.merchant.postdeletemerchant.before.render', array($this, $output));

        // Save the activity
        $activity->save();

        return $output;
    }

     /**
     * POST - Add new merchant
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
    public function postNewMerchant()
    {
        $activity = Activity::portal()
                            ->setActivityType('create');

        $user = NULL;
        $newmerchant = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.merchant.postnewmerchant.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.merchant.postnewmerchant.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.merchant.postnewmerchant.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('create_merchant')) {
                Event::fire('orbit.merchant.postnewmerchant.authz.notallowed', array($this, $user));
                $createMerchantLang = Lang::get('validation.orbit.actionlist.new_merchant');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $createMerchantLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.merchant.postnewmerchant.after.authz', array($this, $user));

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
                    'status'        => 'required|orbit.empty.merchant_status',
                    'country'       => 'required|numeric',
                    'url'           => 'orbit.formaterror.url.web'
                )
            );

            Event::fire('orbit.merchant.postnewmerchant.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.merchant.postnewmerchant.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            $roleMerchant = Role::where('role_name', 'merchant owner')->first();
            if (empty($roleMerchant)) {
                OrbitShopAPI::throwInvalidArgument('Could not find role named "Merchant Owner".');
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
            $countryObject = Country::find($country);
            if (is_object($countryObject)) {
                $countryName = $countryObject->name;
            }

            $newmerchant = new Merchant();
            $newmerchant->user_id = $newuser->user_id;
            $newmerchant->orid = '';
            $newmerchant->email = $email;
            $newmerchant->name = $name;
            $newmerchant->description = $description;
            $newmerchant->address_line1 = $address_line1;
            $newmerchant->address_line2 = $address_line2;
            $newmerchant->address_line3 = $address_line3;
            $newmerchant->postal_code = $postal_code;
            $newmerchant->city_id = $city_id;
            $newmerchant->city = $city;
            $newmerchant->country_id = $country;
            $newmerchant->country = $countryName;
            $newmerchant->phone = $phone;
            $newmerchant->fax = $fax;
            $newmerchant->start_date_activity = $start_date_activity;
            $newmerchant->end_date_activity = $end_date_activity;
            $newmerchant->status = $status;
            $newmerchant->logo = $logo;
            $newmerchant->currency = $currency;
            $newmerchant->currency_symbol = $currency_symbol;
            $newmerchant->tax_code1 = $tax_code1;
            $newmerchant->tax_code2 = $tax_code2;
            $newmerchant->tax_code3 = $tax_code3;
            $newmerchant->slogan = $slogan;
            $newmerchant->vat_included = $vat_included;
            $newmerchant->contact_person_firstname = $contact_person_firstname;
            $newmerchant->contact_person_lastname = $contact_person_lastname;
            $newmerchant->contact_person_position = $contact_person_position;
            $newmerchant->contact_person_phone = $contact_person_phone;
            $newmerchant->contact_person_phone2 = $contact_person_phone2;
            $newmerchant->contact_person_email = $contact_person_email;
            $newmerchant->sector_of_activity = $sector_of_activity;
            $newmerchant->object_type = $object_type;
            $newmerchant->parent_id = $parent_id;
            $newmerchant->url = $url;
            $newmerchant->masterbox_number = $masterbox_number;
            $newmerchant->slavebox_number = $slavebox_number;
            $newmerchant->mobile_default_language = $mobile_default_language;
            $newmerchant->pos_language = $pos_language;
            $newmerchant->modified_by = $this->api->user->user_id;

            Event::fire('orbit.merchant.postnewmerchant.before.save', array($this, $newmerchant));

            $newmerchant->save();

            // add omid to newly created merchant
            $newmerchant->omid = Merchant::OMID_INCREMENT + $newmerchant->merchant_id;
            $newmerchant->save();

            Event::fire('orbit.merchant.postnewmerchant.after.save', array($this, $newmerchant));
            $this->response->data = $newmerchant;

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activityNotes = sprintf('Merchant Created: %s', $newmerchant->name);
            $activity->setUser($user)
                    ->setActivityName('create_merchant')
                    ->setActivityNameLong('Create Merchant OK')
                    ->setObject($newmerchant)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.merchant.postnewmerchant.after.commit', array($this, $newmerchant));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.merchant.postnewmerchant.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_merchant')
                    ->setActivityNameLong('Create Merchant Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.merchant.postnewmerchant.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_merchant')
                    ->setActivityNameLong('Create Merchant Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.merchant.postnewmerchant.query.error', array($this, $e));

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
                    ->setActivityName('create_merchant')
                    ->setActivityNameLong('Create Merchant Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.merchant.postnewmerchant.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_merchant')
                    ->setActivityNameLong('Create Merchant Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save the activity
        $activity->save();

        return $this->render($httpCode);
    }

    /**
     * GET - Search merchant
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param string            `sort_by`                       (optional) - column order by
     * @param string            `sort_mode`                     (optional) - asc or desc
     * @param integer           `take`                          (optional) - limit
     * @param integer           `skip`                          (optional) - limit offset
     * @param integer           `merchant_id`                   (optional)
     * @param string            `omid`                          (optional)
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
    public function getSearchMerchant()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.merchant.getsearchmerchant.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.merchant.getsearchmerchant.after.auth', array($this));

            // Try to check access control list, does this merchant allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.merchant.getsearchmerchant.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('view_merchant')) {
                Event::fire('orbit.merchant.getsearchmerchant.authz.notallowed', array($this, $user));
                $viewUserLang = Lang::get('validation.orbit.actionlist.view_merchant');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewUserLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.merchant.getsearchmerchant.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sortby');
            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                ),
                array(
                    'sort_by' => 'in:merchant_omid,registered_date,merchant_name,merchant_email,merchant_userid,merchant_description,merchantid,merchant_address1,merchant_address2,merchant_address3,merchant_cityid,merchant_city,merchant_countryid,merchant_country,merchant_phone,merchant_fax,merchant_status,merchant_currency,start_date_activity,total_retailer',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.merchant_sortby'),
                )
            );

            Event::fire('orbit.merchant.getsearchmerchant.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            Event::fire('orbit.merchant.getsearchmerchant.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.merchant.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.merchant.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            $merchants = Merchant::excludeDeleted('merchants')
                                ->allowedForUser($user)
                                ->select('merchants.*', DB::raw('count(retailer.merchant_id) AS total_retailer'))
                                ->leftJoin('merchants AS retailer', function($join) {
                                        $join->on(DB::raw('retailer.parent_id'), '=', 'merchants.merchant_id')
                                            ->where(DB::raw('retailer.status'), '!=', 'deleted');
                                    })
                                ->groupBy('merchants.merchant_id');

            // Filter merchant by Ids
            OrbitInput::get('merchant_id', function ($merchantIds) use ($merchants) {
                $merchants->whereIn('merchants.merchant_id', $merchantIds);
            });

            // Filter merchant by omid
            OrbitInput::get('omid', function ($omid) use ($merchants) {
                $merchants->whereIn('merchants.omid', $omid);
            });

            // Filter merchant by user Ids
            OrbitInput::get('user_id', function ($userIds) use ($merchants) {
                $merchants->whereIn('merchants.user_id', $userIds);
            });

            // Filter merchant by name
            OrbitInput::get('name', function ($name) use ($merchants) {
                $merchants->whereIn('merchants.name', $name);
            });

            // Filter merchant by name pattern
            OrbitInput::get('name_like', function ($name) use ($merchants) {
                $merchants->where('merchants.name', 'like', "%$name%");
            });

            // Filter merchant by description
            OrbitInput::get('description', function ($description) use ($merchants) {
                $merchants->whereIn('merchants.description', $description);
            });

            // Filter merchant by description pattern
            OrbitInput::get('description_like', function ($description) use ($merchants) {
                $merchants->where('merchants.description', 'like', "%$description%");
            });

            // Filter merchant by email
            OrbitInput::get('email', function ($email) use ($merchants) {
                $merchants->whereIn('merchants.email', $email);
            });

            // Filter merchant by email pattern
            OrbitInput::get('email_like', function ($email) use ($merchants) {
                $merchants->where('merchants.email', 'like', "%$email%");
            });

            // Filter merchant by address1
            OrbitInput::get('address1', function ($address1) use ($merchants) {
                $merchants->whereIn('merchants.address_line1', $address1);
            });

            // Filter merchant by address1 pattern
            OrbitInput::get('address1_like', function ($address1) use ($merchants) {
                $merchants->where('merchants.address_line1', 'like', "%$address1%");
            });

            // Filter merchant by address2
            OrbitInput::get('address2', function ($address2) use ($merchants) {
                $merchants->whereIn('merchants.address_line2', $address2);
            });

            // Filter merchant by address2 pattern
            OrbitInput::get('address2_like', function ($address2) use ($merchants) {
                $merchants->where('merchants.address_line2', 'like', "%$address2%");
            });

            // Filter merchant by address3
            OrbitInput::get('address3', function ($address3) use ($merchants) {
                $merchants->whereIn('merchants.address_line3', $address3);
            });

            // Filter merchant by address3 pattern
            OrbitInput::get('address3_like', function ($address3) use ($merchants) {
                $merchants->where('merchants.address_line3', 'like', "%$address3%");
            });

            // Filter merchant by postal code
            OrbitInput::get('postal_code', function ($postalcode) use ($merchants) {
                $merchants->whereIn('merchants.postal_code', $postalcode);
            });

            // Filter merchant by cityID
            OrbitInput::get('city_id', function ($cityIds) use ($merchants) {
                $merchants->whereIn('merchants.city_id', $cityIds);
            });

            // Filter merchant by city
            OrbitInput::get('city', function ($city) use ($merchants) {
                $merchants->whereIn('merchants.city', $city);
            });

            // Filter merchant by city pattern
            OrbitInput::get('city_like', function ($city) use ($merchants) {
                $merchants->where('merchants.city', 'like', "%$city%");
            });

            // Filter merchant by countryID
            OrbitInput::get('country_id', function ($countryId) use ($merchants) {
                $merchants->whereIn('merchants.country_id', $countryId);
            });

            // Filter merchant by country
            OrbitInput::get('country', function ($country) use ($merchants) {
                $merchants->whereIn('merchants.country', $country);
            });

            // Filter merchant by country pattern
            OrbitInput::get('country_like', function ($country) use ($merchants) {
                $merchants->where('merchants.country', 'like', "%$country%");
            });

            // Filter merchant by phone
            OrbitInput::get('phone', function ($phone) use ($merchants) {
                $merchants->whereIn('merchants.phone', $phone);
            });

            // Filter merchant by fax
            OrbitInput::get('fax', function ($fax) use ($merchants) {
                $merchants->whereIn('merchants.fax', $fax);
            });

            // Filter merchant by status
            OrbitInput::get('status', function ($status) use ($merchants) {
                $merchants->whereIn('merchants.status', $status);
            });

            // Filter merchant by currency
            OrbitInput::get('currency', function ($currency) use ($merchants) {
                $merchants->whereIn('merchants.currency', $currency);
            });

            // Filter merchant by contact person firstname
            OrbitInput::get('contact_person_firstname', function ($contact_person_firstname) use ($merchants) {
                $merchants->whereIn('merchants.contact_person_firstname', $contact_person_firstname);
            });

            // Filter merchant by contact person firstname like
            OrbitInput::get('contact_person_firstname_like', function ($contact_person_firstname) use ($merchants) {
                $merchants->where('merchants.contact_person_firstname', 'like', "%$contact_person_firstname%");
            });

            // Filter merchant by contact person lastname
            OrbitInput::get('contact_person_lastname', function ($contact_person_lastname) use ($merchants) {
                $merchants->whereIn('merchants.contact_person_lastname', $contact_person_lastname);
            });

            // Filter merchant by contact person lastname like
            OrbitInput::get('contact_person_lastname_like', function ($contact_person_lastname) use ($merchants) {
                $merchants->where('merchants.contact_person_lastname', 'like', "%$contact_person_lastname%");
            });

            // Filter merchant by contact person position
            OrbitInput::get('contact_person_position', function ($contact_person_position) use ($merchants) {
                $merchants->whereIn('merchants.contact_person_position', $contact_person_position);
            });

            // Filter merchant by contact person position like
            OrbitInput::get('contact_person_position_like', function ($contact_person_position) use ($merchants) {
                $merchants->where('merchants.contact_person_position', 'like', "%$contact_person_position%");
            });

            // Filter merchant by contact person phone
            OrbitInput::get('contact_person_phone', function ($contact_person_phone) use ($merchants) {
                $merchants->whereIn('merchants.contact_person_phone', $contact_person_phone);
            });

            // Filter merchant by contact person phone2
            OrbitInput::get('contact_person_phone2', function ($contact_person_phone2) use ($merchants) {
                $merchants->whereIn('merchants.contact_person_phone2', $contact_person_phone2);
            });

            // Filter merchant by contact person email
            OrbitInput::get('contact_person_email', function ($contact_person_email) use ($merchants) {
                $merchants->whereIn('merchants.contact_person_email', $contact_person_email);
            });

            // Filter merchant by sector of activity
            OrbitInput::get('sector_of_activity', function ($sector_of_activity) use ($merchants) {
                $merchants->whereIn('merchants.sector_of_activity', $sector_of_activity);
            });

            // Filter merchant by url
            OrbitInput::get('url', function ($url) use ($merchants) {
                $merchants->whereIn('merchants.url', $url);
            });

            // Filter merchant by masterbox_number
            OrbitInput::get('masterbox_number', function ($masterbox_number) use ($merchants) {
                $merchants->whereIn('merchants.masterbox_number', $masterbox_number);
            });

            // Filter merchant by slavebox_number
            OrbitInput::get('slavebox_number', function ($slavebox_number) use ($merchants) {
                $merchants->whereIn('merchants.slavebox_number', $slavebox_number);
            });

            // Filter merchant by mobile_default_language
            OrbitInput::get('mobile_default_language', function ($mobile_default_language) use ($merchants) {
                $merchants->whereIn('merchants.mobile_default_language', $mobile_default_language);
            });

            // Filter merchant by pos_language
            OrbitInput::get('pos_language', function ($pos_language) use ($merchants) {
                $merchants->whereIn('merchants.pos_language', $pos_language);
            });

            // Add new relation based on request
            OrbitInput::get('with', function ($with) use ($merchants) {
                $with = (array) $with;

                // Make sure the with_count also in array format
                $withCount = array();
                OrbitInput::get('with_count', function ($_wcount) use (&$withCount) {
                    $withCount = (array) $_wcount;
                });

                foreach ($with as $relation) {
                    $merchants->with($relation);

                    // Also include number of count if consumer ask it
                    if (in_array($relation, $withCount)) {
                        $countRelation = $relation . 'Number';
                        $merchants->with($countRelation);
                    }
                }
            });

            $_merchants = clone $merchants;

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
            $merchants->take($take);

            $skip = 0;
            OrbitInput::get('skip', function ($_skip) use (&$skip, $merchants) {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $merchants->skip($skip);

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
                    'total_retailer'       => 'total_retailer',
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function ($_sortMode) use (&$sortMode) {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });
            $merchants->orderBy($sortBy, $sortMode);

            $totalRec = RecordCounter::create($_merchants)->count();
            $listOfRec = $merchants->get();

            $data = new stdclass();
            $data->total_records = $totalRec;
            $data->returned_records = count($listOfRec);
            $data->records = $listOfRec;

            if ($totalRec === 0) {
                $data->records = null;
                $this->response->message = Lang::get('statuses.orbit.nodata.merchant');
            }

            $this->response->data = $data;

        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.merchant.getsearchmerchant.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.merchant.getsearchmerchant.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.merchant.getsearchmerchant.query.error', array($this, $e));

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
            Event::fire('orbit.merchant.getsearchmerchant.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }
        $output = $this->render($httpCode);
        Event::fire('orbit.merchant.getsearchmerchant.before.render', array($this, &$output));

        return $output;
    }

    /**
     * POST - Update merchant
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
    public function postUpdateMerchant()
    {
        $activity = Activity::portal()
                           ->setActivityType('update');

        $user = NULL;
        $updatedmerchant = NULL;
        try {
            $httpCode=200;

            Event::fire('orbit.merchant.postupdatemerchant.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.merchant.postupdatemerchant.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.merchant.postupdatemerchant.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('update_merchant')) {
                Event::fire('orbit.merchant.postupdatemerchant.authz.notallowed', array($this, $user));
                $updateMerchantLang = Lang::get('validation.orbit.actionlist.update_merchant');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $updateMerchantLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.merchant.postupdatemerchant.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $merchant_id = OrbitInput::post('merchant_id');
            $user_id = OrbitInput::post('user_id');
            $email = OrbitInput::post('email');
            $status = OrbitInput::post('status');
            $omid = OrbitInput::post('omid');
            $ticket_header = OrbitInput::post('ticket_header');
            $ticket_footer = OrbitInput::post('ticket_footer');
            $url = OrbitInput::post('url');

            $validator = Validator::make(
                array(
                    'merchant_id'       => $merchant_id,
                    'user_id'           => $user_id,
                    'email'             => $email,
                    'status'            => $status,
                    'omid'              => $omid,
                    'ticket_header'     => $ticket_header,
                    'ticket_footer'     => $ticket_footer,
                    'url'               => $url,
                ),
                array(
                    'merchant_id'       => 'required|numeric|orbit.empty.merchant',
                    'user_id'           => 'numeric|orbit.empty.user',
                    'email'             => 'email|email_exists_but_me',
                    'status'            => 'orbit.empty.merchant_status|orbit.exists.merchant_retailers_is_box_current_retailer:'.$merchant_id,
                    'omid'              => 'omid_exists_but_me',
                    'ticket_header'     => 'ticket_header_max_length',
                    'ticket_footer'     => 'ticket_footer_max_length',
                    'url'               => 'orbit.formaterror.url.web'
                ),
                array(
                   'email_exists_but_me'      => Lang::get('validation.orbit.exists.email'),
                   'omid_exists_but_me'       => Lang::get('validation.orbit.exists.omid'),
                   'ticket_header_max_length' => Lang::get('validation.orbit.formaterror.merchant.ticket_header.max_length'),
                   'ticket_footer_max_length' => Lang::get('validation.orbit.formaterror.merchant.ticket_footer.max_length')
               )
            );

            Event::fire('orbit.merchant.postupdatemerchant.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.merchant.postupdatemerchant.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            $updatedmerchant = Merchant::with('taxes')->excludeDeleted()->allowedForUser($user)->where('merchant_id', $merchant_id)->first();

            OrbitInput::post('omid', function($omid) use ($updatedmerchant) {
                $updatedmerchant->omid = $omid;
            });

            OrbitInput::post('user_id', function($user_id) use ($updatedmerchant) {
                // Right know the interface does not provide a way to change
                // the user so it's better to skip it.
                // $updatedmerchant->user_id = $user_id;
            });

            OrbitInput::post('email', function($email) use ($updatedmerchant) {
                $updatedmerchant->email = $email;
            });

            OrbitInput::post('name', function($name) use ($updatedmerchant) {
                $updatedmerchant->name = $name;
            });

            OrbitInput::post('description', function($description) use ($updatedmerchant) {
                $updatedmerchant->description = $description;
            });

            OrbitInput::post('address_line1', function($address_line1) use ($updatedmerchant) {
                $updatedmerchant->address_line1 = $address_line1;
            });

            OrbitInput::post('address_line2', function($address_line2) use ($updatedmerchant) {
                $updatedmerchant->address_line2 = $address_line2;
            });

            OrbitInput::post('address_line3', function($address_line3) use ($updatedmerchant) {
                $updatedmerchant->address_line3 = $address_line3;
            });

            OrbitInput::post('postal_code', function($postal_code) use ($updatedmerchant) {
                $updatedmerchant->postal_code = $postal_code;
            });

            OrbitInput::post('city_id', function($city_id) use ($updatedmerchant) {
                $updatedmerchant->city_id = $city_id;
            });

            OrbitInput::post('city', function($city) use ($updatedmerchant) {
                $updatedmerchant->city = $city;
            });

            OrbitInput::post('country', function($country_id) use ($updatedmerchant) {
                $countryName = '';
                $countryObject = Country::find($country_id);
                if (is_object($countryObject)) {
                    $countryName = $countryObject->name;
                }

                $updatedmerchant->country_id = $country_id;
                $updatedmerchant->country = $countryName;
            });

            OrbitInput::post('phone', function($phone) use ($updatedmerchant) {
                $updatedmerchant->phone = $phone;
            });

            OrbitInput::post('fax', function($fax) use ($updatedmerchant) {
                $updatedmerchant->fax = $fax;
            });

            OrbitInput::post('start_date_activity', function($start_date_activity) use ($updatedmerchant) {
                $updatedmerchant->start_date_activity = $start_date_activity;
            });

            OrbitInput::post('end_date_activity', function($end_date_activity) use ($updatedmerchant) {
                $updatedmerchant->end_date_activity = $end_date_activity;
            });

            OrbitInput::post('status', function($status) use ($updatedmerchant) {
                $updatedmerchant->status = $status;
            });

            OrbitInput::post('logo', function($logo) use ($updatedmerchant) {
                $updatedmerchant->logo = $logo;
            });

            OrbitInput::post('currency', function($currency) use ($updatedmerchant) {
                $updatedmerchant->currency = $currency;
            });

            OrbitInput::post('currency_symbol', function($currency_symbol) use ($updatedmerchant) {
                $updatedmerchant->currency_symbol = $currency_symbol;
            });

            OrbitInput::post('tax_code1', function($tax_code1) use ($updatedmerchant) {
                $updatedmerchant->tax_code1 = $tax_code1;
            });

            OrbitInput::post('tax_code2', function($tax_code2) use ($updatedmerchant) {
                $updatedmerchant->tax_code2 = $tax_code2;
            });

            OrbitInput::post('tax_code3', function($tax_code3) use ($updatedmerchant) {
                $updatedmerchant->tax_code3 = $tax_code3;
            });

            OrbitInput::post('slogan', function($slogan) use ($updatedmerchant) {
                $updatedmerchant->slogan = $slogan;
            });

            OrbitInput::post('vat_included', function($vat_included) use ($updatedmerchant) {
                $updatedmerchant->vat_included = $vat_included;
            });

            OrbitInput::post('contact_person_firstname', function($contact_person_firstname) use ($updatedmerchant) {
                $updatedmerchant->contact_person_firstname = $contact_person_firstname;
            });

            OrbitInput::post('contact_person_lastname', function($contact_person_lastname) use ($updatedmerchant) {
                $updatedmerchant->contact_person_lastname = $contact_person_lastname;
            });

            OrbitInput::post('contact_person_position', function($contact_person_position) use ($updatedmerchant) {
                $updatedmerchant->contact_person_position = $contact_person_position;
            });

            OrbitInput::post('contact_person_phone', function($contact_person_phone) use ($updatedmerchant) {
                $updatedmerchant->contact_person_phone = $contact_person_phone;
            });

            OrbitInput::post('contact_person_phone2', function($contact_person_phone2) use ($updatedmerchant) {
                $updatedmerchant->contact_person_phone2 = $contact_person_phone2;
            });

            OrbitInput::post('contact_person_email', function($contact_person_email) use ($updatedmerchant) {
                $updatedmerchant->contact_person_email = $contact_person_email;
            });

            OrbitInput::post('sector_of_activity', function($sector_of_activity) use ($updatedmerchant) {
                $updatedmerchant->sector_of_activity = $sector_of_activity;
            });

            OrbitInput::post('parent_id', function($parent_id) use ($updatedmerchant) {
                $updatedmerchant->parent_id = $parent_id;
            });

            OrbitInput::post('url', function($url) use ($updatedmerchant) {
                $updatedmerchant->url = $url;
            });

            OrbitInput::post('masterbox_number', function($masterbox_number) use ($updatedmerchant) {
                $updatedmerchant->masterbox_number = $masterbox_number;
            });

            OrbitInput::post('slavebox_number', function($slavebox_number) use ($updatedmerchant) {
                $updatedmerchant->slavebox_number = $slavebox_number;
            });

            OrbitInput::post('mobile_default_language', function($mobile_default_language) use ($updatedmerchant) {
                $updatedmerchant->mobile_default_language = $mobile_default_language;
            });

            OrbitInput::post('pos_language', function($pos_language) use ($updatedmerchant) {
                if (trim($pos_language) === '') {
                    $pos_language = NULL;
                }
                $updatedmerchant->pos_language = $pos_language;
            });

            OrbitInput::post('ticket_header', function($ticket_header) use ($updatedmerchant) {
                $updatedmerchant->ticket_header = $ticket_header;
            });

            OrbitInput::post('ticket_footer', function($ticket_footer) use ($updatedmerchant) {
                $updatedmerchant->ticket_footer = $ticket_footer;
            });

            $updatedmerchant->modified_by = $this->api->user->user_id;

            Event::fire('orbit.merchant.postupdatemerchant.before.save', array($this, $updatedmerchant));

            $updatedmerchant->save();

            // update user status
            OrbitInput::post('status', function($status) use ($updatedmerchant) {
                $updateuser = User::with(array('role'))->excludeDeleted()->find($updatedmerchant->user_id);
                if (! $updateuser->isSuperAdmin()) {
                    $updateuser->status = $status;
                    $updateuser->modified_by = $this->api->user->user_id;

                    $updateuser->save();
                }
            });

            // do insert/update/delete merchant_taxes
            OrbitInput::post('merchant_taxes', function($merchant_taxes) use ($updatedmerchant) {
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
                            'tax_name'               => 'required|max:50|tax_name_exists_but_me:'.$merchant_tax['merchant_tax_id'].','.$updatedmerchant->merchant_id,
                            'tax_type'               => 'orbit.empty.tax_type',
                            'is_delete'              => 'orbit.exists.tax_link_to_product:'.$merchant_tax['merchant_tax_id'],
                        ),
                        array(
                            'tax_name_exists_but_me' => Lang::get('validation.orbit.exists.tax_name'),
                        )
                    );

                    Event::fire('orbit.merchant.postupdatemerchant.before.merchanttaxesvalidation', array($this, $validator));

                    // Run the validation
                    if ($validator->fails()) {
                        $errorMessage = $validator->messages()->first();
                        OrbitShopAPI::throwInvalidArgument($errorMessage);
                    }

                    Event::fire('orbit.merchant.postupdatemerchant.after.merchanttaxesvalidation', array($this, $validator));

                    //save merchant_taxes
                    if (trim($merchant_tax['merchant_tax_id']) === '') {
                        // do insert
                        $merchanttax = new MerchantTax();
                        $merchanttax->merchant_id = $updatedmerchant->merchant_id;
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
                $updatedmerchant->load('taxes');
            });

            Event::fire('orbit.merchant.postupdatemerchant.after.save', array($this, $updatedmerchant));
            $this->response->data = $updatedmerchant;

            // Commit the changes
            $this->commit();

            // Successfull Update
            $activityNotes = sprintf('Merchant updated: %s', $updatedmerchant->name);
            $activity->setUser($user)
                    ->setActivityName('update_merchant')
                    ->setActivityNameLong('Update Merchant OK')
                    ->setObject($updatedmerchant)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.merchant.postupdatemerchant.after.commit', array($this, $updatedmerchant));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.merchant.postupdatemerchant.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_merchant')
                    ->setActivityNameLong('Update Merchant Failed')
                    ->setObject($updatedmerchant)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.merchant.postupdatemerchant.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_merchant')
                    ->setActivityNameLong('Update Merchant Failed')
                    ->setObject($updatedmerchant)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.merchant.postupdatemerchant.query.error', array($this, $e));

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
                    ->setActivityName('update_merchant')
                    ->setActivityNameLong('Update Merchant Failed')
                    ->setObject($updatedmerchant)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.merchant.postupdatemerchant.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_merchant')
                    ->setActivityNameLong('Update Merchant Failed')
                    ->setObject($updatedmerchant)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save activity
        $activity->save();

        return $this->render($httpCode);
    }

    protected function registerCustomValidation()
    {
        // Check the existance of merchant id
        $user = $this->api->user;
        Validator::extend('orbit.empty.merchant', function ($attribute, $value, $parameters) use ($user){
            $merchant = Merchant::excludeDeleted()
                        ->allowedForUser($user)
                        ->where('merchant_id', $value)
                        ->first();

            if (empty($merchant)) {
                return FALSE;
            }

            App::instance('orbit.empty.merchant', $merchant);

            return TRUE;
        });

        // Check user email address, it should not exists
        Validator::extend('orbit.exists.email', function ($attribute, $value, $parameters) {
            $merchant = Merchant::excludeDeleted()
                        ->where('email', $value)
                        ->first();

            if (! empty($merchant)) {
                return FALSE;
            }

            App::instance('orbit.validation.merchant', $merchant);

            return TRUE;
        });

        // Check user email address, it should not exists (for update)
        Validator::extend('email_exists_but_me', function ($attribute, $value, $parameters) {
            $merchant_id = OrbitInput::post('merchant_id');
            $merchant = Merchant::excludeDeleted()
                        ->where('email', $value)
                        ->where('merchant_id', '!=', $merchant_id)
                        ->first();

            if (! empty($merchant)) {
                return FALSE;
            }

            App::instance('orbit.validation.merchant', $merchant);

            return TRUE;
        });

        // Check OMID, it should not exists (for update)
        Validator::extend('omid_exists_but_me', function ($attribute, $value, $parameters) {
            $merchant_id = OrbitInput::post('merchant_id');
            $merchant = Merchant::excludeDeleted()
                        ->where('omid', $value)
                        ->where('merchant_id', '!=', $merchant_id)
                        ->first();

            if (! empty($merchant)) {
                return FALSE;
            }

            App::instance('orbit.validation.merchant', $merchant);

            return TRUE;
        });

        // Check omid, it should not exists
        Validator::extend('orbit.exists.omid', function ($attribute, $value, $parameters) {
            $merchant = Merchant::excludeDeleted()
                        ->where('omid', $value)
                        ->first();

            if (! empty($merchant)) {
                return FALSE;
            }

            App::instance('orbit.validation.merchant', $merchant);

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
        Validator::extend('orbit.empty.merchant_status', function ($attribute, $value, $parameters) {
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

            App::instance('orbit.validation.merchant', $value);

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

            App::instance('orbit.formaterror.merchant.ticket_header.max_length', $ticketHeader);

            return TRUE;
        });

        // Check ticket footer max length
        Validator::extend('ticket_footer_max_length', function ($attribute, $value, $parameters) {
            $ticketFooter = LineChecker::create($value)->noMoreThan(40);

            if (!empty($ticketFooter)) {
                return FALSE;
            }

            App::instance('orbit.formaterror.merchant.ticket_footer.max_length', $ticketFooter);

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

        // Check if merchant have retailer.
        Validator::extend('orbit.exists.merchant_have_retailer', function ($attribute, $value, $parameters) {
            $retailer = Retailer::excludeDeleted()
                            ->where('parent_id', $value)
                            ->first();
            if (! empty($retailer)) {
                return FALSE;
            }

            App::instance('orbit.exists.merchant_have_retailer', $retailer);

            return TRUE;
        });

        // if merchant status is updated to inactive, then reject if its retailers is current retailer.
        Validator::extend('orbit.exists.merchant_retailers_is_box_current_retailer', function ($attribute, $value, $parameters) {
            if ($value === 'inactive') {
                $merchant_id = $parameters[0];
                $retailer_id = Setting::where('setting_name', 'current_retailer')->first()->setting_value;
                $currentRetailer = Retailer::excludeDeleted()
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
}
