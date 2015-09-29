<?php
/**
 * An API controller for managing tenants.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Helper\EloquentRecordCounter as RecordCounter;

class TenantAPIController extends ControllerAPI
{
    /**
     * POST - Delete Tenant
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `retailer_id`              (required) - ID of the retailer
     * @param string     `password`                 (required) - The mall master password
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeleteTenant()
    {
        $activity = Activity::portal()
                          ->setActivityType('delete');

        $user = NULL;
        $deletetenant = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.tenant.postdeletetenant.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.tenant.postdeletetenant.after.auth', array($this));

            // Try to check access control list, does this tenant allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.tenant.postdeletetenant.before.authz', array($this, $user));
/*
            if (! ACL::create($user)->isAllowed('delete_tenant')) {
                Event::fire('orbit.tenant.postdeletetenant.authz.notallowed', array($this, $user));
                $deleteTenantLang = Lang::get('validation.orbit.actionlist.delete_tenant');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $deleteTenantLang));
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

            Event::fire('orbit.tenant.postdeletetenant.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $retailer_id = OrbitInput::post('retailer_id');
            /* for next version
            $password = OrbitInput::post('password');
            */
            
            $validator = Validator::make(
                array(
                    'retailer_id' => $retailer_id,
                    /* for next version
                    'password'    => $password,
                    */
                ),
                array(
                    'retailer_id' => 'required|numeric|orbit.empty.tenant',//|orbit.exists.deleted_tenant_is_box_current_retailer',
                    /* for next version
                    'password'    => 'required|orbit.masterpassword.delete',
                    */
                )
                /* for next version
                ,
                array(
                    'required.password'             => 'The master is password is required.',
                    'orbit.masterpassword.delete'   => 'The password is incorrect.'
                )
                */
            );

            Event::fire('orbit.tenant.postdeletetenant.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.tenant.postdeletetenant.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // soft delete tenant.
            $deletetenant = App::make('orbit.empty.tenant');
            $deletetenant->status = 'deleted';
            $deletetenant->modified_by = $this->api->user->user_id;

            Event::fire('orbit.tenant.postdeletetenant.before.save', array($this, $deletetenant));

            foreach ($deletetenant->translations as $translation) {
                $translation->modified_by = $this->api->user->user_id;
                $translation->delete();
            }

            $deletetenant->save();

            Event::fire('orbit.tenant.postdeletetenant.after.save', array($this, $deletetenant));
            $this->response->data = null;
            $this->response->message = Lang::get('statuses.orbit.deleted.tenant');

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activityNotes = sprintf('Tenant Deleted: %s', $deletetenant->name);
            $activity->setUser($user)
                    ->setActivityName('delete_tenant')
                    ->setActivityNameLong('Delete Tenant OK')
                    ->setObject($deletetenant)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.tenant.postdeletetenant.after.commit', array($this, $deletetenant));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.tenant.postdeletetenant.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_tenant')
                    ->setActivityNameLong('Delete Tenant Failed')
                    ->setObject($deletetenant)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.tenant.postdeletetenant.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_tenant')
                    ->setActivityNameLong('Delete Tenant Failed')
                    ->setObject($deletetenant)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.tenant.postdeletetenant.query.error', array($this, $e));

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
                    ->setActivityName('delete_tenant')
                    ->setActivityNameLong('Delete Tenant Failed')
                    ->setObject($deletetenant)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.tenant.postdeletetenant.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_tenant')
                    ->setActivityNameLong('Delete Tenant Failed')
                    ->setObject($deletetenant)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.tenant.postdeletetenant.before.render', array($this, $output));

        // Save the activity
        $activity->save();

        return $output;
    }

     /**
     * POST - Add new tenant
     *
     * @author Tian <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `user_id`                 (optional) - User id for the retailer
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
     * @param string     `floor`                   (optional) - The Floor
     * @param string     `unit`                    (optional) - The unit number
     * @param string     `category_ids`            (optional) - List of category ids
     * @param string     `external_object_id`      (required) - External object ID
     * @param integer    `id_language_default`     (required) - ID language default  
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postNewTenant()
    {
        $activity = Activity::portal()
                            ->setActivityType('create');

        $user = NULL;
        $newtenant = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.tenant.postnewtenant.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.tenant.postnewtenant.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;

            Event::fire('orbit.tenant.postnewtenant.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('create_tenant')) {
                Event::fire('orbit.tenant.postnewtenant.authz.notallowed', array($this, $user));
                $createTenantLang = Lang::get('validation.orbit.actionlist.new_tenant');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $createTenantLang));
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

            Event::fire('orbit.tenant.postnewtenant.after.authz', array($this, $user));

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
            $id_language_default = OrbitInput::post('id_language_default');

            // default value for status is inactive
            $status = OrbitInput::post('status');
            if (trim($status) === '') {
                $status = 'inactive';
            }

            $logo = OrbitInput::post('logo');
            $currency = OrbitInput::post('currency');
            $currency_symbol = OrbitInput::post('currency_symbol');
            $tax_code1 = OrbitInput::post('tax_code1');
            $tax_code2 = OrbitInput::post('tax_code2');
            $tax_code3 = OrbitInput::post('tax_code3');
            $slogan = OrbitInput::post('slogan');

            // default value for vat_included is 'yes'
            $vat_included = OrbitInput::post('vat_included');
            if (trim($vat_included) === '') {
                $vat_included = 'yes';
            }

            $contact_person_firstname = OrbitInput::post('contact_person_firstname');
            $contact_person_lastname = OrbitInput::post('contact_person_lastname');
            $contact_person_position = OrbitInput::post('contact_person_position');
            $contact_person_phone = OrbitInput::post('contact_person_phone');
            $contact_person_phone2 = OrbitInput::post('contact_person_phone2');
            $contact_person_email = OrbitInput::post('contact_person_email');
            $sector_of_activity = OrbitInput::post('sector_of_activity');

            // set user mall id
            $parent_id = OrbitInput::post('parent_id');//Config::get('orbit.shop.id');

            $url = OrbitInput::post('url');
            $masterbox_number = OrbitInput::post('masterbox_number');
            $slavebox_number = OrbitInput::post('slavebox_number');
            $floor = OrbitInput::post('floor');
            $unit = OrbitInput::post('unit');
            $external_object_id = OrbitInput::post('external_object_id');
            $category_ids = OrbitInput::post('category_ids');
            $category_ids = (array) $category_ids;

            // Begin database transaction
            $this->beginTransaction();

            $validator = Validator::make(
                array(
                    'email'                => $email,
                    'name'                 => $name,
                    'external_object_id'   => $external_object_id,
                    'status'               => $status,
                    'parent_id'            => $parent_id,
                    'country'              => $country,
                    'url'                  => $url,
                    'id_language_default' => $id_language_default,
                ),
                array(
                    'email'                => 'required|email|orbit.exists.email',
                    'name'                 => 'required',
                    'external_object_id'   => 'required',
                    'status'               => 'orbit.empty.tenant_status',
                    'parent_id'            => 'numeric|orbit.empty.mall',
                    'country'              => 'numeric',
                    'url'                  => 'orbit.formaterror.url.web',
                    'id_language_default' => 'required|numeric',
                )
            );

            Event::fire('orbit.tenant.postnewtenant.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // validate category_ids
            foreach ($category_ids as $category_id_check) {
                $validator = Validator::make(
                    array(
                        'category_id'   => $category_id_check,
                    ),
                    array(
                        'category_id'   => 'numeric|orbit.empty.category:' . $parent_id,
                    )
                );

                Event::fire('orbit.tenant.postnewtenant.before.categoryvalidation', array($this, $validator));

                // Run the validation
                if ($validator->fails()) {
                    $errorMessage = $validator->messages()->first();
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }

                Event::fire('orbit.tenant.postnewtenant.after.categoryvalidation', array($this, $validator));
            }

            Event::fire('orbit.tenant.postnewtenant.after.validation', array($this, $validator));

            $roleTenant = Role::where('role_name', 'tenant owner')->first();
            if (empty($roleTenant)) {
                OrbitShopAPI::throwInvalidArgument('Could not find role named "Tenant Owner".');
            }

            $newuser = new User();
            $newuser->username = $email;
            $newuser->user_email = $email;
            OrbitInput::post('password', function ($password) use ($newuser) {
                $newuser->user_password = Hash::make($password);
            });
            $newuser->status = $status;
            $newuser->user_role_id = $roleTenant->role_id;
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

            $newtenant = new Tenant();
            $newtenant->user_id = $newuser->user_id;
            $newtenant->omid = '';
            $newtenant->orid = '';
            $newtenant->email = $email;
            $newtenant->name = $name;
            $newtenant->description = $description;
            $newtenant->address_line1 = $address_line1;
            $newtenant->address_line2 = $address_line2;
            $newtenant->address_line3 = $address_line3;
            $newtenant->postal_code = $postal_code;
            $newtenant->city_id = $city_id;
            $newtenant->city = $city;
            $newtenant->country_id = $country;
            $newtenant->country = $countryName;
            $newtenant->phone = $phone;
            $newtenant->fax = $fax;
            $newtenant->start_date_activity = $start_date_activity;
            $newtenant->end_date_activity = $end_date_activity;
            $newtenant->status = $status;
            $newtenant->logo = $logo;
            $newtenant->currency = $currency;
            $newtenant->currency_symbol = $currency_symbol;
            $newtenant->tax_code1 = $tax_code1;
            $newtenant->tax_code2 = $tax_code2;
            $newtenant->tax_code3 = $tax_code3;
            $newtenant->slogan = $slogan;
            $newtenant->vat_included = $vat_included;
            $newtenant->contact_person_firstname = $contact_person_firstname;
            $newtenant->contact_person_lastname = $contact_person_lastname;
            $newtenant->contact_person_position = $contact_person_position;
            $newtenant->contact_person_phone = $contact_person_phone;
            $newtenant->contact_person_phone2 = $contact_person_phone2;
            $newtenant->contact_person_email = $contact_person_email;
            $newtenant->sector_of_activity = $sector_of_activity;
            $newtenant->parent_id = $parent_id;
            $newtenant->url = $url;
            $newtenant->masterbox_number = $masterbox_number;
            $newtenant->slavebox_number = $slavebox_number;
            $newtenant->modified_by = $this->api->user->user_id;
            $newtenant->floor = $floor;
            $newtenant->unit = $unit;
            $newtenant->external_object_id = $external_object_id;

            Event::fire('orbit.tenant.postnewtenant.before.save', array($this, $newtenant));

            $newtenant->save();

            // save merchant categories
            $categoryMerchants = array();
            foreach ($category_ids as $category_id) {
                $categoryMerchant = new CategoryMerchant();
                $categoryMerchant->category_id = $category_id;
                $categoryMerchant->merchant_id = $newtenant->merchant_id;
                $categoryMerchant->save();
                $categoryMerchants[] = $categoryMerchant;
            }
            $newtenant->categories = $categoryMerchants;

            Event::fire('orbit.tenant.postnewtenant.after.save', array($this, $newtenant));

            // @author Irianto Pratama <irianto@dominopos.com>
            $default_translation = [
                $id_language_default => [
                    'description' => $newtenant->description
                ]
            ];
            $this->validateAndSaveTranslations($newtenant, json_encode($default_translation), 'create');

            OrbitInput::post('translations', function($translation_json_string) use ($newtenant) {
                $this->validateAndSaveTranslations($newtenant, $translation_json_string, 'create');
            });

            $this->response->data = $newtenant;

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activityNotes = sprintf('Tenant Created: %s', $newtenant->name);
            $activity->setUser($user)
                    ->setActivityName('create_tenant')
                    ->setActivityNameLong('Create Tenant OK')
                    ->setObject($newtenant)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.tenant.postnewtenant.after.commit', array($this, $newtenant));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.tenant.postnewtenant.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_tenant')
                    ->setActivityNameLong('Create Tenant Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.tenant.postnewtenant.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_tenant')
                    ->setActivityNameLong('Create Tenant Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.tenant.postnewtenant.query.error', array($this, $e));

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
                    ->setActivityName('create_tenant')
                    ->setActivityNameLong('Create Tenant Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.tenant.postnewtenant.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_tenant')
                    ->setActivityNameLong('Create Tenant Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save the activity
        $activity->save();

        return $this->render($httpCode);
    }

    /**
     * POST - Update Tenant
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `merchant_id`              (required) - ID of the retailer
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
     * @param string     `floor`                    (optional) - The Floor
     * @param string     `unit`                     (optional) - The unit number
     * @param string     `external_object_id`       (optional) - External object ID
     * @param string     `no_category`              (optional) - Flag to delete all category links. Valid value: Y.
     * @param array      `category_ids`             (optional) - List of category ids
     * @param integer    `id_language_default`      (required) - ID language default
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postUpdateTenant()
    {
        $activity = Activity::portal()
                           ->setActivityType('update');

        $user = NULL;
        $updatedtenant = NULL;
        try {
            $httpCode=200;

            Event::fire('orbit.tenant.postupdatetenant.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.tenant.postupdatetenant.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.tenant.postupdatetenant.before.authz', array($this, $user));
/*
            if (! ACL::create($user)->isAllowed('update_tenant')) {
                Event::fire('orbit.tenant.postupdatetenant.authz.notallowed', array($this, $user));
                $updateTenantLang = Lang::get('validation.orbit.actionlist.update_tenant');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $updateTenantLang));
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

            Event::fire('orbit.tenant.postupdatetenant.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $retailer_id = OrbitInput::post('retailer_id');
            $user_id = OrbitInput::post('user_id');
            $email = OrbitInput::post('email');
            $status = OrbitInput::post('status');
            $parent_id = OrbitInput::post('parent_id');
            $url = OrbitInput::post('url');
            $id_language_default = OrbitInput::post('id_language_default');

            // Begin database transaction
            $this->beginTransaction();

            $validator = Validator::make(
                array(
                    'retailer_id'           => $retailer_id,
                    'user_id'               => $user_id,
                    'email'                 => $email,
                    'status'                => $status,
                    'parent_id'             => $parent_id,
                    'url'                   => $url,
                    'id_language_default'   => $id_language_default,
                ),
                array(
                    'retailer_id'           => 'required|numeric|orbit.empty.tenant',
                    'user_id'               => 'numeric|orbit.empty.user',
                    'email'                 => 'email|email_exists_but_me',
                    'status'                => 'orbit.empty.tenant_status',//|orbit.exists.inactive_tenant_is_box_current_retailer:'.$retailer_id,
                    'parent_id'             => 'numeric|orbit.empty.mall',
                    'url'                   => 'orbit.formaterror.url.web',
                    'id_language_default'   => 'required|numeric',
                ),
                array(
                   'email_exists_but_me' => Lang::get('validation.orbit.exists.email'),
               )
            );

            Event::fire('orbit.tenant.postupdatetenant.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.tenant.postupdatetenant.after.validation', array($this, $validator));

            $updatedtenant = App::make('orbit.empty.tenant');

            OrbitInput::post('user_id', function($user_id) use ($updatedtenant) {
                $updatedtenant->user_id = $user_id;
            });

            OrbitInput::post('email', function($email) use ($updatedtenant) {
                $updatedtenant->email = $email;
            });

            OrbitInput::post('name', function($name) use ($updatedtenant) {
                $updatedtenant->name = $name;
            });

            OrbitInput::post('description', function($description) use ($updatedtenant) {
                $updatedtenant->description = $description;
            });

            OrbitInput::post('address_line1', function($address_line1) use ($updatedtenant) {
                $updatedtenant->address_line1 = $address_line1;
            });

            OrbitInput::post('address_line2', function($address_line2) use ($updatedtenant) {
                $updatedtenant->address_line2 = $address_line2;
            });

            OrbitInput::post('address_line3', function($address_line3) use ($updatedtenant) {
                $updatedtenant->address_line3 = $address_line3;
            });

            OrbitInput::post('postal_code', function($postal_code) use ($updatedtenant) {
                $updatedtenant->postal_code = $postal_code;
            });

            OrbitInput::post('city_id', function($city_id) use ($updatedtenant) {
                $updatedtenant->city_id = $city_id;
            });

            OrbitInput::post('city', function($city) use ($updatedtenant) {
                $updatedtenant->city = $city;
            });

            OrbitInput::post('country', function($country) use ($updatedtenant) {
                $countryName = '';
                $countryObject = Country::find($country);
                if (is_object($countryObject)) {
                    $countryName = $countryObject->name;
                }

                $updatedtenant->country_id = $country;
                $updatedtenant->country = $countryName;
            });

            OrbitInput::post('phone', function($phone) use ($updatedtenant) {
                $updatedtenant->phone = $phone;
            });

            OrbitInput::post('fax', function($fax) use ($updatedtenant) {
                $updatedtenant->fax = $fax;
            });

            OrbitInput::post('start_date_activity', function($start_date_activity) use ($updatedtenant) {
                $updatedtenant->start_date_activity = $start_date_activity;
            });

            OrbitInput::post('end_date_activity', function($end_date_activity) use ($updatedtenant) {
                $updatedtenant->end_date_activity = $end_date_activity;
            });

            OrbitInput::post('status', function($status) use ($updatedtenant) {
                $updatedtenant->status = $status;
            });

            OrbitInput::post('logo', function($logo) use ($updatedtenant) {
                // do nothing
            });

            OrbitInput::post('currency', function($currency) use ($updatedtenant) {
                $updatedtenant->currency = $currency;
            });

            OrbitInput::post('currency_symbol', function($currency_symbol) use ($updatedtenant) {
                $updatedtenant->currency_symbol = $currency_symbol;
            });

            OrbitInput::post('tax_code1', function($tax_code1) use ($updatedtenant) {
                $updatedtenant->tax_code1 = $tax_code1;
            });

            OrbitInput::post('tax_code2', function($tax_code2) use ($updatedtenant) {
                $updatedtenant->tax_code2 = $tax_code2;
            });

            OrbitInput::post('tax_code3', function($tax_code3) use ($updatedtenant) {
                $updatedtenant->tax_code3 = $tax_code3;
            });

            OrbitInput::post('slogan', function($slogan) use ($updatedtenant) {
                $updatedtenant->slogan = $slogan;
            });

            OrbitInput::post('vat_included', function($vat_included) use ($updatedtenant) {
                $updatedtenant->vat_included = $vat_included;
            });

            OrbitInput::post('contact_person_firstname', function($contact_person_firstname) use ($updatedtenant) {
                $updatedtenant->contact_person_firstname = $contact_person_firstname;
            });

            OrbitInput::post('contact_person_lastname', function($contact_person_lastname) use ($updatedtenant) {
                $updatedtenant->contact_person_lastname = $contact_person_lastname;
            });

            OrbitInput::post('contact_person_position', function($contact_person_position) use ($updatedtenant) {
                $updatedtenant->contact_person_position = $contact_person_position;
            });

            OrbitInput::post('contact_person_phone', function($contact_person_phone) use ($updatedtenant) {
                $updatedtenant->contact_person_phone = $contact_person_phone;
            });

            OrbitInput::post('contact_person_phone2', function($contact_person_phone2) use ($updatedtenant) {
                $updatedtenant->contact_person_phone2 = $contact_person_phone2;
            });

            OrbitInput::post('contact_person_email', function($contact_person_email) use ($updatedtenant) {
                $updatedtenant->contact_person_email = $contact_person_email;
            });

            OrbitInput::post('sector_of_activity', function($sector_of_activity) use ($updatedtenant) {
                $updatedtenant->sector_of_activity = $sector_of_activity;
            });

            OrbitInput::post('parent_id', function($parent_id) use ($updatedtenant) {
                $updatedtenant->parent_id = $parent_id;
            });

            OrbitInput::post('url', function($url) use ($updatedtenant) {
                $updatedtenant->url = $url;
            });

            OrbitInput::post('masterbox_number', function($masterbox_number) use ($updatedtenant) {
                $updatedtenant->masterbox_number = $masterbox_number;
            });

            OrbitInput::post('slavebox_number', function($slavebox_number) use ($updatedtenant) {
                $updatedtenant->slavebox_number = $slavebox_number;
            });

            OrbitInput::post('floor', function($floor) use ($updatedtenant) {
                $updatedtenant->floor = $floor;
            });

            OrbitInput::post('unit', function($unit) use ($updatedtenant) {
                $updatedtenant->unit = $unit;
            });

            OrbitInput::post('external_object_id', function($external_object_id) use ($updatedtenant) {
                $updatedtenant->external_object_id = $external_object_id;
            });

            // @author Irianto Pratama <irianto@dominopos.com>
            $default_translation = [
                $id_language_default => [
                    'description' => $updatedtenant->description
                ]
            ];
            $this->validateAndSaveTranslations($updatedtenant, json_encode($default_translation), 'update');

            OrbitInput::post('translations', function($translation_json_string) use ($updatedtenant) {
                $this->validateAndSaveTranslations($updatedtenant, $translation_json_string, 'update');
            });

            $updatedtenant->modified_by = $this->api->user->user_id;

            Event::fire('orbit.tenant.postupdatetenant.before.save', array($this, $updatedtenant));

            $updatedtenant->save();

            // update user status
            OrbitInput::post('status', function($status) use ($updatedtenant) {
                $updateuser = User::with(array('role'))->excludeDeleted()->find($updatedtenant->user_id);
                if (is_object($updateuser)) {
                    if (! $updateuser->isSuperAdmin()) {
                        $updateuser->status = $status;
                        $updateuser->modified_by = $this->api->user->user_id;

                        $updateuser->save();
                    }
                }
            });

            // save CategoryMerchant
            OrbitInput::post('no_category', function($no_category) use ($updatedtenant) {
                if ($no_category == 'Y') {
                    $deleted_category_ids = CategoryMerchant::where('merchant_id', $updatedtenant->merchant_id)->get(array('category_id'))->toArray();
                    $updatedtenant->categories()->detach($deleted_category_ids);
                    $updatedtenant->load('categories');
                }
            });

            OrbitInput::post('category_ids', function($category_ids) use ($updatedtenant) {
                // validate category_ids
                $category_ids = (array) $category_ids;
                foreach ($category_ids as $category_id_check) {
                    $validator = Validator::make(
                        array(
                            'category_id'   => $category_id_check,
                        ),
                        array(
                            'category_id'   => 'numeric|orbit.empty.category:' . $updatedtenant->parent_id,
                        )
                    );

                    Event::fire('orbit.tenant.postupdatetenant.before.categoryvalidation', array($this, $validator));

                    // Run the validation
                    if ($validator->fails()) {
                        $errorMessage = $validator->messages()->first();
                        OrbitShopAPI::throwInvalidArgument($errorMessage);
                    }

                    Event::fire('orbit.tenant.postupdatetenant.after.categoryvalidation', array($this, $validator));
                }
                // sync new set of category ids
                $updatedtenant->categories()->sync($category_ids);

                // reload categories relation
                $updatedtenant->load('categories');
            });

            Event::fire('orbit.tenant.postupdatetenant.after.save', array($this, $updatedtenant));
            $this->response->data = $updatedtenant;

            // Commit the changes
            $this->commit();

            // Successfull Update
            $activityNotes = sprintf('Tenant updated: %s', $updatedtenant->name);
            $activity->setUser($user)
                    ->setActivityName('update_tenant')
                    ->setActivityNameLong('Update Tenant OK')
                    ->setObject($updatedtenant)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.tenant.postupdatetenant.after.commit', array($this, $updatedtenant));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.tenant.postupdatetenant.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_tenant')
                    ->setActivityNameLong('Update Tenant Failed')
                    ->setObject(NULL)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.tenant.postupdatetenant.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_tenant')
                    ->setActivityNameLong('Update Tenant Failed')
                    ->setObject(NULL)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.tenant.postupdatetenant.query.error', array($this, $e));

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
                    ->setActivityName('update_tenant')
                    ->setActivityNameLong('Update Tenant Failed')
                    ->setObject(NULL)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.tenant.postupdatetenant.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_tenant')
                    ->setActivityNameLong('Update Tenant Failed')
                    ->setObject(NULL)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save activity
        $activity->save();

        return $this->render($httpCode);

    }

    /**
     * GET - Search Tenant
     *
     * @author Rio Astamal <me@rioastamal.net>
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
     * @param string            `floor`                         (optional) - The Floor
     * @param string            `unit`                          (optional) - The unit number
     * @param string            `external_object_id`            (optional) - External object ID
     * @param datetime          `created_at_after`              (optional) -
     * @param datetime          `created_at_before`             (optional) -
     * @param datetime          `updated_at_after`              (optional) -
     * @param datetime          `updated_at_before`             (optional) -
     * @param string|array      `with`                          (optional) - Relation which need to be included
     * @param string|array      `with_count`                    (optional) - Also include the "count" relation or not, should be used in conjunction with `with`
     * @param string            `keyword`                       (optional) - keyword to search tenant name or description or email or category name
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchTenant()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.tenant.getsearchtenant.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.tenant.getsearchtenant.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.tenant.getsearchtenant.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('view_tenant')) {
                Event::fire('orbit.tenant.getsearchtenant.authz.notallowed', array($this, $user));
                $viewTenantLang = Lang::get('validation.orbit.actionlist.view_tenant');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewTenantLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.tenant.getsearchtenant.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sortby');
            $validator = Validator::make(
                array(
                    'sortby' => $sort_by,
                ),
                array(
                    'sortby' => 'in:registered_date,retailer_name,retailer_email,retailer_userid,retailer_description,retailerid,retailer_address1,retailer_address2,retailer_address3,retailer_cityid,retailer_city,retailer_countryid,retailer_country,retailer_phone,retailer_fax,retailer_status,retailer_currency,contact_person_firstname,merchant_name,retailer_floor,retailer_unit,retailer_external_object_id,retailer_created_at,retailer_updated_at',
                ),
                array(
                    'sortby.in' => Lang::get('validation.orbit.empty.retailer_sortby'),
                )
            );

            Event::fire('orbit.tenant.getsearchtenant.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.tenant.getsearchtenant.after.validation', array($this, $validator));

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
            $tenants = Tenant::select('merchants.*', DB::raw('CONCAT(floor, " - ", unit) AS location'))
                                 ->excludeDeleted('merchants');

            // Filter tenant by Ids
            OrbitInput::get('merchant_id', function($merchantIds) use ($tenants)
            {
                $tenants->whereIn('merchants.merchant_id', $merchantIds);
            });

            // Filter tenant by Ids
            OrbitInput::get('user_id', function($userIds) use ($tenants)
            {
                $tenants->whereIn('merchants.user_id', $userIds);
            });

            // Filter tenant by name
            OrbitInput::get('name', function($name) use ($tenants)
            {
                $tenants->whereIn('merchants.name', $name);
            });

            // Filter tenant by matching name pattern
            OrbitInput::get('name_like', function($name) use ($tenants)
            {
                $tenants->where('merchants.name', 'like', "%$name%");
            });

            // Filter tenant by description
            OrbitInput::get('description', function($description) use ($tenants)
            {
                $tenants->whereIn('merchants.description', $description);
            });

            // Filter tenant by description pattern
            OrbitInput::get('description_like', function($description) use ($tenants)
            {
                $tenants->where('merchants.description', 'like', "%$description%");
            });

            // Filter tenant by their email
            OrbitInput::get('email', function($email) use ($tenants)
            {
                $tenants->whereIn('merchants.email', $email);
            });

            // Filter tenant by address1
            OrbitInput::get('address1', function($address1) use ($tenants)
            {
                $tenants->where('merchants.address_line1', "%$address1%");
            });

            // Filter tenant by address1 pattern
            OrbitInput::get('address1', function($address1) use ($tenants)
            {
                $tenants->where('merchants.address_line1', 'like', "%$address1%");
            });

            // Filter tenant by address2
            OrbitInput::get('address2', function($address2) use ($tenants)
            {
                $tenants->where('merchants.address_line2', "%$address2%");
            });

            // Filter tenant by address2 pattern
            OrbitInput::get('address2', function($address2) use ($tenants)
            {
                $tenants->where('merchants.address_line2', 'like', "%$address2%");
            });

             // Filter tenant by address3
            OrbitInput::get('address3', function($address3) use ($tenants)
            {
                $tenants->where('merchants.address_line3', "%$address3%");
            });

             // Filter tenant by address3 pattern
            OrbitInput::get('address3', function($address3) use ($tenants)
            {
                $tenants->where('merchants.address_line3', 'like', "%$address3%");
            });

            // Filter tenant by postal code
            OrbitInput::get('postal_code', function ($postalcode) use ($tenants) {
                $tenants->whereIn('merchants.postal_code', $postalcode);
            });

             // Filter tenant by cityID
            OrbitInput::get('city_id', function($cityIds) use ($tenants)
            {
                $tenants->whereIn('merchants.city_id', $cityIds);
            });

             // Filter tenant by city
            OrbitInput::get('city', function($city) use ($tenants)
            {
                $tenants->whereIn('merchants.city', $city);
            });

             // Filter tenant by city pattern
            OrbitInput::get('city_like', function($city) use ($tenants)
            {
                $tenants->where('merchants.city', 'like', "%$city%");
            });

             // Filter tenant by countryID
            OrbitInput::get('country_id', function($countryId) use ($tenants)
            {
                $tenants->whereIn('merchants.country_id', $countryId);
            });

             // Filter tenant by country
            OrbitInput::get('country', function($country) use ($tenants)
            {
                $tenants->whereIn('merchants.country', $country);
            });

             // Filter tenant by country pattern
            OrbitInput::get('country_like', function($country) use ($tenants)
            {
                $tenants->where('merchants.country', 'like', "%$country%");
            });

             // Filter tenant by phone
            OrbitInput::get('phone', function($phone) use ($tenants)
            {
                $tenants->whereIn('merchants.phone', $phone);
            });

             // Filter tenant by fax
            OrbitInput::get('fax', function($fax) use ($tenants)
            {
                $tenants->whereIn('merchants.fax', $fax);
            });

             // Filter tenant by phone
            OrbitInput::get('phone', function($phone) use ($tenants)
            {
                $tenants->whereIn('merchants.phone', $phone);
            });

             // Filter tenant by status
            OrbitInput::get('status', function($status) use ($tenants)
            {
                $tenants->whereIn('merchants.status', $status);
            });

            // Filter tenant by currency
            OrbitInput::get('currency', function($currency) use ($tenants)
            {
                $tenants->whereIn('merchants.currency', $currency);
            });

            // Filter tenant by contact person firstname
            OrbitInput::get('contact_person_firstname', function ($contact_person_firstname) use ($tenants) {
                $tenants->whereIn('merchants.contact_person_firstname', $contact_person_firstname);
            });

            // Filter tenant by contact person firstname like
            OrbitInput::get('contact_person_firstname_like', function ($contact_person_firstname) use ($tenants) {
                $tenants->where('merchants.contact_person_firstname', 'like', "%$contact_person_firstname%");
            });

            // Filter tenant by contact person lastname
            OrbitInput::get('contact_person_lastname', function ($contact_person_lastname) use ($tenants) {
                $tenants->whereIn('merchants.contact_person_lastname', $contact_person_lastname);
            });

            // Filter tenant by contact person lastname like
            OrbitInput::get('contact_person_lastname_like', function ($contact_person_lastname) use ($tenants) {
                $tenants->where('merchants.contact_person_lastname', 'like', "%$contact_person_lastname%");
            });

            // Filter tenant by contact person position
            OrbitInput::get('contact_person_position', function ($contact_person_position) use ($tenants) {
                $tenants->whereIn('merchants.contact_person_position', $contact_person_position);
            });

            // Filter tenant by contact person position like
            OrbitInput::get('contact_person_position_like', function ($contact_person_position) use ($tenants) {
                $tenants->where('merchants.contact_person_position', 'like', "%$contact_person_position%");
            });

            // Filter tenant by contact person phone
            OrbitInput::get('contact_person_phone', function ($contact_person_phone) use ($tenants) {
                $tenants->whereIn('merchants.contact_person_phone', $contact_person_phone);
            });

            // Filter tenant by contact person phone2
            OrbitInput::get('contact_person_phone2', function ($contact_person_phone2) use ($tenants) {
                $tenants->whereIn('merchants.contact_person_phone2', $contact_person_phone2);
            });

            // Filter tenant by contact person email
            OrbitInput::get('contact_person_email', function ($contact_person_email) use ($tenants) {
                $tenants->whereIn('merchants.contact_person_email', $contact_person_email);
            });

            // Filter tenant by sector of activity
            OrbitInput::get('sector_of_activity', function ($sector_of_activity) use ($tenants) {
                $tenants->whereIn('merchants.sector_of_activity', $sector_of_activity);
            });

            // Filter tenant by url
            OrbitInput::get('url', function ($url) use ($tenants) {
                $tenants->whereIn('merchants.url', $url);
            });

            // Filter tenant by parent_id
            OrbitInput::get('parent_id', function($parentIds) use ($tenants)
            {
                $tenants->whereIn('merchants.parent_id', $parentIds);
            });

            // Filter tenant by floor
            OrbitInput::get('floor', function($floor) use ($tenants)
            {
                $tenants->whereIn('merchants.floor', $floor);
            });

            // Filter tenant by unit
            OrbitInput::get('unit', function($unit) use ($tenants)
            {
                $tenants->whereIn('merchants.unit', $unit);
            });

            // Filter tenant by location (floor - unit)
            OrbitInput::get('location', function($data) use ($tenants)
            {
                $tenants->whereIn(DB::raw('CONCAT(floor, " - ", unit)'), $data);
            });

            // Filter tenant by location_like (floor - unit)
            OrbitInput::get('location_like', function($data) use ($tenants) {
                $tenants->where(DB::raw('CONCAT(floor, " - ", unit)'), 'like', "%$data%");
            });

            // Filter tenant by categories
            OrbitInput::get('categories', function($data) use ($tenants)
            {
                $tenants->whereHas('categories', function($q) use ($data) {
                    $q->whereIn('category_name', $data);
                });
            });

            // Filter tenant by categories_like
            OrbitInput::get('categories_like', function($data) use ($tenants) {
                $tenants->whereHas('categories', function($q) use ($data) {
                    $q->where('category_name', 'like', "%$data%");
                });
            });

            // Filter tenant by external_object_id
            OrbitInput::get('external_object_id', function($external_object_id) use ($tenants)
            {
                $tenants->whereIn('merchants.external_object_id', $external_object_id);
            });

            // Filter by created_at date
            OrbitInput::get('created_at_after', function($start) use ($tenants) {
                $tenants->where('merchants.created_at', '>=', $start);
            });

            // Filter by created_at date
            OrbitInput::get('created_at_before', function($end) use ($tenants) {
                $tenants->where('merchants.created_at', '<=', $end);
            });

            // Filter by updated_at date
            OrbitInput::get('updated_at_after', function($start) use ($tenants) {
                $tenants->where('merchants.updated_at', '>=', $start);
            });

            // Filter by updated_at date
            OrbitInput::get('updated_at_before', function($end) use ($tenants) {
                $tenants->where('merchants.updated_at', '<=', $end);
            });

            $tenants->where(function ($query) use ($tenants) {

                // Filter tenant by keyword pattern
                OrbitInput::get('keyword', function($keyword) use ($tenants, $query)
                {
                    $query->orWhere('merchants.name', 'like', "%$keyword%");
                    $query->orWhere('merchants.description', 'like', "%$keyword%");
                    $query->orWhere('merchants.email', 'like', "%$keyword%");
                    $query->orWhereHas('categories', function($q) use ($keyword) {
                        $q->where('category_name', 'like', "%$keyword%");
                    });
                    $query->orWhere(DB::raw('CONCAT(floor, " - ", unit)'), 'like', "%$keyword%");
                });

            });

            // Add new tenant based on request
            OrbitInput::get('with', function($with) use ($tenants) {
                $with = (array)$with;

                // Make sure the with_count also in array format
                $withCount = array();
                OrbitInput::get('with_count', function($_wcount) use (&$withCount) {
                    $withCount = (array)$_wcount;
                });

                foreach ($with as $relation) {
                    $tenants->with($relation);

                    // Also include number of count if consumer ask it
                    if (in_array($relation, $withCount)) {
                        $countRelation = $relation . 'Number';
                        $tenants->with($countRelation);
                    }
                    // relation with translation
                    if ($relation === 'translations') {
                        $tenants->with('translations');
                    }
                }
            });

            // Add new relation based on request
            OrbitInput::get('with', function ($with) use ($tenants) {
                $with = (array) $with;

                foreach ($with as $relation) {

                }
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_tenants = clone $tenants;

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
            $tenants->take($take);

            $skip = 0;
            OrbitInput::get('skip', function($_skip) use (&$skip, $tenants)
            {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $tenants->skip($skip);

            // Default sort by
            $sortBy = 'merchants.name';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'registered_date' => 'merchants.created_at',
                    'retailer_name' => 'merchants.name',
                    'retailer_email' => 'merchants.email',
                    'retailer_userid' => 'merchants.user_id',
                    'retailerid' => 'merchants.merchant_id',
                    'retailer_cityid' => 'merchants.city_id',
                    'retailer_city' => 'merchants.city',
                    'retailer_countryid' => 'merchants.country_id',
                    'retailer_country' => 'merchants.country',
                    'retailer_phone' => 'merchants.phone',
                    'retailer_fax' => 'merchants.fax',
                    'retailer_status' => 'merchants.status',
                    'retailer_floor' => 'merchants.floor',
                    'retailer_unit' => 'merchants.unit',
                    'retailer_external_object_id' => 'merchants.external_object_id',
                    'retailer_created_at' => 'merchants.created_at',
                    'retailer_updated_at' => 'merchants.updated_at',

                    // Synonyms
                    'tenant_name' => 'merchants.name',
                    'tenant_email' => 'merchants.email',
                    'tenant_userid' => 'merchants.user_id',
                    'tenantid' => 'merchants.merchant_id',
                    'tenant_cityid' => 'merchants.city_id',
                    'tenant_city' => 'merchants.city',
                    'tenant_countryid' => 'merchants.country_id',
                    'tenant_country' => 'merchants.country',
                    'tenant_phone' => 'merchants.phone',
                    'tenant_fax' => 'merchants.fax',
                    'tenant_status' => 'merchants.status',
                    'tenant_floor' => 'merchants.floor',
                    'tenant_unit' => 'merchants.unit',
                    'tenant_external_object_id' => 'merchants.external_object_id',
                    'tenant_created_at' => 'merchants.created_at',
                    'tenant_updated_at' => 'merchants.updated_at',
                );

                if (array_key_exists($_sortBy, $sortByMapping)) {
                    $sortBy = $sortByMapping[$_sortBy];
                }
            });

            if ($sortBy !== 'merchants.status') {
                $tenants->orderBy('merchants.status', 'asc');
            }

            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });
            $tenants->orderBy($sortBy, $sortMode);

            $totalTenants = RecordCounter::create($_tenants)->count();
            $listOfTenants = $tenants->get();

            $data = new stdclass();
            $data->total_records = $totalTenants;
            $data->returned_records = count($listOfTenants);
            $data->records = $listOfTenants;

            if ($totalTenants === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.tenant');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.tenant.getsearchtenant.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.tenant.getsearchtenant.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.tenant.getsearchtenant.query.error', array($this, $e));

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
            Event::fire('orbit.tenant.getsearchtenant.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.tenant.getsearchtenant.before.render', array($this, &$output));

        return $output;
    }


    protected function registerCustomValidation()
    {
        // Check the existance of retailer id
        Validator::extend('orbit.empty.tenant', function ($attribute, $value, $parameters) {
            $tenant = Tenant::excludeDeleted()
                        ->where('merchant_id', $value)
                        ->first();

            if (empty($tenant)) {
                return FALSE;
            }

            App::instance('orbit.empty.tenant', $tenant);

            return TRUE;
        });

        // Check the existance of retailer id
        Validator::extend('orbit.empty.tenant', function ($attribute, $value, $parameters) {
            $tenant = Tenant::where('merchant_id', $value)
                                ->excludeDeleted()
                                ->first();

            if (empty($tenant)) {
                return FALSE;
            }

            App::instance('orbit.empty.tenant', $tenant);

            return TRUE;
        });

        // Check user email address, it should not exists
        Validator::extend('orbit.exists.email', function ($attribute, $value, $parameters) {
            $tenant = Tenant::excludeDeleted()
                        ->where('email', $value)
                        ->first();

            if (! empty($tenant)) {
                return FALSE;
            }

            App::instance('orbit.validation.tenant', $tenant);

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
            $tenant = Tenant::excludeDeleted()
                        ->where('orid', $value)
                        ->first();

            if (! empty($tenant)) {
                return FALSE;
            }

            App::instance('orbit.validation.tenant', $tenant);

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

        // Check user email address, it should not exists
        Validator::extend('email_exists_but_me', function ($attribute, $value, $parameters) {
            $retailer_id = OrbitInput::post('retailer_id');
            $tenant = Tenant::excludeDeleted()
                        ->where('email', $value)
                        ->where('merchant_id', '!=', $retailer_id)
                        ->first();

            if (! empty($tenant)) {
                return FALSE;
            }

            App::instance('orbit.validation.tenant', $tenant);

            return TRUE;
        });

        // Check ORID, it should not exists
        Validator::extend('orid_exists_but_me', function ($attribute, $value, $parameters) {
            $retailer_id = OrbitInput::post('retailer_id');
            $tenant = Tenant::excludeDeleted()
                        ->where('orid', $value)
                        ->where('merchant_id', '!=', $retailer_id)
                        ->first();

            if (! empty($tenant)) {
                return FALSE;
            }

            App::instance('orbit.validation.tenant', $tenant);

            return TRUE;
        });

        // Check the existance of the retailer status
        Validator::extend('orbit.empty.tenant_status', function ($attribute, $value, $parameters) {
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

            App::instance('orbit.validation.tenant', $value);

            return FALSE;
        });

        // Check the existance of merchant id
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

        // Check the existance of category id
        Validator::extend('orbit.empty.category', function ($attribute, $value, $parameters) {
            $mallId = $parameters[0];

            $category = Category::excludeDeleted()
                                ->where('merchant_id', $mallId)
                                ->where('category_id', $value)
                                ->first();

            if (empty($category)) {
                return FALSE;
            }

            App::instance('orbit.empty.category', $category);

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

        // tenant cannot be deleted if is box current tenant.
        Validator::extend('orbit.exists.deleted_tenant_is_box_current_retailer', function ($attribute, $value, $parameters) {
            $retailer_id = $value;
            $box_retailer_id = Setting::where('setting_name', 'current_retailer')->first()->setting_value;

            if ($retailer_id === $box_retailer_id) {
                return FALSE;
            }

            return TRUE;
        });

        // if tenant status is updated to inactive, then reject if is box current tenant.
        Validator::extend('orbit.exists.inactive_tenant_is_box_current_retailer', function ($attribute, $value, $parameters) {
            if ($value === 'inactive') {
                $retailer_id = $parameters[0];
                $box_retailer_id = Setting::where('setting_name', 'current_retailer')->first()->setting_value;

                if ($retailer_id === $box_retailer_id) {
                    return FALSE;
                }
            }

            return TRUE;
        });

        // Tenant deletion master password
        Validator::extend('orbit.masterpassword.delete', function ($attribute, $value, $parameters) {
            // Current Mall location
            $currentMall = Config::get('orbit.shop.id');

            // Get the master password from settings table
            $masterPassword = Setting::getMasterPasswordFor($currentMall);

            if (! is_object($masterPassword)) {
                // @Todo replace with language
                $message = Lang::get('validation.orbit.access.wrongmasterpassword');
                ACL::throwAccessForbidden($message);
            }

            if (! Hash::check($value, $masterPassword->setting_value)) {
                $message = Lang::get('validation.orbit.access.wrongmasterpassword');

                ACL::throwAccessForbidden($message);
            }

            return TRUE;
        });
    }

    /**
     * GET - Tenant City List
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

            Event::fire('orbit.tenant.getcitylist.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.tenant.getcitylist.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.tenant.getcitylist.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('view_tenant')) {
                Event::fire('orbit.tenant.getcitylist.authz.notallowed', array($this, $user));
                $viewTenantLang = Lang::get('validation.orbit.actionlist.view_tenant');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewTenantLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.retailer.getcitylist.after.authz', array($this, $user));

            $tenants = Tenant::excludeDeleted()
                ->select('city')
                ->whereNotNull('city')
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
            Event::fire('orbit.tenant.getcitylist.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.tenant.getcitylist.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.tenant.getcitylist.query.error', array($this, $e));

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
            Event::fire('orbit.tenant.getcitylist.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.tenant.getcitylist.before.render', array($this, &$output));

        return $output;
    }

    /**
     * @param Retailer $tenant
     * @param string $translations_json_string
     * @param string $scenario 'create' / 'update'
     * @throws InvalidArgsException
     */
    private function validateAndSaveTranslations($tenant, $translations_json_string, $scenario = 'create')
    {
        /*
         * JSON structure: object with keys = merchant_language_id and values = ProductTranslation object or null
         *
         * Having a value of null means deleting the translation
         *
         * where MerchantTranslation object is object with keys:
         *   description, ticket_header, ticket_footer.
         *
         * No requirement for including fields. If field not included it means not updated. If field included with
         * value null it means set to null (use main language content instead).
         */

        $valid_fields = ['description'];
        $user = $this->api->user;
        $operations = [];

        $data = @json_decode($translations_json_string);
        if (json_last_error() != JSON_ERROR_NONE) {
            OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.jsonerror.field.format', ['field' => 'translations']));
        }
        foreach ($data as $merchant_language_id => $translations) {
            $language = MerchantLanguage::excludeDeleted()
                ->allowedForUser($user)
                ->where('merchant_language_id', '=', $merchant_language_id)
                ->first();
            if (empty($language)) {
                OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.empty.merchant_language'));
            }
            $existing_translation = MerchantTranslation::excludeDeleted()
                ->where('merchant_id', '=', $tenant->merchant_id)
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
                $new_translation = new MerchantTranslation();
                $new_translation->merchant_id = $tenant->merchant_id;
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
                /** @var MerchantTranslation $existing_translation */
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
                /** @var MerchantTranslation $existing_translation */
                $existing_translation = $operation[1];
                $existing_translation->modified_by = $this->api->user->user_id;
                $existing_translation->delete();
            }
        }
    }

}
