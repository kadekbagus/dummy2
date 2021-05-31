<?php
/**
 * An API controller for managing tenants.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Helper\EloquentRecordCounter as RecordCounter;
use Orbit\Helper\Util\PaginationNumber;
use Orbit\Helper\Database\Cache as OrbitDBCache;
use Carbon\Carbon as Carbon;

class TenantAPIController extends ControllerAPI
{

    protected $tenantViewRoles = ['super admin', 'mall admin', 'mall owner', 'campaign admin','campaign owner', 'campaign employee', 'mall customer service'];
    protected $tenantModifiyRoles = ['super admin', 'mall admin', 'mall owner', 'campaign owner', 'campaign employee'];
    protected $campaignRole = ['campaign owner', 'campaign employee'];
    protected $valid_floor = Null;
    protected $valid_account_type = Null;

    /**
     * Flag to return the query builder.
     *
     * @var Builder
     */
    protected $returnBuilder = FALSE;

    /**
     * Default language name used if none are sent
     */
    const DEFAULT_LANG = 'en';

    /**
     * saveSocmedUri()
     *
     * @author Qosdil A. <qosdil@dominopos.com>
     * @param string $socmedCode
     * @param string $merchantId
     * @param string $uri
     */
    private function saveSocmedUri($socmedCode, $merchantId, $uri)
    {
        $socmedId = SocialMedia::whereSocialMediaCode($socmedCode)->first()->social_media_id;

        $merchantSocmed = MerchantSocialMedia::whereMerchantId($merchantId)->whereSocialMediaId($socmedId)->first();

        if (!$merchantSocmed) {
            $merchantSocmed = new MerchantSocialMedia;
            $merchantSocmed->social_media_id = $socmedId;
            $merchantSocmed->merchant_id = $merchantId;
        }

        $merchantSocmed->social_media_uri = $uri;
        $merchantSocmed->save();
    }

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

            // get user mall id
            $mall_id = OrbitInput::post('current_mall');
            $listOfMallIds = $user->getUserMallIds($mall_id);
            if (empty($listOfMallIds)) { // invalid mall id
                $errorMessage = 'Invalid mall id.';
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            } else {
                $mall_id = $listOfMallIds[0];
            }

            /* for next version
            $password = OrbitInput::post('password');
            */

            $validator = Validator::make(
                array(
                    'merchant_id' => $mall_id,
                    'retailer_id' => $retailer_id,
                    /* for next version
                    'password'    => $password,
                    */
                ),
                array(
                    'merchant_id' => 'required|orbit.empty.mall',
                    'retailer_id' => 'required|orbit.empty.tenant',//|orbit.exists.deleted_tenant_is_box_current_retailer',
                    /* for next version
                    'password'    => [
                        'required',
                        ['orbit.masterpassword.delete', $mall_id]
                    ],
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

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.tenant.postdeletetenant.after.validation', array($this, $validator));

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
     * @author Irianto Pratama <irianto@dominopos.com>
     * @author kadek <kadek@dominopos.com>
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
     * @param string     `object_type`             (optional) - object_type of tenant : tenant or service
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

            // tenants do not have emails, but email is required in merchants table so cannot simply be null
            $email = '';

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
            $translations = OrbitInput::post('translations');
            $start_date_activity = OrbitInput::post('start_date_activity');
            $end_date_activity = OrbitInput::post('end_date_activity');
            $object_type = OrbitInput::post('object_type');

            // set user mall id
            $parent_id = OrbitInput::post('parent_id', OrbitInput::post('merchant_id'));

            // get user mall_ids
            $listOfMallIds = $user->getUserMallIds($parent_id);
            if (empty($listOfMallIds)) { // invalid mall id
                $errorMessage = 'Invalid mall id.';
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            } else {
                $parent_id = $listOfMallIds[0];
            }

            $default_merchant_language_id = MerchantLanguage::getLanguageIdByMerchant($parent_id, static::DEFAULT_LANG);
            $id_language_default = OrbitInput::post('id_language_default', $default_merchant_language_id);

            $box_url = OrbitInput::post('box_url');
            $keywords = OrbitInput::post('keywords');
            $keywords = (array) $keywords;

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

            $url = OrbitInput::post('url');
            $box_url = OrbitInput::post('box_url');
            $masterbox_number = OrbitInput::post('masterbox_number');
            $slavebox_number = OrbitInput::post('slavebox_number');
            $floor_id = OrbitInput::post('floor_id');
            $unit = OrbitInput::post('unit');
            $external_object_id = OrbitInput::post('external_object_id');
            $category_ids = OrbitInput::post('category_ids');
            $category_ids = (array) $category_ids;
            // Begin database transaction
            $this->beginTransaction();

            $validator = Validator::make(
                array(
                    'name'                => $name,
                    'box_url'             => $box_url,
                    'external_object_id'  => $external_object_id,
                    'status'              => $status,
                    'parent_id'           => $parent_id,
                    'object_type'         => $object_type,
                    /* 'country'          => $country, */
                    'id_language_default' => $id_language_default,
                    'url'                 => $url,
                    'phone'               => $phone,
                    'masterbox_number'    => $masterbox_number,
                    'floor_id'            => $floor_id,
                ),
                array(
                    'name'                => 'required',
                    'box_url'             => 'orbit.formaterror.url.web',
                    'external_object_id'  => 'required',
                    'status'              => 'orbit.empty.tenant_status',
                    'parent_id'           => 'orbit.empty.mall',
                    'object_type'         => 'required|orbit.empty.tenant_type',
                    /* 'country'          => 'numeric', */
                    'id_language_default' => 'required|orbit.empty.language_default',
                    'url'                 => 'orbit.empty.for_tenant_only:' . $object_type . '|orbit.formaterror.url.web',
                    'phone'               => array('orbit.empty.for_tenant_only:' . $object_type,'regex:/^\+?\d+$/'),
                    'masterbox_number'    => 'orbit.empty.for_tenant_only:' . $object_type . '|alpha_num|orbit_unique_verification_number:' . $parent_id . ',' . '',
                    'floor_id'           => 'orbit.empty.floor:' . $parent_id,
                ),
                array(
                    //ACL::throwAccessForbidden($message);
                    'orbit_unique_verification_number' => 'The verification number already used by other',
                    'orbit.empty.tenant_floor' => Lang::get('validation.orbit.empty.tenant_floor'),
                    'orbit.empty.tenant_unit' => Lang::get('validation.orbit.empty.tenant_unit'),
                    'alpha_num' => 'The verification number must letter and number.',
                    'regex' => 'Wrong phone number format',
                    'orbit.formaterror.url.web' => 'Tenant URL is not valid',
               )
            );

            Event::fire('orbit.tenant.postnewtenant.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // validate category_ids
            if (isset($category_ids) && count($category_ids) > 0) {
                foreach ($category_ids as $category_id_check) {
                    $validator = Validator::make(
                        array(
                            'category_id'   => $category_id_check,
                        ),
                        array(
                            'category_id'   => 'orbit.empty.category',
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
            }

            Event::fire('orbit.tenant.postnewtenant.after.validation', array($this, $validator));

            $countryName = '';
            $countryObject = Country::find($country);
            if (is_object($countryObject)) {
                $countryName = $countryObject->name;
            }

            // Get english language_id
            $idLanguageEnglish = Language::select('language_id')
                                ->where('name', '=', 'en')
                                ->first();

            $floor_db = $this->valid_floor;

            $newtenant = new TenantStoreAndService();
            $newtenant->omid = '';
            $newtenant->orid = '';
            $newtenant->email = $email;
            $newtenant->name = $name;
            $newtenant->address_line1 = $address_line1;
            $newtenant->address_line2 = $address_line2;
            $newtenant->address_line3 = $address_line3;
            $newtenant->postal_code = $postal_code;
            $newtenant->city_id = $city_id;
            $newtenant->city = $city;
            $newtenant->country_id = $country;
            $newtenant->country = $countryName;
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
            $newtenant->phone = $phone;
            $newtenant->url = $url;
            $newtenant->masterbox_number = $masterbox_number;
            $newtenant->slavebox_number = $slavebox_number;
            $newtenant->modified_by = $this->api->user->user_id;
            if (count($floor_db) > 0) {
                $newtenant->floor = $floor_db->object_name;
                $newtenant->floor_id = $floor_db->object_id;
            }
            $newtenant->unit = $unit;
            $newtenant->external_object_id = $external_object_id;
            $newtenant->box_url = $box_url;
            $newtenant->object_type = $object_type;

            // Check for english content
            $dataTranslations = @json_decode($translations);
            if (json_last_error() != JSON_ERROR_NONE) {
                OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.jsonerror.field.format', ['field' => 'translations']));
            }

            if (! is_null($dataTranslations)) {
                // Get english tenant description for saving to default language
                foreach ($dataTranslations as $key => $val) {
                    // Validation language id from translation
                    $language = Language::where('language_id', '=', $key)->first();
                    if (empty($language)) {
                        OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.empty.merchant_language'));
                    }

                    if ($key === $idLanguageEnglish->language_id) {
                        $newtenant->description = $val->description;
                    }
                }
            }

            Event::fire('orbit.tenant.postnewtenant.before.save', array($this, $newtenant));

            $newtenant->save();

            // save to spending rule, the default is Y
            // @author kadek <kadek@dominopos.com>
            $newSpendingRules = new SpendingRule();
            $newSpendingRules->object_id = $newtenant->merchant_id;
            $newSpendingRules->with_spending = 'Y';
            $newSpendingRules->save();

            if (OrbitInput::post('facebook_uri')) {

                // Validation facebvook uri set only for tenant, cannot save as a service
                $validator = Validator::make(
                    array('facebook_uri'    => OrbitInput::post('facebook_uri')),
                    array('facebook_uri'    => 'orbit.empty.for_tenant_only:' . $object_type)
                );

                Event::fire('orbit.tenant.postnewtenant.before.validation', array($this, $validator));

                // Run the validation
                if ($validator->fails()) {
                    $errorMessage = $validator->messages()->first();
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }

                // Save sosmed uri
                $this->saveSocmedUri('facebook', $newtenant->merchant_id, OrbitInput::post('facebook_uri'));

                // For response
                $newtenant->facebook_uri = OrbitInput::post('facebook_uri');
            }

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

            // save Keyword
            $tenantKeywords = array();
            foreach ($keywords as $keyword) {
                $keyword_id = null;

                $existKeyword = Keyword::excludeDeleted()
                    ->where('keyword', '=', $keyword)
                    ->where('merchant_id', '=', $parent_id)
                    ->first();

                if (empty($existKeyword)) {
                    $newKeyword = new Keyword();
                    $newKeyword->merchant_id = $parent_id;
                    $newKeyword->keyword = $keyword;
                    $newKeyword->status = 'active';
                    $newKeyword->created_by = $this->api->user->user_id;
                    $newKeyword->modified_by = $this->api->user->user_id;
                    $newKeyword->save();

                    $keyword_id = $newKeyword->keyword_id;
                    $tenantKeywords[] = $newKeyword;
                } else {
                    $keyword_id = $existKeyword->keyword_id;
                    $tenantKeywords[] = $existKeyword;
                }

                $newKeywordObject = new KeywordObject();
                $newKeywordObject->keyword_id = $keyword_id;
                $newKeywordObject->object_id = $newtenant->merchant_id;
                $newKeywordObject->object_type = $object_type;
                $newKeywordObject->save();

            }
            $newtenant->keywords = $tenantKeywords;

            Event::fire('orbit.tenant.postnewtenant.after.save', array($this, $newtenant));

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
     * @author Irianto Pratama <irianto@dominopos.com>
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

            // validate user mall id for current_mall
            $mall_id = OrbitInput::post('current_mall');
            $listOfMallIds = $user->getUserMallIds($mall_id);
            if (empty($listOfMallIds)) { // invalid mall id
                $errorMessage = 'Invalid mall id.';
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            } else {
                $mall_id = $listOfMallIds[0];
            }

            $retailer_id = OrbitInput::post('retailer_id');
            $user_id = OrbitInput::post('user_id');
            $email = OrbitInput::post('email');
            $status = OrbitInput::post('status');

            // validate user mall id for parent_id
            $parent_id = OrbitInput::post('parent_id');
            $listOfMallIds = $user->getUserMallIds($parent_id);
            if (empty($listOfMallIds)) { // invalid mall id
                $errorMessage = 'Invalid mall id.';
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            } else {
                $parent_id = $listOfMallIds[0];
            }

            $url = OrbitInput::post('url');
            $masterbox_number = OrbitInput::post('masterbox_number');
            $category_ids = OrbitInput::post('category_ids');
            $box_url = OrbitInput::post('box_url');
            $translations = OrbitInput::post('translations');

            $default_merchant_language_id = MerchantLanguage::getLanguageIdByMerchant($mall_id, static::DEFAULT_LANG);
            $id_language_default = OrbitInput::post('id_language_default', $default_merchant_language_id);

            $floor_id = OrbitInput::post('floor_id');
            $unit = OrbitInput::post('unit');
            $phone = OrbitInput::post('phone');
            $object_type = OrbitInput::post('object_type');

            // Begin database transaction
            $this->beginTransaction();

            $validator = Validator::make(
                array(
                    'current_mall'        => $mall_id,
                    'retailer_id'         => $retailer_id,
                    'user_id'             => $user_id,
                    'email'               => $email,
                    'status'              => $status,
                    'parent_id'           => $parent_id,
                    'url'                 => $url,
                    'masterbox_number'    => $masterbox_number,
                    'category_ids'        => $category_ids,
                    'box_url'             => $box_url,
                    'id_language_default' => $id_language_default,
                    'phone'               => $phone,
                    'floor_id'            => $floor_id,
                ),
                array(
                    'current_mall'     => 'orbit.empty.mall',
                    'retailer_id'      => 'required|orbit.empty.tenantstoreandservice',
                    'user_id'          => 'orbit.empty.user',
                    'email'            => 'email|email_exists_but_me',
                    'status'           => 'orbit.empty.tenant_status|orbit.exists.tenant_on_active_campaign',
                    'parent_id'        => 'orbit.empty.mall',
                    'url'              => 'orbit.empty.for_tenant_only:' . $object_type . '|orbit.formaterror.url.web',
                    'masterbox_number' => 'orbit.empty.for_tenant_only:' . $object_type . '|alpha_num|orbit_unique_verification_number:' . $mall_id . ',' . $retailer_id,
                    'category_ids'     => 'array',
                    'phone'            => array('regex:/^\+?\d+$/','orbit.empty.for_tenant_only:' . $object_type),
                    'floor_id'     => 'orbit.empty.floor:' . $parent_id,
                ),
                array(
                    'email_exists_but_me' => Lang::get('validation.orbit.exists.email'),
                    'orbit.exists.tenant_on_active_campaign' => Lang::get('validation.orbit.exists.tenant_on_active_campaign'),
                    'orbit.empty.tenant_floor' => Lang::get('validation.orbit.empty.tenant_floor'),
                    'orbit.empty.tenant_unit' => Lang::get('validation.orbit.empty.tenant_unit'),
                    'orbit_unique_verification_number' => 'The verification number already used by other',
                    'alpha_num' => 'The verification number must letter and number.',
                    'regex' => 'Wrong phone number format',
                    'orbit.formaterror.url.web' => 'Tenant URL is not valid',
                //ACL::throwAccessForbidden($message);
               )
            );

            Event::fire('orbit.tenant.postupdatetenant.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.tenant.postupdatetenant.after.validation', array($this, $validator));

            $updatedtenant = App::make('orbit.empty.tenantstoreandservice');

            // Get post input from form
            OrbitInput::post('user_id', function($user_id) use ($updatedtenant) {
                $updatedtenant->user_id = $user_id;
            });

            OrbitInput::post('email', function($email) use ($updatedtenant) {
                $updatedtenant->email = $email;
            });

            OrbitInput::post('name', function($name) use ($updatedtenant) {
                $updatedtenant->name = $name;
            });

            // OrbitInput::post('description', function($description) use ($updatedtenant) {
            //     $updatedtenant->description = $description;
            // });

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

            OrbitInput::post('url', function($url) use ($updatedtenant) {
                $updatedtenant->url = $url;
            });

            OrbitInput::post('slavebox_number', function($slavebox_number) use ($updatedtenant) {
                $updatedtenant->slavebox_number = $slavebox_number;
            });

            OrbitInput::post('masterbox_number', function($masterbox_number) use ($updatedtenant) {
                $updatedtenant->masterbox_number = $masterbox_number;
            });

            OrbitInput::post('floor_id', function($floor_id) use ($updatedtenant) {
                $floor_db = $this->valid_floor;
                if (count($floor_db) > 0) {
                    $updatedtenant->floor = $floor_db->object_name;
                    $updatedtenant->floor_id = $floor_db->object_id;
                }
            });

            OrbitInput::post('unit', function($unit) use ($updatedtenant) {
                $updatedtenant->unit = $unit;
            });

            OrbitInput::post('external_object_id', function($external_object_id) use ($updatedtenant) {
                $updatedtenant->external_object_id = $external_object_id;
            });

            OrbitInput::post('box_url', function($box_url) use ($updatedtenant) {
                $updatedtenant->box_url = $box_url;
            });

            // @author Irianto Pratama <irianto@dominopos.com>
            // save RetailerTenant - link to tenant
            OrbitInput::post('tenant_id', function($tenant_id) use ($updatedtenant) {
                $this->validateAndSaveLinkToTenant($updatedtenant, $tenant_id);
            });

            // // @author Irianto Pratama <irianto@dominopos.com>
            // $default_translation = [
            //     $id_language_default => [
            //         'description' => $updatedtenant->description
            //     ]
            // ];
            // $this->validateAndSaveTranslations($updatedtenant, json_encode($default_translation), 'update');

            OrbitInput::post('translations', function($translation_json_string) use ($updatedtenant) {
                $this->validateAndSaveTranslations($updatedtenant, $translation_json_string, 'update');
            });

            $updatedtenant->modified_by = $this->api->user->user_id;

            // Get english language_id
            $idLanguageEnglish = Language::select('language_id')
                                ->where('name', '=', 'en')
                                ->first();

            // Check for english content
            $dataTranslations = @json_decode($translations);
            if (json_last_error() != JSON_ERROR_NONE) {
                OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.jsonerror.field.format', ['field' => 'translations']));
            }

            if (! is_null($dataTranslations)) {
                // Get english tenant description for saving to default language
                foreach ($dataTranslations as $key => $val) {
                    // Validation language id from translation
                    $language = Language::where('language_id', '=', $key)->first();
                    if (empty($language)) {
                        OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.empty.merchant_language'));
                    }

                    if ($key === $idLanguageEnglish->language_id) {
                        $updatedtenant->description = $val->description;
                    }
                }
            }

            Event::fire('orbit.tenant.postupdatetenant.before.save', array($this, $updatedtenant));

            $updatedtenant->setUpdatedAt($updatedtenant->freshTimestamp());
            $updatedtenant->save();

            OrbitInput::post('facebook_uri', function($facebook_uri) use($updatedtenant, $retailer_id) {
                $this->saveSocmedUri('facebook', $retailer_id, $facebook_uri);

                // For response
                $updatedtenant->facebook_uri = $facebook_uri;
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
                            'category_id'   => 'orbit.empty.category',
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

            // Delete old data
            $deleted_keyword_object = KeywordObject::where('object_id', '=', $retailer_id)
                                                    ->where('object_type', '=', $object_type);
            $deleted_keyword_object->delete();

            OrbitInput::post('keywords', function($keywords) use ($updatedtenant, $mall_id, $user, $retailer_id, $object_type) {
                // Insert new data
                $tenantKeywords = array();
                foreach ($keywords as $keyword) {
                    $keyword_id = null;

                    $existKeyword = Keyword::excludeDeleted()
                        ->where('keyword', '=', $keyword)
                        ->where('merchant_id', '=', $mall_id)
                        ->first();

                    if (empty($existKeyword)) {
                        $newKeyword = new Keyword();
                        $newKeyword->merchant_id = $mall_id;
                        $newKeyword->keyword = $keyword;
                        $newKeyword->status = 'active';
                        $newKeyword->created_by = $user->user_id;
                        $newKeyword->modified_by = $user->user_id;
                        $newKeyword->save();

                        $keyword_id = $newKeyword->keyword_id;
                        $tenantKeywords[] = $newKeyword;
                    } else {
                        $keyword_id = $existKeyword->keyword_id;
                        $tenantKeywords[] = $existKeyword;
                    }


                    $newKeywordObject = new KeywordObject();
                    $newKeywordObject->keyword_id = $keyword_id;
                    $newKeywordObject->object_id = $retailer_id;
                    $newKeywordObject->object_type = $object_type;
                    $newKeywordObject->save();

                }
                $updatedtenant->keywords = $tenantKeywords;
            });

            // update store advert
            if ($updatedtenant->object_type === 'tenant') {
                $storeAdverts = Advert::excludeDeleted()
                                    ->where('link_object_id', $updatedtenant->merchant_id)
                                    ->update([
                                            'status'     => $updatedtenant->status,
                                        ]);
            }

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
     * @param string            `box_url`                       (optional) - Box url
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
        // flag for limit the query result
        // TODO : should be change in the future
        $limit = FALSE;
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
/*
            if (! ACL::create($user)->isAllowed('view_tenant')) {
                Event::fire('orbit.tenant.getsearchtenant.authz.notallowed', array($this, $user));
                $viewTenantLang = Lang::get('validation.orbit.actionlist.view_tenant');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewTenantLang));
                ACL::throwAccessForbidden($message);
            }
*/
            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->tenantViewRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.tenant.getsearchtenant.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sortby');

            // TODO : change this into something else
            $limited = OrbitInput::get('limited');

            if ($limited === 'yes') {
                $limit = TRUE;
            }

            $object_type = OrbitInput::get('object_type');

            // get user mall_ids
            $parent_id = OrbitInput::get('parent_id');
            $listOfMallIds = $user->getUserMallIds($parent_id);
            if (empty($listOfMallIds)) { // invalid mall id
                $errorMessage = 'Invalid mall id.';
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $validator = Validator::make(
                array(
                    'sortby' => $sort_by,
                ),
                array(
                    'sortby' => 'in:registered_date,retailer_name,retailer_email,retailer_userid,retailer_description,retailerid,retailer_address1,retailer_address2,retailer_address3,retailer_cityid,retailer_city,retailer_countryid,retailer_country,retailer_phone,retailer_fax,retailer_status,retailer_currency,contact_person_firstname,merchant_name,retailer_floor,retailer_unit,retailer_object_type,retailer_external_object_id,retailer_created_at,retailer_updated_at',
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

            $prefix = DB::getTablePrefix();

            // Builder object
            // if flag limit is true then show only merchant_id and name to make the frontend life easier
            // TODO : remove this with something like is_all_retailer just like on orbit-shop
            if ($limit) {
                $tenants = TenantStoreAndService::with('link_to_tenant')
                                 ->select('merchant_id', 'name', 'status')
                                 ->excludeDeleted('merchants');
            } else {

                // Get Facebook social media ID
                $facebookSocmedId = SocialMedia::whereSocialMediaCode('facebook')->first()->social_media_id;

                $tenants = TenantStoreAndService::with('link_to_tenant')
                                 ->select('merchants.*',
                                    DB::raw("(CASE WHEN unit = '' THEN {$prefix}objects.object_name ELSE CONCAT({$prefix}objects.object_name, \" - \", unit) END) AS location"),
                                    'merchant_social_media.social_media_uri as facebook_uri'
                                  )
                                 // A left join to get tenants' Facebook URIs
                                 ->leftJoin('merchant_social_media', function ($join) use ($facebookSocmedId) {
                                    $join->on('merchants.merchant_id', '=', 'merchant_social_media.merchant_id')
                                        ->where('social_media_id', '=', $facebookSocmedId);
                                    })
                                 ->leftJoin('objects', 'objects.object_id', '=', 'merchants.floor_id')
                                 ->excludeDeleted('merchants');
            }

            if ($this->returnBuilder) {
                $tenants->addSelect(DB::raw("GROUP_CONCAT(`{$prefix}categories`.`category_name` ORDER BY category_name ASC SEPARATOR ', ') as tenant_categories"))
                        ->leftJoin('category_merchant','category_merchant.merchant_id','=','merchants.merchant_id')
                        ->leftJoin('categories','categories.category_id','=','category_merchant.category_id')
                        ->where('categories.status', '!=', 'deleted')
                        ->groupBy('merchants.merchant_id');
            }

            // Filter tenant by parent_id / mall id
            $tenants->whereIn('merchants.parent_id', $listOfMallIds);

            // Filter tenant by object_type
            if (! empty($object_type) && is_array($object_type)) {

                //Validate objety_type only parsing 'tenant' or 'service'
                foreach ($object_type as $type) {
                    $validator = Validator::make(
                        array('object_type' => $type),
                        array('object_type' => 'orbit.empty.tenant_type')
                    );

                    // Run the validation
                    if ($validator->fails()) {
                        $errorMessage = $validator->messages()->first();
                        OrbitShopAPI::throwInvalidArgument($errorMessage);
                    }
                }

                $tenants->whereIn('merchants.object_type', $object_type);

            } else {

                // $tenants->where('merchants.object_type', 'tenant');
                $tenants->whereIn('merchants.object_type', ['tenant','service']);
            }

            // Filter tenant by Ids
            OrbitInput::get('tenant_id', function($tenantIds) use ($tenants)
            {
                $tenants->whereIn('merchants.merchant_id', $tenantIds);
            });

            // or using merchant_id
            OrbitInput::get('merchant_id', function($data) use ($tenants)
            {
                $tenants->whereIn('merchants.merchant_id', $data);
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

            // Filter retailer by url
            OrbitInput::get('url', function ($url) use ($tenants) {
                $tenants->whereIn('merchants.url', $url);
            });

            // Filter retailer by box_url
            OrbitInput::get('box_url', function ($box_url) use ($tenants) {
                $tenants->whereIn('merchants.box_url', $box_url);
            });

            // Filter retailer by box_url like
            OrbitInput::get('box_url_like', function ($data) use ($tenants) {
                $tenants->where('merchants.box_url', 'like', "%$data%");
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

            // Filter tenant by floor
            OrbitInput::get('floor_like', function($floor) use ($tenants)
            {
                $tenants->where('objects.object_name', 'like', "%$floor%");
            });

            // Filter tenant by unit
            OrbitInput::get('unit_like', function($unit) use ($tenants)
            {
                $tenants->where('merchants.unit', 'like', "%$unit%");
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

             // Filter tenant by box_url
            OrbitInput::get('box_url', function($box_url) use ($tenants)
            {
                $tenants->whereIn('merchants.box_url', $box_url);
            });

             // Filter tenant by box_url
            OrbitInput::get('box_url', function($box_url) use ($tenants)
            {
                $tenants->where('merchants.box_url', 'like', "%$box_url%");
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
                    } elseif ($relation === 'keywords') {
                        $tenants->with('keywords');
                    } elseif ($relation === 'categories') {
                        $tenants->with([
                            'categories' => function($q) {
                                $q->where('status', 'active');
                            }
                        ]);
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

            // if limit is true show all records
            // TODO : replace this with something else in the future
            if (!$limit)
            {
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
                }
            }

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
                    'retailer_object_type' => 'merchants.object_type',
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
                    'tenant_object_type' => 'merchants.object_type',
                    'tenant_created_at' => 'merchants.created_at',
                    'tenant_updated_at' => 'merchants.updated_at',
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
            $tenants->orderBy($sortBy, $sortMode);

            // Return the instance of Query Builder
            if ($this->returnBuilder) {
                return ['builder' => $tenants, 'count' => RecordCounter::create($_tenants)->count()];
            }

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

    /**
     * GET - Campaign Location
     *
     * @author shelgi <shelgi@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string            `sort_by`                       (optional) - column order by
     * @param string            `sort_mode`                     (optional) - asc or desc
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getCampaignLocation()
    {
        // flag for limit the query result
        // TODO : should be change in the future
        $limit = FALSE;
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
/*
            if (! ACL::create($user)->isAllowed('view_tenant')) {
                Event::fire('orbit.tenant.getsearchtenant.authz.notallowed', array($this, $user));
                $viewTenantLang = Lang::get('validation.orbit.actionlist.view_tenant');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewTenantLang));
                ACL::throwAccessForbidden($message);
            }
*/
            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->tenantViewRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.tenant.getsearchtenant.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sortby');
            $filtermode = OrbitInput::get('filtermode');
            $account_type_id = OrbitInput::get('account_type_id');

            // for advert setup
            $from = OrbitInput::get('from');
            $group_by = OrbitInput::get('group_by');
            $campaign_id = OrbitInput::get('campaign_id');
            $link_type = OrbitInput::get('link_type');
            $merchant_name = OrbitInput::get('merchant_name');
            $merchant_id = OrbitInput::get('merchant_id');
            $object_type = (array) OrbitInput::get('object_type', ['mall', 'tenant']);
            $keywords = OrbitInput::get('keywords');
            $store_name = OrbitInput::get('store_name');
            $mall_name = OrbitInput::get('mall_name');
            $cities = OrbitInput::get('cities');
            $country = OrbitInput::get('country');
            $country_id = OrbitInput::get('country_id');
            $cities = (array) $cities;
            $parent = null;
            $parent_ids = [];

            $validator = Validator::make(
                array(
                    'sortby' => $sort_by,
                    'account_type_id' => $account_type_id,
                    'keywords' => $keywords,
                ),
                array(
                    'sortby' => 'in:registered_date,retailer_name,retailer_email,retailer_userid,retailer_description,retailerid,retailer_address1,retailer_address2,retailer_address3,retailer_cityid,retailer_city,retailer_countryid,retailer_country,retailer_phone,retailer_fax,retailer_status,retailer_currency,contact_person_firstname,merchant_name,retailer_floor,retailer_unit,retailer_external_object_id,retailer_created_at,retailer_updated_at',
                    'account_type_id' => 'orbit.empty.account_type',
                    'keywords' => 'min:3',
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

            $prefix = DB::getTablePrefix();

            // find mall_id for filter
            if (!empty($mall_name)) {
                $parents = Mall::select('merchant_id')->where('merchants.name', 'like', "%$mall_name%")->get();
                foreach ($parents as $key => $value) {
                    $parent_ids[] = $value['merchant_id'];
                }
            }

            $tenants = CampaignLocation::select('merchants.merchant_id',
                                            'merchants.floor',
                                            'merchants.unit',
                                            DB::raw("IF({$prefix}merchants.object_type = 'tenant', pm.merchant_id, {$prefix}merchants.merchant_id) as mall_id"),
                                            DB::raw("IF({$prefix}merchants.object_type = 'tenant', CONCAT({$prefix}merchants.name,' at ', pm.name), CONCAT('Mall at ', {$prefix}merchants.name)) as display_name"),
                                            'merchants.status',
                                            DB::raw("IF({$prefix}merchants.object_type = 'tenant', (select language_id from {$prefix}languages where name = pm.mobile_default_language), (select language_id from {$prefix}languages where name = {$prefix}merchants.mobile_default_language)) as default_language"),
                                            DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN pm.city
                                                          WHEN {$prefix}merchants.object_type = 'mall' THEN {$prefix}merchants.city
                                                    END as city"),
                                            DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN pm.country
                                                          WHEN {$prefix}merchants.object_type = 'mall' THEN {$prefix}merchants.country
                                                    END as country")
                                        )
                                       ->leftjoin('merchants as pm', DB::raw("pm.merchant_id"), '=', 'merchants.parent_id')
                                       ->where('merchants.status', '=', 'active')
                                       ->whereIn('merchants.object_type', $object_type);

            if ($from === 'detail') {
                if ($link_type === 'promotion' || $link_type === 'news') {
                    $tenants = $tenants->join('news_merchant as nm', DB::raw("nm.merchant_id"), '=', 'merchants.merchant_id')
                                    ->whereRaw("nm.news_id = {$this->quote($campaign_id)}");
                } elseif ($link_type === 'coupon') {
                    $tenants = $tenants->join('promotion_retailer as pr', DB::raw("pr.retailer_id"), '=', 'merchants.merchant_id')
                                    ->whereRaw("pr.promotion_id = {$this->quote($campaign_id)}");
                } elseif ($link_type === 'coupon_redeem') {
                    $tenants = $tenants->join('promotion_retailer_redeem as prr', function ($q) use ($campaign_id) {
                                                $q->on(DB::raw("prr.retailer_id"), '=', 'merchants.merchant_id');
                                                  //->on(DB::raw("prr.object_type"), '=', DB::raw("'tenant'"));
                                            })
                                        ->whereRaw("prr.promotion_id = {$this->quote($campaign_id)}");
                }
            }

            // Need to overide the query for advert
            if ($from === 'advert') {
                if ($link_type === 'coupon' ) {
                    $tenants = PromotionRetailer::select(
                                    'merchants.merchant_id',
                                    DB::raw("IF({$prefix}merchants.object_type = 'tenant', pm.merchant_id, {$prefix}merchants.merchant_id) as mall_id"),
                                    'merchants.status',
                                    'merchants.object_type',
                                    DB::raw("IF({$prefix}merchants.object_type = 'tenant', pm.name, `{$prefix}merchants`.`name`) AS display_name"),
                                    'merchants.country',
                                    'merchants.city'
                                )
                                ->leftjoin('merchants', 'merchants.merchant_id', '=', 'promotion_retailer.retailer_id')
                                ->leftjoin('merchants as pm', DB::raw("pm.merchant_id"), '=', DB::raw("IF(isnull(`{$prefix}merchants`.`parent_id`), `{$prefix}merchants`.`merchant_id`, `{$prefix}merchants`.`parent_id`) "))
                                ->where('promotion_id', $campaign_id)
                                ->groupBy('mall_id');

                } elseif ($link_type === 'promotion' || $link_type === 'news') {
                    $tenants = NewsMerchant::select(
                                    'merchants.merchant_id',
                                    DB::raw("IF({$prefix}merchants.object_type = 'tenant', pm.merchant_id, {$prefix}merchants.merchant_id) as mall_id"),
                                    'merchants.status',
                                    'merchants.object_type',
                                    DB::raw("IF({$prefix}merchants.object_type = 'tenant', pm.name, `{$prefix}merchants`.`name`) AS display_name"),
                                    'merchants.country',
                                    'merchants.city'
                                )
                                ->leftjoin('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                                ->leftjoin('merchants as pm', DB::raw("pm.merchant_id"), '=', DB::raw("IF(isnull(`{$prefix}merchants`.`parent_id`), `{$prefix}merchants`.`merchant_id`, `{$prefix}merchants`.`parent_id`) "))
                                ->where('news_id', $campaign_id)
                                ->groupBy('mall_id');
                } elseif ($link_type === 'store') {
                    $tenants = CampaignLocation::select('merchants.merchant_id',
                                                    DB::raw("IF({$prefix}merchants.object_type = 'tenant', pm.merchant_id, {$prefix}merchants.merchant_id) as mall_id"),
                                                    DB::raw("{$prefix}merchants.name as display_name"),
                                                    'merchants.status',
                                                    'merchants.country',
                                                    'merchants.city',
                                                    DB::raw("IF({$prefix}merchants.object_type = 'tenant', (select language_id from {$prefix}languages where name = pm.mobile_default_language), (select language_id from {$prefix}languages where name = {$prefix}merchants.mobile_default_language)) as default_language")
                                                )
                                               ->leftjoin('merchants as pm', DB::raw("pm.merchant_id"), '=', 'merchants.parent_id')
                                               ->where('merchants.object_type', 'tenant')
                                               ->where('merchants.status', '!=', 'deleted')
                                               ->groupBy('merchants.name');

                    if (! empty($merchant_name)) {
                        $tenants = CampaignLocation::select('merchants.merchant_id',
                                                        DB::raw("IF({$prefix}merchants.object_type = 'tenant', pm.merchant_id, {$prefix}merchants.merchant_id) as mall_id"),
                                                        DB::raw("pm.name as display_name"),
                                                        'merchants.status',
                                                        'merchants.country',
                                                        'merchants.city',
                                                        DB::raw("IF({$prefix}merchants.object_type = 'tenant', (select language_id from {$prefix}languages where name = pm.mobile_default_language), (select language_id from {$prefix}languages where name = {$prefix}merchants.mobile_default_language)) as default_language")
                                                    )
                                                   ->leftjoin('merchants as pm', DB::raw("pm.merchant_id"), '=', 'merchants.parent_id')
                                                   ->where('merchants.object_type', 'tenant')
                                                   ->where('merchants.status', '!=', 'deleted')
                                                   ->where('merchants.name', '=', $merchant_name);
                    }

                    if (! empty($merchant_id)) {
                        $tenants = CampaignLocation::select('merchants.merchant_id', 'merchants.name as display_name',
                                                        DB::raw("IF({$prefix}merchants.object_type = 'tenant', pm.merchant_id, {$prefix}merchants.merchant_id) as mall_id"),
                                                        'merchants.status',
                                                        'merchants.country',
                                                        'merchants.city',
                                                        DB::raw("IF({$prefix}merchants.object_type = 'tenant', (select language_id from {$prefix}languages where name = pm.mobile_default_language), (select language_id from {$prefix}languages where name = {$prefix}merchants.mobile_default_language)) as default_language")
                                                    )
                                                   ->leftjoin('merchants as pm', DB::raw("pm.merchant_id"), '=', 'merchants.parent_id')
                                                   ->where('merchants.object_type', 'tenant')
                                                   ->where('merchants.status', '!=', 'deleted')
                                                   ->where('merchants.merchant_id', '=', $merchant_id);

                    }

                } elseif ($link_type === 'no_link' || $link_type === 'information' || $link_type === 'url') {
                    $tenants = CampaignLocation::select('merchants.merchant_id',
                                    DB::raw("IF({$prefix}merchants.object_type = 'tenant', pm.merchant_id, {$prefix}merchants.merchant_id) as mall_id"),
                                    DB::raw("IF({$prefix}merchants.object_type = 'tenant', pm.name, `{$prefix}merchants`.`name`) AS display_name"),
                                    'merchants.status',
                                    'merchants.country',
                                    'merchants.city'
                                )
                               ->leftjoin('merchants as pm', DB::raw("pm.merchant_id"), '=', DB::raw("IF(isnull(`{$prefix}merchants`.`parent_id`), `{$prefix}merchants`.`merchant_id`, `{$prefix}merchants`.`parent_id`) "))
                               ->leftjoin('mall_countries', 'mall_countries.country', '=', DB::raw('pm.country'))
                               ->whereIn('merchants.object_type', ['mall', 'tenant'])
                               ->where('merchants.status', '=', 'active')
                               ->where(DB::raw("pm.status"), '=', 'active')
                               ->groupBy('mall_id');

                    // filter country_id
                    OrbitInput::get('country_id', function($country_id) use ($tenants, $prefix)
                    {
                        $tenants->where(DB::raw("(IF({$prefix}merchants.object_type = 'tenant', pm.country_id, {$prefix}merchants.country_id))"), '=', $country_id);
                    });
                }
                else if ($link_type === 'mall') {
                    $tenants = Mall::select(
                        'merchants.merchant_id', 'merchants.name as display_name', 'merchants.status'
                    )
                    ->where('merchants.status', 'active');
                }
            }

            $account_name = '';
            if (! is_null($this->valid_account_type)) {
                $account_name = $this->valid_account_type->type_name;
            }

            // filter by city (can be multiple)
            if (!empty($cities)) {
                $cityName = implode("','", $cities);
                $tenants->havingRaw("city in ('{$cityName}')");
            }

            // filter by store name
            if (!empty($store_name) && $account_name !== 'Mall') {
                $tenants->where('merchants.name', 'like', "%$store_name%");
            }

            // filter by mall name and store name
            if (!empty($parent_ids)
                && $account_name !== 'Mall'
            ) {
                $tenants->where(function($query) use ($user, $parent_ids) {
                    $query->whereIn('merchants.parent_id', $parent_ids);

                    if ($user->campaignAccount->accountType->type_name === 'Dominopos') {
                        $query->orWhereIn('merchants.merchant_id', $parent_ids);
                    }
                });
            }

            // filter by country
            if (!empty($country) && $account_name !== 'Mall') {
                $tenants->where(DB::raw("(IF({$prefix}merchants.object_type = 'tenant', pm.country, {$prefix}merchants.country))"), '=', $country);
            }

            // filter by country_id
            if (!empty($country_id) && $account_name !== 'Mall') {
                $tenants->where(DB::raw("(IF({$prefix}merchants.object_type = 'tenant', pm.country_id, {$prefix}merchants.country_id))"), '=', $country_id);
            }

            // filter by mall name for account type mall (PMP account setup admin portal)
            if (!empty($mall_name) && $account_name === 'Mall') {
                $tenants->where('merchants.name', 'like', "%$mall_name%");
            }

            // show empty result when search mall_name but parent_ids not found
            if (!empty($mall_name) && empty($parent_ids) && empty($store_name)) {
                $tenants->where('merchants.name', '=', $mall_name);
            }

            // this is for request from pmp account listing on admin portal
            $user_id = OrbitInput::get('user_id');
            if (!empty($user_id)) {
                $user = User::with('role')->where('user_id', '=', $user_id)->first();
                if (!is_object($user)) {
                    OrbitShopAPI::throwInvalidArgument('user not found');
                }
            }

            if (in_array(strtolower($user->role->role_name), $this->campaignRole)) {
                if ($user->campaignAccount->is_link_to_all === 'N') {
                    $tenants->join('user_merchant', function($q) use ($user)
                    {
                        $q->on('user_merchant.merchant_id', '=', 'merchants.merchant_id')
                             ->where('user_merchant.user_id', '=', $user->user_id);
                    });
                } else {
                    if ($user->campaignAccount->accountType->type_name === '3rd Party') {
                        $tenants->whereRaw("{$prefix}merchants.object_type = 'mall'");
                    }
                }

                if (empty($from) && empty($link_type) && empty($merchant_id)) {
                    $tenants->where('merchants.status', '=', 'active');
                }

            }

            // filter by account type
            if (! is_null($this->valid_account_type)) {
                $account_type = $this->valid_account_type;
                $permission = [
                    'Mall'      => 'mall',
                    'Merchant'  => 'tenant',
                    'Agency'    => 'mall_tenant',
                    '3rd Party' => 'mall',
                    'Dominopos' => 'mall_tenant'
                ];

                // access
                if (array_key_exists($account_type->type_name, $permission)) {
                    $access = implode("','", explode("_", $permission[$account_type->type_name]));

                    $tenants->whereRaw("{$prefix}merchants.object_type in ('{$access}')");
                }

                // unique link to tenant
                if ($account_type->unique_rule !== 'none') {
                    $unique_rule = implode("','", explode("_", $account_type->unique_rule));

                    $userId = OrbitInput::get('id');
                    $tenants->where(function($query) use ($prefix, $unique_rule, $userId) {
                        $query->whereRaw("NOT EXISTS (
                                SELECT 1
                                FROM {$prefix}user_merchant um
                                JOIN {$prefix}campaign_account ca
                                    ON ca.user_id = um.user_id
                                JOIN {$prefix}account_types at
                                    ON at.account_type_id = ca.account_type_id
                                    AND at.unique_rule != 'none'
                                    AND at.status = 'active'
                                WHERE
                                    um.object_type IN ('{$unique_rule}')
                                    AND {$prefix}merchants.merchant_id = um.merchant_id
                                GROUP BY um.merchant_id
                        )");

                        // Add filter for selected tenants by current user.
                        // This should be executed ONLY from pmp accounts update page (select tenants modal)
                        if (! empty($userId) && empty($store_name)) {
                            $query->orWhereRaw("EXISTS (
                                    SELECT 1
                                    FROM {$prefix}user_merchant um2
                                    JOIN {$prefix}campaign_account ca2
                                        ON ca2.user_id = um2.user_id
                                    JOIN {$prefix}account_types at2
                                        ON at2.account_type_id = ca2.account_type_id
                                        AND at2.unique_rule != 'none'
                                        AND at2.status = 'active'
                                    WHERE
                                        um2.object_type IN ('{$unique_rule}')
                                        AND {$prefix}merchants.merchant_id = um2.merchant_id
                                        AND um2.user_id = '{$userId}'
                                    GROUP BY um2.merchant_id
                            )");
                        }
                    });
                }
            }

            if (! empty($keywords)) {
                if ($from === 'advert') {
                    if ($link_type === 'store' && ! empty($merchant_name)) {
                        $tenants->where(DB::raw('pm.name'), 'like', "$keywords%"); // find mall
                    } elseif ($link_type === 'store') {
                        $tenants->where('merchants.name', 'like', "$keywords%"); // find tenant
                    } elseif (in_array($link_type, ['promotion', 'news', 'coupon'])) {
                        $tenants->having(DB::raw('display_name'), 'like', "$keywords%"); // find mall
                    }
                } else {
                    $tenants->where('merchants.name', 'like', "$keywords%"); // find tenant and mall
                }
            }

            if (! empty($keywords)) {
                if ($from === 'advert') {
                    if ($link_type === 'store' && ! empty($merchant_name)) {
                        $tenants->where(DB::raw('pm.name'), 'like', "$keywords%"); // find mall
                    } elseif ($link_type === 'store') {
                        $tenants->where('merchants.name', 'like', "$keywords%"); // find tenant
                    } elseif (in_array($link_type, ['promotion', 'news', 'coupon'])) {
                        $tenants->having(DB::raw('display_name'), 'like', "$keywords%"); // find mall
                    }
                } else {
                    $tenants->where('merchants.name', 'like', "$keywords%"); // find tenant and mall
                }
            }

            if ($filtermode === 'available') {
                $tenants->whereRaw("
                    NOT EXISTS (
                        SELECT 1
                        FROM {$prefix}user_merchant
                        WHERE {$prefix}user_merchant.object_type IN ('mall', 'tenant')
                            AND {$prefix}merchants.merchant_id = {$prefix}user_merchant.merchant_id
                    )");
            }

            // Only showing tenant only, provide for coupon redemption place.
            if ($filtermode === 'tenant') {
                $tenants->whereRaw("
                    NOT EXISTS (
                        SELECT 1
                        FROM {$prefix}user_merchant
                        WHERE {$prefix}user_merchant.object_type = 'mall'
                            AND {$prefix}merchants.merchant_id = {$prefix}user_merchant.merchant_id
                    )");
            }

            if ($filtermode === 'mall') {
                $tenants->whereRaw("
                    NOT EXISTS (
                        SELECT 1
                        FROM {$prefix}user_merchant
                        WHERE {$prefix}user_merchant.object_type = 'tenant'
                            AND {$prefix}merchants.merchant_id = {$prefix}user_merchant.merchant_id
                    )");
            }

            if ($from !== 'advert') {
               $tenants->groupBy('merchants.merchant_id');
            }

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_tenants = clone $tenants;

            // Cache the result of database calls
            OrbitDBCache::create(Config::get('orbit.cache.database', []))->remember($tenants);

            $recordCounter = RecordCounter::create($_tenants);
            OrbitDBCache::create(Config::get('orbit.cache.database', []))->remember($recordCounter->getQueryBuilder());

            $take = PaginationNumber::parseTakeFromGet('link_to_tenant');
            $tenants->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $tenants->skip($skip);

            // Default sort by
            $sortBy = 'display_name';
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

                    'display_name' => 'display_name',
                );

                if (array_key_exists($_sortBy, $sortByMapping)) {
                    $sortBy = $sortByMapping[$_sortBy];
                }
            });

            // this parameter is intended for tenant listing for tenant dropdown list so it will
            // ignore the sort by status that will broke alphabetical order.
            $true_sort = OrbitInput::get('true_sort');

            if ($sortBy !== 'merchants.status') {
                if(empty($true_sort)) {
                    $tenants->orderBy('merchants.status', 'asc');
                }
            }

            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });
            $tenants->orderBy($sortBy, $sortMode);

            // echo '<pre>'; print_r($tenants->toSql()); die;

            $totalTenants = $recordCounter->count();
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

        // Check existing tenant (with type tenant or service)
        Validator::extend('orbit.empty.tenantstoreandservice', function ($attribute, $value, $parameters){
            $merchant = TenantStoreAndService::excludeDeleted()
                        ->where(function($q) {
                             $q->where('object_type', 'tenant')
                               ->orWhere('object_type', 'service');
                        })
                        ->where('merchant_id', $value)
                        ->first();

            if (empty($merchant)) {
                return FALSE;
            }

            App::instance('orbit.empty.tenantstoreandservice', $merchant);

            return TRUE;
        });

        // Check existing tenant (with type tenant or service)
        Validator::extend('orbit.empty.for_tenant_only', function ($attribute, $value, $parameters){
            if ($parameters[0] !== 'tenant') {
                return FALSE;
            }

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

        // Check the existance of the tenant type
        Validator::extend('orbit.empty.tenant_type', function ($attribute, $value, $parameters) {
            $valid = false;
            $statuses = array('tenant', 'service');
            foreach ($statuses as $status) {
                if($value === $status) $valid = $valid || TRUE;
            }

            return $valid;
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

        // Check floor
        Validator::extend('orbit.empty.tenant_floor', function ($attribute, $value, $parameters) {
            $mall_id = $parameters[0];
            $floor = $parameters[1];
            // check if only status is being set to active
            if ($value === 'active') {
                $floor_db = Object::excludeDeleted()
                                  ->where('object_type','floor')
                                  ->where('merchant_id',$mall_id)
                                  ->where('object_name',$floor)
                                  ->first();
                if (empty($floor_db)) {
                    return FALSE;
                }
            }

            return TRUE;
        });

        // Check unit
        Validator::extend('orbit.empty.tenant_unit', function ($attribute, $value, $parameters) {
            $unit = $parameters[0];
            // check if only status is being set to active
            if ($value === 'active' && empty($unit)) {
                return FALSE;
            }

            return TRUE;
        });

        // tenant cannot be inactive if have linked to news, promotion, and coupon.
        Validator::extend('orbit.exists.tenant_on_inactive_have_linked', function ($attribute, $value, $parameters) {
            $updatedtenant = App::make('orbit.empty.tenant');

            // check if only current status is active and being set to inactive
            if ($updatedtenant->status === 'active' && $value === 'inactive') {
                $tenant_id = $updatedtenant->merchant_id;

                // check tenant if exists in coupons.
                $coupon = CouponRetailer::whereHas('coupon', function($q) {
                        $q->excludeDeleted();
                    })
                    ->where('retailer_id',$tenant_id)
                    ->first();

                if (! empty($coupon)) {
                    return FALSE;
                }

                // check tenant if exists in news.
                $news = NewsMerchant::whereHas('news', function($q) {
                        $q->excludeDeleted()
                          ->where('object_type','news');
                    })
                    ->where('merchant_id',$tenant_id)
                    ->first();

                if (! empty($news)) {
                    return FALSE;
                }

                // check tenant if exists in promotion.
                $promotion = NewsMerchant::whereHas('news', function($q) {
                        $q->excludeDeleted()
                          ->where('object_type','promotion');
                    })
                    ->where('merchant_id',$tenant_id)
                    ->first();

                if (! empty($promotion)) {
                    return FALSE;
                }
            }

            return TRUE;
        });

        // tenant cannot be inactive if news, promotion, and coupon status is not started, ongoing and paused.
        Validator::extend('orbit.exists.tenant_on_active_campaign', function ($attribute, $value, $parameters) {
            $updatedtenant = App::make('orbit.empty.tenantstoreandservice');
            $tenant_id = $updatedtenant->merchant_id;

            $mall = CampaignLocation::select('parent_id')->where('merchant_id', '=', $tenant_id)->first();

            $timezone = Mall::leftJoin('timezones','timezones.timezone_id','=','merchants.timezone_id')
                ->where('merchants.merchant_id','=', $mall->parent_id)
                ->first();

            $timezoneName = $timezone->timezone_name;

            $nowMall = Carbon::now($timezoneName);
            $dateNowMall = $nowMall->toDateString();

            $prefix = DB::getTablePrefix();

            // check if only current status is active and being set to inactive
            if ($updatedtenant->status === 'active' && $value === 'inactive') {
                $tenant_id = $updatedtenant->merchant_id;

                // check tenant if exists in coupons.
                $coupon = PromotionRetailer::leftjoin('promotions', 'promotions.promotion_id', '=', 'promotion_retailer.promotion_id')
                    ->leftjoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'promotions.campaign_status_id')
                    ->whereRaw("(CASE WHEN {$prefix}promotions.end_date < {$this->quote($nowMall)} THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END) NOT IN ('stopped', 'expired')")
                    ->where('promotion_retailer.retailer_id', $tenant_id)
                    ->first();

                if (! empty($coupon)) {
                    return FALSE;
                }

                // check tenant if exists in news & promotions.
                $news = NewsMerchant::leftjoin('news', 'news.news_id', '=', 'news_merchant.news_id')
                    ->leftjoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'news.campaign_status_id')
                    ->whereRaw("(CASE WHEN {$prefix}news.end_date < {$this->quote($nowMall)} THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END) NOT IN ('stopped', 'expired')")
                    ->where('news_merchant.merchant_id', $tenant_id)
                    ->first();


                if (! empty($news) ) {
                    return FALSE;
                }

            }

            return TRUE;
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
            $category = Category::excludeDeleted()
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
            $url = 'http://' . $value;

            $pattern = '@^((http:\/\/www\.)|(www\.)|(http:\/\/))[a-zA-Z0-9._-]+\.[a-zA-Z.]{2,5}$@';

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
            $currentMall = $parameters[0];

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

        // Check if the merchant verification number is unique
        Validator::extend('orbit_unique_verification_number', function ($attribute, $value, $parameters) {
            // Current Mall
            $parent_id = $parameters[0];
            $tenant_id = $parameters[1];
            // Check the tenants which has verification number posted
            $tenantVerificationNumber = Tenant::excludeDeleted()
                    ->where('object_type', 'tenant')
                    ->where('masterbox_number', $value)
                    ->where('parent_id', $parent_id)
                    ->first();

            // Check verification number tenant with cs verification number
            $csVerificationNumber = UserVerificationNumber::
                    where('verification_number', $value)
                    ->where('merchant_id', $parent_id)
                    ->first();

            if ( (! empty($tenantVerificationNumber) && $tenantVerificationNumber->merchant_id !== $tenant_id) || ! empty($csVerificationNumber)) {
                return FALSE;
            }

            return TRUE;
        });

        // @author Irianto Pratama <irianto@dominopos.com>
        // Check if tenant_id is not exist.
        Validator::extend('orbit.exists.tenant_id', function ($attribute, $value, $parameters) {
            $retailertenant = RetailerTenant::where('tenant_id', $value)
                            ->first();
            if (! empty($retailertenant)) {
                return FALSE;
            }

            App::instance('orbit.exists.tenant_id', $retailertenant);

            return TRUE;
        });

        Validator::extend('orbit.empty.floor', function ($attribute, $value, $parameters) {
            $merchant_id = $parameters[0];

            $floor = Object::excludeDeleted()
                        ->where('object_id', $value)
                        ->where('object_type', 'floor')
                        ->where('merchant_id', $merchant_id)
                        ->first();

            if (! count($floor) > 0) {
                return FALSE;
            }

            $this->valid_floor = $floor;

            return TRUE;
        });

        Validator::extend('orbit.empty.account_type', function ($attribute, $value, $parameters) {
            $account_type_id = $value;

            $account_type = AccountType::excludeDeleted()
                        ->where('account_type_id', $account_type_id)
                        ->first();

            if (! is_object($account_type)) {
                return FALSE;
            }

            $this->valid_account_type = $account_type;

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

            // if (! ACL::create($user)->isAllowed('view_tenant')) {
            //     Event::fire('orbit.tenant.getcitylist.authz.notallowed', array($this, $user));
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

            Event::fire('orbit.retailer.getcitylist.after.authz', array($this, $user));

            $tenants = Tenant::excludeDeleted()
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

        $valid_fields = ['description', 'meta_description'];
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

                $tenant->setRelation('translation_'. $new_translation->merchant_language_id, $new_translation);
            }
            elseif ($op === 'update') {
                /** @var MerchantTranslation $existing_translation */
                $existing_translation = $operation[1];
                $data = $operation[2];
                foreach ($data as $field => $value) {
                    $existing_translation->{$field} = $value;
                }
                $existing_translation->status = $tenant->status;
                $existing_translation->modified_by = $this->api->user->user_id;
                $existing_translation->save();

                $tenant->setRelation('translation_'. $existing_translation->merchant_language_id, $existing_translation);
            }
            elseif ($op === 'delete') {
                /** @var MerchantTranslation $existing_translation */
                $existing_translation = $operation[1];
                $existing_translation->modified_by = $this->api->user->user_id;
                $existing_translation->delete();
            }
        }
    }

    /**
     * @param Retailer $tenant
     * @param string $tenant_id
     * @throws InvalidArgsException
     *
     * @author Irianto Pratama <irianto@dominopos.com>
     */
    private function validateAndSaveLinkToTenant($tenant, $tenant_id)
    {
        $retailertenant = RetailerTenant::where('retailer_id', $tenant->merchant_id)
                ->first();

        if (empty($retailertenant) || $retailertenant->tenant_id !== $tenant_id) {
            $validator = Validator::make(
                array(
                    'tenant_id'     => $tenant_id,
                ),
                array(
                    'tenant_id'     => 'orbit.exists.tenant_id',
                )
            );

            Event::fire('orbit.tenant.before.retailertenantvalidation', array($this, $validator));

            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            Event::fire('orbit.tenant.after.retailertenantvalidation', array($this, $validator));
        }

        if (! empty($retailertenant)) {
            $retailertenant->tenant_id = $tenant_id;
            $retailertenant->save();
        } else {
            $retailertenant = new RetailerTenant();
            $retailertenant->retailer_id = $tenant->merchant_id;
            $retailertenant->tenant_id = $tenant_id;
            $retailertenant->save();
        }
        $tenant->setRelation('link_to_tenant', $retailertenant);
    }

    public function setReturnBuilder($bool)
    {
        $this->returnBuilder = $bool;

        return $this;
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }
}
