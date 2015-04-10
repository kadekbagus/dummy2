<?php
/**
 * An API controller for managing retailers.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Helper\EloquentRecordCounter as RecordCounter;

class RetailerAPIController extends ControllerAPI
{
    /**
     * POST - Delete Retailer
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `retailer_id`                 (required) - ID of the retailer
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeleteRetailer()
    {
        $activity = Activity::portal()
                          ->setActivityType('delete');

        $user = NULL;
        $deleteretailer = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.retailer.postdeleteretailer.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.retailer.postdeleteretailer.after.auth', array($this));

            // Try to check access control list, does this retailer allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.retailer.postdeleteretailer.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('delete_retailer')) {
                Event::fire('orbit.retailer.postdeleteretailer.authz.notallowed', array($this, $user));
                $deleteRetailerLang = Lang::get('validation.orbit.actionlist.delete_retailer');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $deleteRetailerLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.retailer.postdeleteretailer.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $retailer_id = OrbitInput::post('retailer_id');
            $password = OrbitInput::post('password');

            $validator = Validator::make(
                array(
                    'retailer_id' => $retailer_id,
                    'password'    => $password,
                ),
                array(
                    'retailer_id' => 'required|numeric|orbit.empty.retailer|orbit.exists.deleted_retailer_is_box_current_retailer',
                    'password'    => 'required|orbit.access.wrongpassword',
                )
            );

            Event::fire('orbit.retailer.postdeleteretailer.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.retailer.postdeleteretailer.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // soft delete retailer.
            $deleteretailer = Retailer::excludeDeleted()->allowedForUser($user)->where('merchant_id', $retailer_id)->first();
            $deleteretailer->status = 'deleted';
            $deleteretailer->modified_by = $this->api->user->user_id;

            Event::fire('orbit.retailer.postdeleteretailer.before.save', array($this, $deleteretailer));

            $deleteretailer->save();

            // soft delete user.
            $deleteuser = User::with(array('apikey', 'role'))->excludeDeleted()->find($deleteretailer->user_id);
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
            Event::fire('orbit.retailer.postdeleteretailer.after.save', array($this, $deleteretailer));
            $this->response->data = null;
            $this->response->message = Lang::get('statuses.orbit.deleted.retailer');

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activityNotes = sprintf('Retailer Deleted: %s', $deleteretailer->name);
            $activity->setUser($user)
                    ->setActivityName('delete_retailer')
                    ->setActivityNameLong('Delete Retailer OK')
                    ->setObject($deleteretailer)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.retailer.postdeleteretailer.after.commit', array($this, $deleteretailer));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.retailer.postdeleteretailer.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_retailer')
                    ->setActivityNameLong('Delete Retailer Failed')
                    ->setObject($deleteretailer)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.retailer.postdeleteretailer.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_retailer')
                    ->setActivityNameLong('Delete Retailer Failed')
                    ->setObject($deleteretailer)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.retailer.postdeleteretailer.query.error', array($this, $e));

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
                    ->setActivityName('delete_retailer')
                    ->setActivityNameLong('Delete Retailer Failed')
                    ->setObject($deleteretailer)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.retailer.postdeleteretailer.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_retailer')
                    ->setActivityNameLong('Delete Retailer Failed')
                    ->setObject($deleteretailer)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.retailer.postdeleteretailer.before.render', array($this, $output));

        // Save the activity
        $activity->save();

        return $output;
    }

     /**
     * POST - Add new retailer
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @author Kadek <kadek@dominopos.com>
     * @author Tian <tian@dominopos.com>
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `user_id`                 (required) - User id for the retailer
     * @param string     `orid`                    (required) - ORID of the retailer
     * @param string     `email`                   (required) - Email address of the retailer
     * @param string     `name`                    (required) - Name of the retailer
     * @param string     `description`             (optional) - Merchant description
     * @param string     `address_line1`           (optional) - Address 1
     * @param string     `address_line2`           (optional) - Address 2
     * @param string     `address_line3`           (optional) - Address 3
     * @param integer    `postal_code`             (optional) - Postal code
     * @param integer    `city_id`                 (optional) - City id
     * @param string     `city`                    (optional) - Name of the city
     * @param integer    `country_id`              (optional) - Country id
     * @param string     `country`                 (optional) - Name of the country
     * @param string     `phone`                   (optional) - Phone of the retailer
     * @param string     `fax`                     (optional) - Fax of the retailer
     * @param string     `start_date_activity`     (optional) - Start date activity of the retailer
     * @param string     `end_date_activity`       (optional) - End date activity of the retailer
     * @param string     `status`                  (optional) - Status of the retailer
     * @param string     `logo`                    (optional) - Logo of the retailer
     * @param string     `currency`                (optional) - Currency used by the retailer
     * @param string     `currency_symbol`         (optional) - Currency symbol
     * @param string     `tax_code1`               (optional) - Tax code 1
     * @param string     `tax_code2`               (optional) - Tax code 2
     * @param string     `tax_code3`               (optional) - Tax code 3
     * @param string     `slogan`                  (optional) - Slogan for the retailer
     * @param string     `vat_included`            (optional) - Vat included
     * @param string     `contact_person_firstname`(optional) - Contact person first name
     * @param string     `contact_person_lastname` (optional) - Contact person last name
     * @param string     `contact_person_position` (optional) - Contact person position
     * @param string     `contact_person_phone`    (optional) - Contact person phone
     * @param string     `contact_person_phone2`   (optional) - Contact person second phone
     * @param string     `contact_person_email`    (optional) - Contact person email
     * @param string     `sector_of_activity`      (optional) - Sector of activity
     * @param integer    `parent_id`               (optional) - Merchant id for the retailer
     * @param string     `url`                     (optional) - Url
     * @param string     `masterbox_number`        (optional) - Masterbox number
     * @param string     `slavebox_number`         (optional) - Slavebox number
     * @return Illuminate\Support\Facades\Response
     */
    public function postNewRetailer()
    {
        $activity = Activity::portal()
                            ->setActivityType('create');

        $user = NULL;
        $newretailer = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.retailer.postnewretailer.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.retailer.postnewretailer.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.retailer.postnewretailer.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('create_retailer')) {
                Event::fire('orbit.retailer.postnewretailer.authz.notallowed', array($this, $user));
                $createRetailerLang = Lang::get('validation.orbit.actionlist.new_retailer');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $createRetailerLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.retailer.postnewretailer.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $password = OrbitInput::post('password');
            $user_id = OrbitInput::post('user_id');
            $email = OrbitInput::post('email');
            $name = OrbitInput::post('name');
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

            $validator = Validator::make(
                array(
                    'email'     => $email,
                    'name'      => $name,
                    'status'    => $status,
                    'parent_id' => $parent_id,
                    'country'   => $country,
                    'url'       => $url,
                ),
                array(
                    'email'     => 'required|email|orbit.exists.email',
                    'name'      => 'required',
                    'status'    => 'required|orbit.empty.retailer_status',
                    'parent_id' => 'required|numeric|orbit.empty.merchant',
                    'country'   => 'required|numeric',
                    'url'       => 'orbit.formaterror.url.web'
                )
            );

            Event::fire('orbit.retailer.postnewretailer.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.retailer.postnewretailer.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            $roleRetailer = Role::where('role_name', 'retailer owner')->first();
            if (empty($roleRetailer)) {
                OrbitShopAPI::throwInvalidArgument('Could not find role named "Merchant Owner".');
            }

            $newuser = new User();
            $newuser->username = $email;
            $newuser->user_email = $email;
            $newuser->user_password = Hash::make($password);
            $newuser->status = $status;
            $newuser->user_role_id = $roleRetailer->role_id;
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

            $newretailer = new Retailer();
            $newretailer->user_id = $newuser->user_id;
            $newretailer->omid = '';
            $newretailer->email = $email;
            $newretailer->name = $name;
            $newretailer->description = $description;
            $newretailer->address_line1 = $address_line1;
            $newretailer->address_line2 = $address_line2;
            $newretailer->address_line3 = $address_line3;
            $newretailer->postal_code = $postal_code;
            $newretailer->city_id = $city_id;
            $newretailer->city = $city;
            $newretailer->country_id = $country;
            $newretailer->country = $countryName;
            $newretailer->phone = $phone;
            $newretailer->fax = $fax;
            $newretailer->start_date_activity = $start_date_activity;
            $newretailer->end_date_activity = $end_date_activity;
            $newretailer->status = $status;
            $newretailer->logo = $logo;
            $newretailer->currency = $currency;
            $newretailer->currency_symbol = $currency_symbol;
            $newretailer->tax_code1 = $tax_code1;
            $newretailer->tax_code2 = $tax_code2;
            $newretailer->tax_code3 = $tax_code3;
            $newretailer->slogan = $slogan;
            $newretailer->vat_included = $vat_included;
            $newretailer->contact_person_firstname = $contact_person_firstname;
            $newretailer->contact_person_lastname = $contact_person_lastname;
            $newretailer->contact_person_position = $contact_person_position;
            $newretailer->contact_person_phone = $contact_person_phone;
            $newretailer->contact_person_phone2 = $contact_person_phone2;
            $newretailer->contact_person_email = $contact_person_email;
            $newretailer->sector_of_activity = $sector_of_activity;
            $newretailer->object_type = $object_type;
            $newretailer->parent_id = $parent_id;
            $newretailer->url = $url;
            $newretailer->masterbox_number = $masterbox_number;
            $newretailer->slavebox_number = $slavebox_number;
            $newretailer->modified_by = $this->api->user->user_id;

            Event::fire('orbit.retailer.postnewretailer.before.save', array($this, $newretailer));

            $newretailer->save();

            // add orid to newly created retailer
            $newretailer->orid = Retailer::ORID_INCREMENT + $newretailer->merchant_id;
            $newretailer->save();

            Event::fire('orbit.retailer.postnewretailer.after.save', array($this, $newretailer));
            $this->response->data = $newretailer;

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activityNotes = sprintf('Retailer Created: %s', $newretailer->name);
            $activity->setUser($user)
                    ->setActivityName('create_retailer')
                    ->setActivityNameLong('Create Retailer OK')
                    ->setObject($newretailer)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.retailer.postnewretailer.after.commit', array($this, $newretailer));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.retailer.postnewretailer.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_retailer')
                    ->setActivityNameLong('Create Retailer Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.retailer.postnewretailer.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_retailer')
                    ->setActivityNameLong('Create Retailer Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.retailer.postnewretailer.query.error', array($this, $e));

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
                    ->setActivityName('create_retailer')
                    ->setActivityNameLong('Create Retailer Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.retailer.postnewretailer.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_retailer')
                    ->setActivityNameLong('Create Retailer Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save the activity
        $activity->save();

        return $this->render($httpCode);
    }


    /**
     * POST - Update retailer
     *
     * @author <Kadek> <kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `merchant_id`              (required) - ID of the retailer
     * @param string     `orid`                     (optional) - ORID of the retailer
     * @param integer    `user_id`                  (optional) - User id for the retailer
     * @param string     `email`                    (optional) - Email address of the retailer
     * @param string     `name`                     (optional) - Name of the retailer
     * @param string     `description`              (optional) - Merchant description
     * @param string     `address_line1`            (optional) - Address 1
     * @param string     `address_line2`            (optional) - Address 2
     * @param string     `address_line3`            (optional) - Address 3
     * @param integer    `postal_code`              (optional) - Postal code
     * @param integer    `city_id`                  (optional) - City id
     * @param string     `city`                     (optional) - Name of the city
     * @param integer    `country_id`               (optional) - Country id
     * @param string     `country`                  (optional) - Name of the country
     * @param string     `phone`                    (optional) - Phone of the retailer
     * @param string     `fax`                      (optional) - Fax of the retailer
     * @param string     `start_date_activity`      (optional) - Start date activity of the retailer
     * @param string     `status`                   (optional) - Status of the retailer
     * @param string     `logo`                     (optional) - Logo of the retailer
     * @param string     `currency`                 (optional) - Currency used by the retailer
     * @param string     `currency_symbol`          (optional) - Currency symbol
     * @param string     `tax_code1`                (optional) - Tax code 1
     * @param string     `tax_code2`                (optional) - Tax code 2
     * @param string     `tax_code3`                (optional) - Tax code 3
     * @param string     `slogan`                   (optional) - Slogan for the retailer
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
     * @return Illuminate\Support\Facades\Response
     */
    public function postUpdateRetailer()
    {
        $activity = Activity::portal()
                           ->setActivityType('update');

        $user = NULL;
        $updatedretailer = NULL;
        try {
            $httpCode=200;

            Event::fire('orbit.retailer.postupdateretailer.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.retailer.postupdateretailer.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.retailer.postupdateretailer.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('update_retailer')) {
                Event::fire('orbit.retailer.postupdateretailer.authz.notallowed', array($this, $user));
                $updateRetailerLang = Lang::get('validation.orbit.actionlist.update_retailer');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $updateRetailerLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.retailer.postupdateretailer.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $retailer_id = OrbitInput::post('retailer_id');
            $user_id = OrbitInput::post('user_id');
            $email = OrbitInput::post('email');
            $status = OrbitInput::post('status');
            $orid = OrbitInput::post('orid');
            $parent_id = OrbitInput::post('parent_id');
            $url = OrbitInput::post('url');

            $validator = Validator::make(
                array(
                    'retailer_id'       => $retailer_id,
                    'user_id'           => $user_id,
                    'email'             => $email,
                    'status'            => $status,
                    'orid'              => $orid,
                    'parent_id'         => $parent_id,
                    'url'               => $url,
                ),
                array(
                    'retailer_id'       => 'required|numeric|orbit.empty.retailer',
                    'user_id'           => 'numeric|orbit.empty.user',
                    'email'             => 'email|email_exists_but_me',
                    'status'            => 'orbit.empty.retailer_status|orbit.exists.inactive_retailer_is_box_current_retailer:'.$retailer_id,
                    'orid'              => 'orid_exists_but_me',
                    'parent_id'         => 'numeric|orbit.empty.merchant',
                    'url'               => 'orbit.formaterror.url.web'
                ),
                array(
                   'email_exists_but_me' => Lang::get('validation.orbit.exists.email'),
                   'orid_exists_but_me'  => Lang::get('validation.orbit.exists.orid'),
               )
            );

            Event::fire('orbit.retailer.postupdateretailer.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.retailer.postupdateretailer.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            $updatedretailer = Retailer::excludeDeleted()->allowedForUser($user)->where('merchant_id', $retailer_id)->first();

            OrbitInput::post('orid', function($orid) use ($updatedretailer) {
                $updatedretailer->orid = $orid;
            });

            OrbitInput::post('user_id', function($user_id) use ($updatedretailer) {
                // $updatedretailer->user_id = $user_id;
            });

            OrbitInput::post('email', function($email) use ($updatedretailer) {
                $updatedretailer->email = $email;
            });

            OrbitInput::post('name', function($name) use ($updatedretailer) {
                $updatedretailer->name = $name;
            });

            OrbitInput::post('description', function($description) use ($updatedretailer) {
                $updatedretailer->description = $description;
            });

            OrbitInput::post('address_line1', function($address_line1) use ($updatedretailer) {
                $updatedretailer->address_line1 = $address_line1;
            });

            OrbitInput::post('address_line2', function($address_line2) use ($updatedretailer) {
                $updatedretailer->address_line2 = $address_line2;
            });

            OrbitInput::post('address_line3', function($address_line3) use ($updatedretailer) {
                $updatedretailer->address_line3 = $address_line3;
            });

            OrbitInput::post('postal_code', function($postal_code) use ($updatedretailer) {
                $updatedretailer->postal_code = $postal_code;
            });

            OrbitInput::post('city_id', function($city_id) use ($updatedretailer) {
                $updatedretailer->city_id = $city_id;
            });

            OrbitInput::post('city', function($city) use ($updatedretailer) {
                $updatedretailer->city = $city;
            });

            OrbitInput::post('country', function($country) use ($updatedretailer) {
                $countryName = '';
                $countryObject = Country::find($country);
                if (is_object($countryObject)) {
                    $countryName = $countryObject->name;
                }

                $updatedretailer->country_id = $country;
                $updatedretailer->country = $countryName;
            });

            OrbitInput::post('phone', function($phone) use ($updatedretailer) {
                $updatedretailer->phone = $phone;
            });

            OrbitInput::post('fax', function($fax) use ($updatedretailer) {
                $updatedretailer->fax = $fax;
            });

            OrbitInput::post('start_date_activity', function($start_date_activity) use ($updatedretailer) {
                $updatedretailer->start_date_activity = $start_date_activity;
            });

            OrbitInput::post('end_date_activity', function($end_date_activity) use ($updatedretailer) {
                $updatedretailer->end_date_activity = $end_date_activity;
            });

            OrbitInput::post('status', function($status) use ($updatedretailer) {
                $updatedretailer->status = $status;
            });

            OrbitInput::post('logo', function($logo) use ($updatedretailer) {
                $updatedretailer->logo = $logo;
            });

            OrbitInput::post('currency', function($currency) use ($updatedretailer) {
                $updatedretailer->currency = $currency;
            });

            OrbitInput::post('currency_symbol', function($currency_symbol) use ($updatedretailer) {
                $updatedretailer->currency_symbol = $currency_symbol;
            });

            OrbitInput::post('tax_code1', function($tax_code1) use ($updatedretailer) {
                $updatedretailer->tax_code1 = $tax_code1;
            });

            OrbitInput::post('tax_code2', function($tax_code2) use ($updatedretailer) {
                $updatedretailer->tax_code2 = $tax_code2;
            });

            OrbitInput::post('tax_code3', function($tax_code3) use ($updatedretailer) {
                $updatedretailer->tax_code3 = $tax_code3;
            });

            OrbitInput::post('slogan', function($slogan) use ($updatedretailer) {
                $updatedretailer->slogan = $slogan;
            });

            OrbitInput::post('vat_included', function($vat_included) use ($updatedretailer) {
                $updatedretailer->vat_included = $vat_included;
            });

            OrbitInput::post('contact_person_firstname', function($contact_person_firstname) use ($updatedretailer) {
                $updatedretailer->contact_person_firstname = $contact_person_firstname;
            });

            OrbitInput::post('contact_person_lastname', function($contact_person_lastname) use ($updatedretailer) {
                $updatedretailer->contact_person_lastname = $contact_person_lastname;
            });

            OrbitInput::post('contact_person_position', function($contact_person_position) use ($updatedretailer) {
                $updatedretailer->contact_person_position = $contact_person_position;
            });

            OrbitInput::post('contact_person_phone', function($contact_person_phone) use ($updatedretailer) {
                $updatedretailer->contact_person_phone = $contact_person_phone;
            });

            OrbitInput::post('contact_person_phone2', function($contact_person_phone2) use ($updatedretailer) {
                $updatedretailer->contact_person_phone2 = $contact_person_phone2;
            });

            OrbitInput::post('contact_person_email', function($contact_person_email) use ($updatedretailer) {
                $updatedretailer->contact_person_email = $contact_person_email;
            });

            OrbitInput::post('sector_of_activity', function($sector_of_activity) use ($updatedretailer) {
                $updatedretailer->sector_of_activity = $sector_of_activity;
            });

            OrbitInput::post('parent_id', function($parent_id) use ($updatedretailer) {
                $updatedretailer->parent_id = $parent_id;
            });

            OrbitInput::post('url', function($url) use ($updatedretailer) {
                $updatedretailer->url = $url;
            });

            OrbitInput::post('masterbox_number', function($masterbox_number) use ($updatedretailer) {
                $updatedretailer->masterbox_number = $masterbox_number;
            });

            OrbitInput::post('slavebox_number', function($slavebox_number) use ($updatedretailer) {
                $updatedretailer->slavebox_number = $slavebox_number;
            });

            $updatedretailer->modified_by = $this->api->user->user_id;

            Event::fire('orbit.retailer.postupdateretailer.before.save', array($this, $updatedretailer));

            $updatedretailer->save();

            // update user status
            OrbitInput::post('status', function($status) use ($updatedretailer) {
                $updateuser = User::with(array('role'))->excludeDeleted()->find($updatedretailer->user_id);
                if (! $updateuser->isSuperAdmin()) {
                    $updateuser->status = $status;
                    $updateuser->modified_by = $this->api->user->user_id;

                    $updateuser->save();
                }
            });

            Event::fire('orbit.retailer.postupdateretailer.after.save', array($this, $updatedretailer));
            $this->response->data = $updatedretailer;

            // Commit the changes
            $this->commit();

            // Successfull Update
            $activityNotes = sprintf('Retailer updated: %s', $updatedretailer->name);
            $activity->setUser($user)
                    ->setActivityName('update_retailer')
                    ->setActivityNameLong('Update Retailer OK')
                    ->setObject($updatedretailer)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.retailer.postupdateretailer.after.commit', array($this, $updatedretailer));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.retailer.postupdateretailer.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_retailer')
                    ->setActivityNameLong('Update Retailer Failed')
                    ->setObject($updatedretailer)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.retailer.postupdateretailer.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_retailer')
                    ->setActivityNameLong('Update Retailer Failed')
                    ->setObject($updatedretailer)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.retailer.postupdateretailer.query.error', array($this, $e));

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
                    ->setActivityName('update_retailer')
                    ->setActivityNameLong('Update Retailer Failed')
                    ->setObject($updatedretailer)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.retailer.postupdateretailer.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_retailer')
                    ->setActivityNameLong('Update Retailer Failed')
                    ->setObject($updatedretailer)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save activity
        $activity->save();

        return $this->render($httpCode);

    }

    /**
     * GET - Search Retailer
     *
     * @author Kadek Bagus <kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string            `sort_by`                       (optional) - column order by
     * @param string            `sort_mode`                     (optional) - asc or desc
     * @param integer           `take`                          (optional) - limit
     * @param integer           `skip`                          (optional) - limit offset
     * @param integer           `merchant_id`                   (optional)
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
     * @param integer           `parent_id`                     (optional) - Merchant id for the retailer
     * @param string|array      `with`                          (optional) - Relation which need to be included
     * @param string|array      `with_count`                    (optional) - Also include the "count" relation or not, should be used in conjunction with `with`
     * @return Illuminate\Support\Facades\Response
     */

    public function getSearchRetailer()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.retailer.getsearchretailer.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.retailer.getsearchretailer.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.retailer.getsearchretailer.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('view_retailer')) {
                Event::fire('orbit.retailer.getsearchretailer.authz.notallowed', array($this, $user));
                $viewRetailerLang = Lang::get('validation.orbit.actionlist.view_retailer');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewRetailerLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.retailer.getsearchretailer.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sortby');
            $validator = Validator::make(
                array(
                    'sortby' => $sort_by,
                ),
                array(
                    'sortby' => 'in:orid,registered_date,retailer_name,retailer_email,retailer_userid,retailer_description,retailerid,retailer_address1,retailer_address2,retailer_address3,retailer_cityid,retailer_city,retailer_countryid,retailer_country,retailer_phone,retailer_fax,retailer_status,retailer_currency,contact_person_firstname,merchant_name',
                ),
                array(
                    'sortby.in' => Lang::get('validation.orbit.empty.retailer_sortby'),
                )
            );

            Event::fire('orbit.retailer.getsearchretailer.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.retailer.getsearchretailer.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.retailer.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.retailer.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            // Builder object
            $retailers = Retailer::excludeDeleted('merchants')
                                ->allowedForUser($user)
                                ->select('merchants.*', DB::raw('m.name as merchant_name'))
                                ->join('merchants AS m', DB::raw('m.merchant_id'), '=', 'merchants.parent_id');

            // Filter retailer by Ids
            OrbitInput::get('merchant_id', function($merchantIds) use ($retailers)
            {
                $retailers->whereIn('merchants.merchant_id', $merchantIds);
            });

            // Filter retailer by Ids
            OrbitInput::get('user_id', function($userIds) use ($retailers)
            {
                $retailers->whereIn('merchants.user_id', $userIds);
            });

            // Filter retailer by name
            OrbitInput::get('name', function($name) use ($retailers)
            {
                $retailers->whereIn('merchants.name', $name);
            });

            // Filter retailer by matching name pattern
            OrbitInput::get('name_like', function($name) use ($retailers)
            {
                $retailers->where('merchants.name', 'like', "%$name%");
            });

            // Filter retailer by description
            OrbitInput::get('description', function($description) use ($retailers)
            {
                $retailers->whereIn('merchants.description', $description);
            });

            // Filter retailer by description pattern
            OrbitInput::get('description_like', function($description) use ($retailers)
            {
                $description->where('merchants.description', 'like', "%$description%");
            });

            // Filter retailer by their email
            OrbitInput::get('email', function($email) use ($retailers)
            {
                $retailers->whereIn('merchants.email', $email);
            });

            // Filter retailer by address1
            OrbitInput::get('address1', function($address1) use ($retailers)
            {
                $retailers->where('merchants.address_line1', "%$address1%");
            });

            // Filter retailer by address1 pattern
            OrbitInput::get('address1', function($address1) use ($retailers)
            {
                $retailers->where('merchants.address_line1', 'like', "%$address1%");
            });

            // Filter retailer by address2
            OrbitInput::get('address2', function($address2) use ($retailers)
            {
                $retailers->where('merchants.address_line2', "%$address2%");
            });

            // Filter retailer by address2 pattern
            OrbitInput::get('address2', function($address2) use ($retailers)
            {
                $retailers->where('merchants.address_line2', 'like', "%$address2%");
            });

             // Filter retailer by address3
            OrbitInput::get('address3', function($address3) use ($retailers)
            {
                $retailers->where('merchants.address_line3', "%$address3%");
            });

             // Filter retailer by address3 pattern
            OrbitInput::get('address3', function($address3) use ($retailers)
            {
                $retailers->where('merchants.address_line3', 'like', "%$address3%");
            });

            // Filter retailer by postal code
            OrbitInput::get('postal_code', function ($postalcode) use ($retailers) {
                $retailers->whereIn('merchants.postal_code', $postalcode);
            });

             // Filter retailer by cityID
            OrbitInput::get('city_id', function($cityIds) use ($retailers)
            {
                $retailers->whereIn('merchants.city_id', $cityIds);
            });

             // Filter retailer by city
            OrbitInput::get('city', function($city) use ($retailers)
            {
                $retailers->whereIn('merchants.city', $city);
            });

             // Filter retailer by city pattern
            OrbitInput::get('city_like', function($city) use ($retailers)
            {
                $retailers->where('merchants.city', 'like', "%$city%");
            });

             // Filter retailer by countryID
            OrbitInput::get('country_id', function($countryId) use ($retailers)
            {
                $retailers->whereIn('merchants.country_id', $countryId);
            });

             // Filter retailer by country
            OrbitInput::get('country', function($country) use ($retailers)
            {
                $retailers->whereIn('merchants.country', $country);
            });

             // Filter retailer by country pattern
            OrbitInput::get('country_like', function($country) use ($retailers)
            {
                $retailers->where('merchants.country', 'like', "%$country%");
            });

             // Filter retailer by phone
            OrbitInput::get('phone', function($phone) use ($retailers)
            {
                $retailers->whereIn('merchants.phone', $phone);
            });

             // Filter retailer by fax
            OrbitInput::get('fax', function($fax) use ($retailers)
            {
                $retailers->whereIn('merchants.fax', $fax);
            });

             // Filter retailer by phone
            OrbitInput::get('phone', function($phone) use ($retailers)
            {
                $retailers->whereIn('merchants.phone', $phone);
            });

             // Filter retailer by status
            OrbitInput::get('status', function($status) use ($retailers)
            {
                $retailers->whereIn('merchants.status', $status);
            });

            // Filter retailer by currency
            OrbitInput::get('currency', function($currency) use ($retailers)
            {
                $retailers->whereIn('merchants.currency', $currency);
            });

            // Filter retailer by contact person firstname
            OrbitInput::get('contact_person_firstname', function ($contact_person_firstname) use ($retailers) {
                $retailers->whereIn('merchants.contact_person_firstname', $contact_person_firstname);
            });

            // Filter retailer by contact person firstname like
            OrbitInput::get('contact_person_firstname_like', function ($contact_person_firstname) use ($retailers) {
                $retailers->where('merchants.contact_person_firstname', 'like', "%$contact_person_firstname%");
            });

            // Filter retailer by contact person lastname
            OrbitInput::get('contact_person_lastname', function ($contact_person_lastname) use ($retailers) {
                $retailers->whereIn('merchants.contact_person_lastname', $contact_person_lastname);
            });

            // Filter retailer by contact person lastname like
            OrbitInput::get('contact_person_lastname_like', function ($contact_person_lastname) use ($retailers) {
                $retailers->where('merchants.contact_person_lastname', 'like', "%$contact_person_lastname%");
            });

            // Filter retailer by contact person position
            OrbitInput::get('contact_person_position', function ($contact_person_position) use ($retailers) {
                $retailers->whereIn('merchants.contact_person_position', $contact_person_position);
            });

            // Filter retailer by contact person position like
            OrbitInput::get('contact_person_position_like', function ($contact_person_position) use ($retailers) {
                $retailers->where('merchants.contact_person_position', 'like', "%$contact_person_position%");
            });

            // Filter retailer by contact person phone
            OrbitInput::get('contact_person_phone', function ($contact_person_phone) use ($retailers) {
                $retailers->whereIn('merchants.contact_person_phone', $contact_person_phone);
            });

            // Filter retailer by contact person phone2
            OrbitInput::get('contact_person_phone2', function ($contact_person_phone2) use ($retailers) {
                $retailers->whereIn('merchants.contact_person_phone2', $contact_person_phone2);
            });

            // Filter retailer by contact person email
            OrbitInput::get('contact_person_email', function ($contact_person_email) use ($retailers) {
                $retailers->whereIn('merchants.contact_person_email', $contact_person_email);
            });

            // Filter retailer by sector of activity
            OrbitInput::get('sector_of_activity', function ($sector_of_activity) use ($retailers) {
                $retailers->whereIn('merchants.sector_of_activity', $sector_of_activity);
            });

            // Filter retailer by url
            OrbitInput::get('url', function ($url) use ($retailers) {
                $retailers->whereIn('merchants.url', $url);
            });

            // Filter retailer by masterbox_number
            OrbitInput::get('masterbox_number', function ($masterbox_number) use ($retailers) {
                $retailers->whereIn('merchants.masterbox_number', $masterbox_number);
            });

            // Filter retailer by slavebox_number
            OrbitInput::get('slavebox_number', function ($slavebox_number) use ($retailers) {
                $retailers->whereIn('merchants.slavebox_number', $slavebox_number);
            });

            // Filter retailer by parent_id
            OrbitInput::get('parent_id', function($parentIds) use ($retailers)
            {
                $retailers->whereIn('merchants.parent_id', $parentIds);
            });

            // Add new relation based on request
            OrbitInput::get('with', function($with) use ($retailers) {
                $with = (array)$with;

                // Make sure the with_count also in array format
                $withCount = array();
                OrbitInput::get('with_count', function($_wcount) use (&$withCount) {
                    $withCount = (array)$_wcount;
                });

                foreach ($with as $relation) {
                    $retailers->with($relation);

                    // Also include number of count if consumer ask it
                    if (in_array($relation, $withCount)) {
                        $countRelation = $relation . 'Number';
                        $retailers->with($countRelation);
                    }
                }
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_retailers = clone $retailers;

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
            $retailers->take($take);

            $skip = 0;
            OrbitInput::get('skip', function($_skip) use (&$skip, $retailers)
            {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $retailers->skip($skip);

            // Default sort by
            $sortBy = 'merchants.name';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                   // Map the sortby request to the real column name
                  $sortByMapping = array(
                  'orid' => 'merchants.orid',
                  'registered_date' => 'merchants.created_at',
                  'retailer_name' => 'merchants.name',
                  'retailer_email' => 'merchants.email',
                  'retailer_userid' => 'merchants.user_id',
                  'retailer_description' => 'merchants.description',
                  'retailerid' => 'merchants.merchant_id',
                  'retailer_address1' => 'merchants.address_line1',
                  'retailer_address2' => 'merchants.address_line2',
                  'retailer_address3' => 'merchants.address_line3',
                  'retailer_cityid' => 'merchants.city_id',
                  'retailer_city' => 'merchants.city',
                  'retailer_countryid' => 'merchants.country_id',
                  'retailer_country' => 'merchants.country',
                  'retailer_phone' => 'merchants.phone',
                  'retailer_fax' => 'merchants.fax',
                  'retailer_status' => 'merchants.status',
                  'retailer_currency' => 'merchants.currency',
                  'contact_person_firstname' => 'merchants.contact_person_firstname',
                  'merchant_name' => 'merchant_name',
                  );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });
            $retailers->orderBy($sortBy, $sortMode);

            $totalRetailers = RecordCounter::create($_retailers)->count();
            $listOfRetailers = $retailers->get();

            $data = new stdclass();
            $data->total_records = $totalRetailers;
            $data->returned_records = count($listOfRetailers);
            $data->records = $listOfRetailers;

            if ($totalRetailers === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.retailer');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.retailer.getsearchretailer.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.retailer.getsearchretailer.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.retailer.getsearchretailer.query.error', array($this, $e));

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
            Event::fire('orbit.retailer.getsearchretailer.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.retailer.getsearchretailer.before.render', array($this, &$output));

        return $output;
    }


    protected function registerCustomValidation()
    {
        // Check the existance of retailer id
        Validator::extend('orbit.empty.retailer', function ($attribute, $value, $parameters) {
            $retailer = Retailer::excludeDeleted()
                        ->where('merchant_id', $value)
                        ->first();

            if (empty($retailer)) {
                return FALSE;
            }

            App::instance('orbit.empty.retailer', $retailer);

            return TRUE;
        });

        // Check user email address, it should not exists
        Validator::extend('orbit.exists.email', function ($attribute, $value, $parameters) {
            $retailer = Retailer::excludeDeleted()
                        ->where('email', $value)
                        ->first();

            if (! empty($retailer)) {
                return FALSE;
            }

            App::instance('orbit.validation.retailer', $retailer);

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

        // Check orid, it should not exists
        Validator::extend('orbit.exists.orid', function ($attribute, $value, $parameters) {
            $retailer = Retailer::excludeDeleted()
                        ->where('orid', $value)
                        ->first();

            if (! empty($retailer)) {
                return FALSE;
            }

            App::instance('orbit.validation.retailer', $retailer);

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

        // Check user email address, it should not exists
        Validator::extend('email_exists_but_me', function ($attribute, $value, $parameters) {
            $retailer_id = OrbitInput::post('retailer_id');
            $retailer = Retailer::excludeDeleted()
                        ->where('email', $value)
                        ->where('merchant_id', '!=', $retailer_id)
                        ->first();

            if (! empty($retailer)) {
                return FALSE;
            }

            App::instance('orbit.validation.retailer', $retailer);

            return TRUE;
        });

        // Check ORID, it should not exists
        Validator::extend('orid_exists_but_me', function ($attribute, $value, $parameters) {
            $retailer_id = OrbitInput::post('retailer_id');
            $retailer = Retailer::excludeDeleted()
                        ->where('orid', $value)
                        ->where('merchant_id', '!=', $retailer_id)
                        ->first();

            if (! empty($retailer)) {
                return FALSE;
            }

            App::instance('orbit.validation.retailer', $retailer);

            return TRUE;
        });

        // Check the existance of the retailer status
        Validator::extend('orbit.empty.retailer_status', function ($attribute, $value, $parameters) {
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

            App::instance('orbit.validation.retailer', $value);

            return FALSE;
        });

        // Check the existance of merchant id
        Validator::extend('orbit.empty.merchant', function ($attribute, $value, $parameters) {
            $merchant = Merchant::excludeDeleted()
                        ->where('merchant_id', $value)
                        ->first();

            if (empty($merchant)) {
                return FALSE;
            }

            App::instance('orbit.empty.merchant', $merchant);

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

        // Retailer cannot be deleted if is box current retailer.
        Validator::extend('orbit.exists.deleted_retailer_is_box_current_retailer', function ($attribute, $value, $parameters) {
            $retailer_id = $value;
            $box_retailer_id = Setting::where('setting_name', 'current_retailer')->first()->setting_value;

            if ($retailer_id === $box_retailer_id) {
                return FALSE;
            }

            return TRUE;
        });

        // if retailer status is updated to inactive, then reject if is box current retailer.
        Validator::extend('orbit.exists.inactive_retailer_is_box_current_retailer', function ($attribute, $value, $parameters) {
            if ($value === 'inactive') {
                $retailer_id = $parameters[0];
                $box_retailer_id = Setting::where('setting_name', 'current_retailer')->first()->setting_value;

                if ($retailer_id === $box_retailer_id) {
                    return FALSE;
                }
            }

            return TRUE;
        });
    }

    /**
     * GET - Retailer City List
     *
     * @author Tian <tian@dominopos.com>
     *
     * @return Illuminate\Support\Facades\Response
     *
     */
    public function getCityList()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.retailer.getcitylist.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.retailer.getcitylist.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.retailer.getcitylist.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('view_retailer')) {
                Event::fire('orbit.retailer.getcitylist.authz.notallowed', array($this, $user));
                $viewRetailerLang = Lang::get('validation.orbit.actionlist.view_retailer');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewRetailerLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.retailer.getcitylist.after.authz', array($this, $user));

            $retailers = Retailer::excludeDeleted()
                ->select('city')
                ->orderBy('city', 'asc')
                ->groupBy('city')
                ->get();

            $data = new stdclass();
            $data->records = $retailers;

            if ($retailers->count() === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.city');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.retailer.getcitylist.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.retailer.getcitylist.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.retailer.getcitylist.query.error', array($this, $e));

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
            Event::fire('orbit.retailer.getcitylist.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.retailer.getcitylist.before.render', array($this, &$output));

        return $output;
    }
}
