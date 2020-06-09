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
use DominoPOS\OrbitUploader\Uploader as OrbitUploader;

class MallAPIController extends ControllerAPI
{
    /**
     * Flag to return the query builder.
     *
     * @var Builder
     */
    protected $returnBuilder = FALSE;

    protected $valid_lang = NULL;
    protected $valid_mall_lang = NULL;
    protected $valid_timezone = NULL;

    protected $default = [
        'timezone'                      => 'Asia/Jakarta',
        'currency'                      => 'IDR',
        'currency_symbol'               => 'Rp',
        'vat_included'                  => 'no',
        'campaign_base_price_promotion' => 200000,
        'campaign_base_price_coupon'    => 200000,
        'campaign_base_price_news'      => 200000,
        'widgets'                 => [
            [
                'type'      => 'tenant',
                'object_id' => 0,
                'order'     => 1,
                'animation' => 'none',
                'status'    => 'active',
                'slogan'    => [
                    'default' => 'View All Stores',
                    'en'      => 'View All Stores'
                ]
            ],
            [
                'type'      => 'promotion',
                'object_id' => 0,
                'order'     => 2,
                'animation' => 'none',
                'status'    => 'active',
                'slogan'    => [
                    'default' => 'Latest Promotions',
                    'en'      => 'Latest Promotions'
              ]
            ],
            [
                'type'      => 'news',
                'object_id' => 0,
                'order'     => 3,
                'animation' => 'none',
                'status'    => 'active',
                'slogan'    => [
                    'default' => 'Get the Latest News',
                    'en'      => 'Get the Latest News'
              ]
            ],
            [
                'type'      => 'coupon',
                'object_id' => 0,
                'order'     => 4,
                'animation' => 'none',
                'status'    => 'active',
                'slogan'    => [
                    'default' => 'Your Available Coupons',
                    'en'      => 'Your Available Coupons'
              ]
            ],
            [
                'type'      => 'lucky_draw',
                'object_id' => 0,
                'order'     => 5,
                'animation' => 'none',
                'status'    => 'active',
                'slogan'    => [
                    'default' => 'Your Lucky Draw Number',
                    'en'      => 'Your Lucky Draw Number'
              ]
            ],
            [
                'type'      => 'service',
                'object_id' => 0,
                'order'     => 6,
                'animation' => 'none',
                'status'    => 'active',
                'slogan'    => [
                    'default' => 'View All Service',
                    'en'      => 'View All Service'
              ]
            ],
            [
                'type'      => 'free_wifi',
                'object_id' => 0,
                'order'     => 7,
                'animation' => 'none',
                'status'    => 'active',
                'slogan'    => [
                    'default' => 'View All Get Internet Access',
                    'en'      => 'View All Get Internet Access'
              ]
            ]
        ],
        'age_ranges' => [
            [
                'range_name' => '0-14',
                'min_value'  => '0',
                'max_value'  => '14',
                'status'     => 'active'
            ],
            [
                'range_name' => '15-24',
                'min_value'  => '15',
                'max_value'  => '24',
                'status'     => 'active'
            ],
            [
                'range_name' => '25-34',
                'min_value'  => '25',
                'max_value'  => '34',
                'status'     => 'active'
            ],
            [
                'range_name' => '35-44',
                'min_value'  => '35',
                'max_value'  => '44',
                'status'     => 'active'
            ],
            [
                'range_name' => '45-54',
                'min_value'  => '45',
                'max_value'  => '54',
                'status'     => 'active'
            ],
            [
                'range_name' => '55 +',
                'min_value'  => '55',
                'max_value'  => '0',
                'status'     => 'active'
            ],
            [
                'range_name' => 'Unknown',
                'min_value'  => '0',
                'max_value'  => '0',
                'status'     => 'active'
            ]
        ]
      ];

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
     * POST - Add new mall
     *
     * @author Kadek <kadek@dominopos.com>
     * @author Rio Astamal <me@rioastamal.net>
     * @author Irianto <irianto@dominopos.com>
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
     * @param string     `province`                (optional) - Name of the province
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
     * @param string     `operating_hours`         (optional) - Mall operating hours
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
            $mall_name = trim(OrbitInput::post('name'));
            $password = OrbitInput::post('password');
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
            $currency = OrbitInput::post('currency', $this->default['currency']);
            $currency_symbol = OrbitInput::post('currency_symbol', $this->default['currency_symbol']);
            $tax_code1 = OrbitInput::post('tax_code1');
            $tax_code2 = OrbitInput::post('tax_code2');
            $tax_code3 = OrbitInput::post('tax_code3');
            $slogan = OrbitInput::post('slogan');
            $vat_included = OrbitInput::post('vat_included', $this->default['vat_included']);
            $contact_person_firstname = OrbitInput::post('contact_person_firstname');
            $contact_person_lastname = OrbitInput::post('contact_person_lastname');
            $contact_person_position = OrbitInput::post('contact_person_position');
            $contact_person_phone = OrbitInput::post('contact_person_phone');
            $contact_person_phone2 = OrbitInput::post('contact_person_phone2');
            $contact_person_email = OrbitInput::post('contact_person_email');
            $sector_of_activity = OrbitInput::post('sector_of_activity', 'Mall');
            $object_type = OrbitInput::post('object_type');
            $parent_id = OrbitInput::post('parent_id');
            $url = OrbitInput::post('url');
            $masterbox_number = OrbitInput::post('masterbox_number');
            $slavebox_number = OrbitInput::post('slavebox_number');
            $mobile_default_language = OrbitInput::post('mobile_default_language');
            $pos_language = OrbitInput::post('pos_language');
            $timezoneName = OrbitInput::post('timezone', $this->default['timezone']);
            $domain = OrbitInput::post('domain');
            $languages = OrbitInput::post('languages', []);
            $partner_ids = OrbitInput::post('partner_ids', []);
            $floors = OrbitInput::post('floors');
            $campaign_base_price_promotion = OrbitInput::post('campaign_base_price_promotion', $this->default['campaign_base_price_promotion']);
            $campaign_base_price_coupon = OrbitInput::post('campaign_base_price_coupon', $this->default['campaign_base_price_coupon']);
            $campaign_base_price_news = OrbitInput::post('campaign_base_price_news', $this->default['campaign_base_price_news']);
            $geo_point_latitude = OrbitInput::post('geo_point_latitude');
            $geo_point_longitude = OrbitInput::post('geo_point_longitude');
            $geo_area = OrbitInput::post('geo_area');
            $free_wifi_status = OrbitInput::post('free_wifi_status', 'inactive');
            $operating_hours = OrbitInput::post('operating_hours');
            $is_subscribed = OrbitInput::post('is_subscribed', 'Y');
            $logo = OrbitInput::files('logo');
            $maps = OrbitInput::files('maps');
            $ipcountry = OrbitInput::post('ipcountry');
            $ipcity = OrbitInput::post('ipcity', []);
            $disable_ads = OrbitInput::post('disable_ads', 'n');
            $disable_ymal = OrbitInput::post('disable_ymal', 'n');
            $video_id_1 = OrbitInput::post('video_id_1');
            $video_id_2 = OrbitInput::post('video_id_2');
            $video_id_3 = OrbitInput::post('video_id_3');
            $video_id_4 = OrbitInput::post('video_id_4');
            $video_id_5 = OrbitInput::post('video_id_5');
            $video_id_6 = OrbitInput::post('video_id_6');
            $other_photo_section_title = OrbitInput::post('other_photo_section_title');
            $mall_google_indoor_map = OrbitInput::post('mall_google_indoor_map');
            $mall_google_indoor_streetview = OrbitInput::post('mall_google_indoor_streetview');

            // generate array validation image
            $logo_validation = $this->generate_validation_image('mall_logo', $logo, 'orbit.upload.mall.logo');
            $maps_validation = $this->generate_validation_image('mall_map', $maps, 'orbit.upload.mall.map', 3);

            // for a while this declaration with default value
            $widgets = OrbitInput::post('widgets', $this->default['widgets']);
            $age_ranges = OrbitInput::post('age_ranges', $this->default['age_ranges']);

            $mall_google_indoor_map = OrbitInput::post('mall_google_indoor_map');
            $mall_google_indoor_streetview = OrbitInput::post('mall_google_indoor_streetview');

            $validation_data = [
                'name'                          => $mall_name,
                'email'                         => $email,
                'password'                      => $password,
                'address_line1'                 => $address_line1,
                'city'                          => $city,
                'country'                       => $country,
                'phone'                         => $phone,
                'url'                           => $url,
                'contact_person_firstname'      => $contact_person_firstname,
                'contact_person_lastname'       => $contact_person_lastname,
                'contact_person_email'          => $contact_person_email,
                'status'                        => $status,
                'parent_id'                     => $parent_id,
                'start_date_activity'           => $start_date_activity,
                'end_date_activity'             => $end_date_activity,
                'timezone'                      => $timezoneName,
                'currency'                      => $currency,
                'currency_symbol'               => $currency_symbol,
                'vat_included'                  => $vat_included,
                'sector_of_activity'            => $sector_of_activity,
                'languages'                     => $languages,
                'mobile_default_language'       => $mobile_default_language,
                'domain'                        => $domain,
                'geo_point_latitude'            => $geo_point_latitude,
                'geo_point_longitude'           => $geo_point_longitude,
                'geo_area'                      => $geo_area,
                'campaign_base_price_promotion' => $campaign_base_price_promotion,
                'campaign_base_price_coupon'    => $campaign_base_price_coupon,
                'campaign_base_price_news'      => $campaign_base_price_news,
                'floors'                        => $floors,
                'free_wifi_status'              => $free_wifi_status,
                'ipcountry'                     => $ipcountry,
                'ipcity'                        => $ipcity,
                'disable_ads'                   => $disable_ads,
                'disable_ymal'                  => $disable_ymal,
            ];

            $validation_error = [
                'name'                          => 'required|orbit.exists.mall_name',
                'email'                         => 'required|email|orbit.exists.email',
                'password'                      => 'required|min:6',
                'address_line1'                 => 'required',
                'city'                          => 'required',
                'country'                       => 'required|orbit.empty.country',
                'phone'                         => 'required',
                'url'                           => 'orbit.formaterror.url.web',
                'contact_person_firstname'      => 'required',
                'contact_person_lastname'       => 'required',
                'contact_person_email'          => 'email',
                'status'                        => 'required|orbit.empty.mall_status',
                'parent_id'                     => 'orbit.empty.mallgroup',
                'start_date_activity'           => 'date_format:Y-m-d H:i:s',
                'end_date_activity'             => 'date_format:Y-m-d H:i:s',
                'timezone'                      => 'required|timezone|orbit.exists.timezone',
                'currency'                      => 'required|size:3',
                'currency_symbol'               => 'required',
                'vat_included'                  => 'required|in:yes,no',
                'sector_of_activity'            => 'required',
                'languages'                     => 'required|array',
                'mobile_default_language'       => 'required|size:2|orbit.formaterror.language',
                'domain'                        => 'required|orbit.exists.domain',
                'geo_point_latitude'            => 'required|orbit.formaterror.geo_latitude',
                'geo_point_longitude'           => 'required|orbit.formaterror.geo_longitude',
                'geo_area'                      => 'required|orbit.formaterror.geo_area',
                'campaign_base_price_promotion' => 'required',
                'campaign_base_price_coupon'    => 'required',
                'campaign_base_price_news'      => 'required',
                'floors'                        => 'required|array',
                'free_wifi_status'              => 'in:active,inactive',
                'ipcountry'                     => 'required',
                'ipcity'                        => 'required|array',
                'disable_ads'                   => 'in:n,y',
                'disable_ymal'                  => 'in:n,y',
            ];

            $validation_error_message = [
                'name.required'                     => 'Mall name is required',
                'orbit.exists.mall_name'            => 'Mall name already exists',
                'email.required'                    => 'The email address is required',
                'address_line1.required'            => 'The address is required',
                'phone.required'                    => 'The mall phone number is required',
                'contact_person_firstname.required' => 'The first name is required',
                'contact_person_lastname.required'  => 'The last name is required',
            ];

            // add validation image
            if (! empty($logo_validation)) {
                $validation_data += $logo_validation['data'];
                $validation_error += $logo_validation['error'];
                $validation_error_message += $logo_validation['error_message'];
            }

            if (! empty($maps_validation)) {
                $validation_data += $maps_validation['data'];
                $validation_error += $maps_validation['error'];
                $validation_error_message += $maps_validation['error_message'];
            }

            // handle empty string
            if ($is_subscribed !== 'Y') {
                $is_subscribed = 'N';

                unset($validation_data['phone']);
                unset($validation_data['contact_person_firstname']);
                unset($validation_data['contact_person_lastname']);

                unset($validation_error['phone']);
                unset($validation_error['contact_person_firstname']);
                unset($validation_error['contact_person_lastname']);
                $validation_error['domain'] = 'orbit.exists.domain';
                $validation_error['geo_area'] = 'orbit.formaterror.geo_area';

                unset($validation_error_message['phone.required']);
                unset($validation_error_message['contact_person_firstname.required']);
                unset($validation_error_message['contact_person_lastname.required']);
            }

            $validator = Validator::make($validation_data, $validation_error, $validation_error_message);

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
            $newuser->user_firstname = $mall_name;
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
            $countryObject = App::make('orbit.empty.country');
            if (is_object($countryObject)) {
                $countryName = $countryObject->name;
            }

            $timezone = $this->valid_timezone;

            $newmall = new Mall();
            $newmall->user_id = $newuser->user_id;
            $newmall->timezone_id = $timezone->timezone_id;
            // $newmall->omid = '';
            $newmall->email = $email;
            $newmall->name = $mall_name;
            $newmall->description = $description;
            $newmall->address_line1 = $address_line1;
            $newmall->address_line2 = $address_line2;
            $newmall->address_line3 = $address_line3;
            $newmall->postal_code = $postal_code;
            $newmall->city_id = $city_id;
            $newmall->city = $city;
            $newmall->province = $province;
            $newmall->country_id = $country;
            $newmall->country = $countryName;
            $newmall->phone = $phone;
            $newmall->fax = $fax;
            $newmall->start_date_activity = $start_date_activity;
            $newmall->end_date_activity = $end_date_activity;
            $newmall->status = $status;
            // $newmall->logo = $logo;
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
            $newmall->operating_hours = $operating_hours;
            $newmall->object_type = $object_type;
            if (! empty($parent_id)) {
                $newmall->parent_id = $parent_id;
            }
            $newmall->is_mall = 'yes';
            $newmall->is_subscribed = $is_subscribed;
            $newmall->url = $url;
            if (! empty($domain)) {
                $newmall->ci_domain = $domain . Config::get('orbit.shop.ci_domain');
            }
            $newmall->masterbox_number = $masterbox_number;
            $newmall->slavebox_number = $slavebox_number;
            if (in_array($mobile_default_language, $languages)) {
                $newmall->mobile_default_language = $mobile_default_language;
            } else {
                OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.empty.mobile_default_lang'));
            }
            $newmall->pos_language = $pos_language;
            $newmall->disable_ads = $disable_ads;
            $newmall->disable_ymal = $disable_ymal;

            $newmall->video_id_1 = $video_id_1;
            $newmall->video_id_2 = $video_id_2;
            $newmall->video_id_3 = $video_id_3;
            $newmall->video_id_4 = $video_id_4;
            $newmall->video_id_5 = $video_id_5;
            $newmall->video_id_6 = $video_id_6;
            $newmall->other_photo_section_title = $other_photo_section_title;

            $newmall->mall_google_indoor_map = $mall_google_indoor_map;
            $newmall->mall_google_indoor_streetview = $mall_google_indoor_streetview;

            $newmall->modified_by = $this->api->user->user_id;

            Event::fire('orbit.mall.postnewmall.before.save', array($this, $newmall));

            $newmall->save();

            // Insert to mall_countries
            $countryName = Country::where('country_id', $country)->first();
            $existkMallCountry = MallCountry::where('country_id', $country)->first();
            if (empty($existkMallCountry)) {
              $new_mall_country = new MallCountry();
              $new_mall_country->country_id = $country;
              $new_mall_country->country = $countryName->name;
              $new_mall_country->save();
            }

            // Insert to mall_cities
            $checkMallCity = MallCity::where('city', $city)->first();
            if (empty($checkMallCity)) {
              $new_mall_city = new MallCity();
              $new_mall_city->city = $city;
              $new_mall_city->country_id = $country;
              $new_mall_city->save();
            }

            // Insert vendor_gtm_country
            $existVendorGtmCountry = VendorGTMCountry::where('gtm_country', $countryName->name)->first();
            if (empty($existVendorGtmCountry)) {
                $new_vendor_gtm_country = new VendorGTMCountry();
                $new_vendor_gtm_country->vendor_country = $ipcountry;
                $new_vendor_gtm_country->gtm_country =$countryName->name;
                $new_vendor_gtm_country->save();
            }

            // Insert vendor_gtm_city
            $vendor_ip_database = Config::get('orbit.vendor_ip_database.default', 'dbip');
            $checkVendorGtmCity = VendorGTMCity::where('gtm_city', $city)->where('vendor_type', $vendor_ip_database)->first();
            if (! empty($checkVendorGtmCity)) {
              // Delete first if exist gtm_city
              $deleteVendorGtmCity = VendorGTMCity::where('gtm_city', $city)->where('vendor_type', $vendor_ip_database)->delete();
            }

            foreach ($ipcity as $vendorCity) {
              $new_vendor_gtm_city = new VendorGTMCity();
              $new_vendor_gtm_city->vendor_type = $vendor_ip_database;
              $new_vendor_gtm_city->vendor_city = $vendorCity;
              $new_vendor_gtm_city->gtm_city = $city;
              $new_vendor_gtm_city->country_id = $country;
              $new_vendor_gtm_city->vendor_country = $ipcountry;
              $new_vendor_gtm_city->save();
            }

            // languages
            // @author irianto <irianto@dominopos.com>
            if (count($languages) > 0) {
                foreach ($languages as $language_name) {
                    $validator = Validator::make(
                        array(
                            'language'             => $language_name
                        ),
                        array(
                            'language'             => 'required|size:2|orbit.formaterror.language'
                        )
                    );

                    // Run the validation
                    if ($validator->fails()) {
                        $errorMessage = $validator->messages()->first();
                        OrbitShopAPI::throwInvalidArgument($errorMessage);
                    }

                    $merchant_language = new MerchantLanguage();
                    $merchant_language->merchant_id = $newmall->merchant_id;
                    $merchant_language->language_id = Language::where('name', '=', $language_name)->first()->language_id;
                    $merchant_language->save();
                }
            }

            $languages_by_name = [];
            foreach ($newmall->languages as $language) {
                $name_lang = $language->language->name;
                $languages_by_name[$name_lang] = $language;
            }

            // widgets
            // @author irianto <irianto@dominopos.com>
            $new_widget = new stdClass();
            foreach ($widgets as $data_widget) {
                $new_widget = new Widget();
                $new_widget->widget_type = $data_widget['type'];
                $new_widget->widget_object_id = $data_widget['object_id'];
                $new_widget->widget_slogan = $data_widget['slogan']['default'];
                $new_widget->widget_order = $data_widget['order'];
                $new_widget->merchant_id = $newmall->merchant_id;
                $new_widget->animation = $data_widget['animation'];
                $new_widget->status = $data_widget['status'];
                if ($data_widget['type'] === 'free_wifi') {
                    if ($data_widget['status'] !== $free_wifi_status) {
                        $new_widget->status = $free_wifi_status;
                    }
                }
                $new_widget->save();

                // Sync also to the widget_retailer table
                $new_widget->malls()->sync( [$newmall->merchant_id] );

                // Insert the translation for the slogan
                $new_widget_trans = new stdClass();
                $slogan = $data_widget['slogan'];
                if (count($languages) > 0) {
                    foreach ($languages as $lang) {
                        if (isset($slogan[$lang])) {
                            // Get the Language ID
                            // The content for this particular language is available
                            $new_widget_trans = new WidgetTranslation();
                            $new_widget_trans->widget_id = $new_widget->widget_id;
                            $new_widget_trans->merchant_language_id = $languages_by_name[$lang]->language_id;
                            $new_widget_trans->widget_slogan = $slogan[$lang];
                            $new_widget_trans->status = 'active';
                            $new_widget_trans->save();
                        }
                    }
                }
            }
            $newmall->free_wifi_status = $new_widget->status;

            // floor
            // @author irianto <irianto@dominopos.com>
            if (count($floors) > 0) {
                foreach ($floors as $floor_json) {
                    $floor = @json_decode($floor_json);
                    if (json_last_error() != JSON_ERROR_NONE) {
                        OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.jsonerror.format'));
                    }

                    // check exist floor name
                    $exist_floor = Object::excludeDeleted()
                                        ->where('merchant_id', $newmall->merchant_id)
                                        ->where('object_name', $floor->name)
                                        ->where('object_type', 'floor')
                                        ->first();

                    if (count($exist_floor) > 0)
                    {
                        OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.exists.floor'));
                    }

                    $newfloor = new Object();
                    $newfloor->merchant_id = $newmall->merchant_id;
                    $newfloor->object_name = $floor->name;
                    $newfloor->object_type = 'floor';
                    $newfloor->object_order = $floor->order;
                    $newfloor->status = 'active';
                    $newfloor->save();
                }
            }

            // settings
            // @author irianto <irianto@dominopos.com>
            $setting_items = [
                'enable_coupon'                                        => 'true',
                'enable_coupon_widget'                                 => 'true',
                'enable_lucky_draw'                                    => 'true',
                'enable_lucky_draw_widget'                             => 'true',
                'enable_free_wifi'                                     => 'true',
                'enable_free_wifi_widget'                              => 'true',
                'enable_membership_card'                               => 'false',
                'landing_page'                                         => 'widget',
                'agreement_accepted'                                   => 'false',
                'agreement_acceptor_first_name'                        => '',
                'agreement_acceptor_last_name'                         => '',
                'dom:' . $domain . Config::get('orbit.shop.ci_domain') => $newmall->merchant_id
            ];

            foreach ($setting_items as $setting_name => $setting_value) {
                $settings = new Setting();
                $settings->setting_name = $setting_name;
                $settings->setting_value = $setting_value;
                $settings->object_id = $newmall->merchant_id;
                $settings->object_type = 'merchant';
                if (strpos($setting_name, 'dom') !== false) {
                    $settings->object_id = NULL;
                    $settings->object_type = NULL;
                }
                $settings->status = 'active';
                $settings->modified_by = $user->user_id;

                $settings->save();
            }

            // age ranges
            // @author irianto <irianto@dominopos.com>
            foreach ($age_ranges as $age_range) {
                $age = new AgeRange();
                $age->merchant_id = $newmall->merchant_id;
                $age->range_name = $age_range['range_name'];
                $age->min_value = $age_range['min_value'];
                $age->max_value = $age_range['max_value'];
                $age->status = $age_range['status'];
                $age->save();
            }

            // campaign base prices
            // @author irianto <irianto@dominopos.com>
            $campaign_base_prices = [];
            $campaign_base_prices[] = ['price' => $campaign_base_price_promotion, 'campaign_type' => 'promotion'];
            $campaign_base_prices[] = ['price' => $campaign_base_price_coupon, 'campaign_type' => 'coupon'];
            $campaign_base_prices[] = ['price' => $campaign_base_price_news, 'campaign_type' => 'news'];

            foreach ($campaign_base_prices as $campaign_base_price) {
                $price = new CampaignBasePrice();
                $price->merchant_id = $newmall->merchant_id;
                $price->price = $campaign_base_price['price'];
                $price->campaign_type = $campaign_base_price['campaign_type'];
                $price->status = 'active';
                $price->save();
            }

            // save to spending rule, the default is N
            // @author kadek <kadek@dominopos.com>
            $newSpendingRules = new SpendingRule();
            $newSpendingRules->object_id = $newmall->merchant_id;
            $newSpendingRules->with_spending = 'N';
            $newSpendingRules->save();

            //save to object_partner
            if (! empty($partner_ids)) {
              foreach ($partner_ids as $partner_id) {
                if ($partner_id != "") {
                  $objectPartner = new ObjectPartner();
                  $objectPartner->object_id = $newmall->merchant_id;
                  $objectPartner->object_type = 'mall';
                  $objectPartner->partner_id = $partner_id;
                  $objectPartner->save();
                }
              }
            }

            if (OrbitInput::post('facebook_uri')) {
                $this->saveSocmedUri('facebook', $newmall->merchant_id, OrbitInput::post('facebook_uri'));

                // For response
                $newmall->facebook_uri = OrbitInput::post('facebook_uri');
            }

            // save geo location mall
            // @author irianto <irianto@dominopos.com>

            $fence = new MerchantGeofence();
            $latitude = (double)$geo_point_latitude;
            $longitude = (double)$geo_point_longitude;
            $area = preg_replace('/[^0-9\s,\-\.]/', '',  $geo_area);

            $fence->position = DB::raw("POINT($latitude, $longitude)");
            $fence->area = DB::raw("GEOMFROMTEXT(\"POLYGON(({$area}))\")");
            $fence->merchant_id = $newmall->merchant_id;

            $fence->save();

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

    public function getMallDetail()
    {
        try {
            $httpCode = 200;
            $mall = null;
            // Require authentication
            $this->checkAuth();
            // Try to check access control list, does this mall allowed to
            // perform this action
            $user = $this->api->user;

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = ['super admin', 'mall admin', 'mall owner', 'merchant database admin'];
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            $merchantId = OrbitInput::get('merchant_id');

            $validator = Validator::make(
                array(
                    'merchant_id' => $merchantId,
                ),
                array(
                    'merchant_id' => 'required',
                )
            );

            $prefix = DB::getTablePrefix();
            $subdomain = Config::get('orbit.shop.ci_domain');

            // Get Facebook social media ID
            $facebookSocmedId = SocialMedia::whereSocialMediaCode('facebook')->first()->social_media_id;

            $mall = Mall::excludeDeleted('merchants')
                ->select(
                    'merchants.*',
                    'countries.code as country_code',
                    DB::raw("LEFT({$prefix}merchants.ci_domain, instr({$prefix}merchants.ci_domain, '.') - 1) as subdomain"),
                    DB::raw('count(tenant.merchant_id) AS total_tenant'),
                    DB::raw('mall_group.name AS mall_group_name'),
                    'merchant_social_media.social_media_uri as facebook_uri',
                    // latitude
                    DB::raw("SUBSTR(AsText({$prefix}merchant_geofences.position), LOCATE('(', AsText({$prefix}merchant_geofences.position)) + 1, LOCATE(' ', AsText({$prefix}merchant_geofences.position)) - 1 - LOCATE('(', AsText({$prefix}merchant_geofences.position))) as geo_point_latitude"),
                    // longitude
                    DB::raw("SUBSTR(AsText({$prefix}merchant_geofences.position), LOCATE(' ', AsText({$prefix}merchant_geofences.position)) + 1, LOCATE(')', AsText({$prefix}merchant_geofences.position)) - 1 - LOCATE(' ', AsText({$prefix}merchant_geofences.position))) as geo_point_longitude"),
                    // area
                    DB::raw("SUBSTR(AsText({$prefix}merchant_geofences.area), LOCATE('((', AsText({$prefix}merchant_geofences.area)) + 2, LOCATE('))', AsText({$prefix}merchant_geofences.area)) - 2 - LOCATE('((', AsText({$prefix}merchant_geofences.area))) as geo_area"),
                    DB::raw("CASE WHEN
                        (
                            select sum(total_campaign_has_translation) as total_translation
                            from
                            (
                                select CASE WHEN m.object_type = 'tenant' THEN m.parent_id ELSE m.merchant_id END as mall_id,
                                        nt.merchant_language_id, count(n.news_id) as total_campaign_has_translation
                                from {$prefix}news_translations nt
                                join {$prefix}news n
                                    on n.news_id = nt.news_id
                                join {$prefix}news_merchant nm
                                    on nm.news_id = n.news_id
                                join {$prefix}merchants m
                                    on m.merchant_id = nm.merchant_id
                                where m.object_type in ('mall', 'tenant')
                                    and n.status != 'deleted'
                                group by mall_id, nt.merchant_language_id

                                union

                                select CASE WHEN m.object_type = 'tenant' THEN m.parent_id ELSE m.merchant_id END as mall_id,
                                        ct.merchant_language_id, count(c.promotion_id) as total_campaign_has_translation
                                from {$prefix}coupon_translations ct
                                join {$prefix}promotions c
                                    on c.promotion_id = ct.promotion_id
                                join {$prefix}promotion_retailer pr
                                    on pr.promotion_id = c.promotion_id
                                join {$prefix}merchants m
                                    on m.merchant_id = pr.retailer_id
                                where m.object_type in ('mall', 'tenant')
                                    and c.status != 'deleted'
                                group by mall_id, ct.merchant_language_id
                            ) as campaign
                            where campaign.mall_id = {$prefix}merchants.merchant_id
                                and campaign.merchant_language_id = (
                                    select lang.language_id
                                    from {$prefix}languages lang
                                    where lang.name = {$prefix}merchants.mobile_default_language
                                )
                        )
                        > 0 THEN 'true' ELSE 'false' END as disable_mobile_default_language")
                )
                ->with(
                    'parent',
                    'mediaLogo',
                    'mediaMapOrig',
                    'mallFloors',
                    'timezone',
                    'mallCampaignBasePrices',
                    'mallLanguages',
                    'mallCategories',
                    'mallCategoryTranslations',
                    'widget_free_wifi',
                    'partners'
                )
                ->leftJoin('merchants AS tenant', function($join) {
                        $join->on(DB::raw('tenant.parent_id'), '=', 'merchants.merchant_id')
                            ->where(DB::raw('tenant.status'), '!=', 'deleted')
                            ->where(DB::raw('tenant.object_type'), '=', 'tenant');
                    })
                // A left join to get tenants' Facebook URIs
                ->leftJoin('merchant_social_media', function ($join) use ($facebookSocmedId) {
                        $join->on('merchants.merchant_id', '=', 'merchant_social_media.merchant_id')
                            ->where('social_media_id', '=', $facebookSocmedId);
                    })
                ->leftJoin('merchants AS mall_group', DB::raw('mall_group.merchant_id'), '=', 'merchants.parent_id')
                ->leftJoin('merchant_geofences', 'merchant_geofences.merchant_id', '=', 'merchants.merchant_id')
                ->leftJoin('countries', 'merchants.country_id', '=', 'countries.country_id')
                ->where('merchants.merchant_id', $merchantId)
                ->groupBy('merchants.merchant_id')
                ->firstOrFail();

            $data = $mall;

            $this->response->data = $data;

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
        }
        $output = $this->render($httpCode);

        return $output;
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

/*
            if (! ACL::create($user)->isAllowed('view_mall')) {
                Event::fire('orbit.mall.getsearchmall.authz.notallowed', array($this, $user));
                $viewUserLang = Lang::get('validation.orbit.actionlist.view_mall');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewUserLang));
                ACL::throwAccessForbidden($message);
            }
*/
            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = ['super admin', 'mall admin', 'mall owner', 'merchant database admin'];
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
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
                    'sort_by' => 'in:merchant_orid,registered_date,merchant_name,merchant_email,merchant_userid,merchant_description,merchantid,merchant_address1,merchant_address2,merchant_address3,merchant_cityid,merchant_city,merchant_countryid,merchant_country,merchant_phone,merchant_fax,merchant_status,merchant_currency,start_date_activity,end_date_activity,total_retailer,mallgroup',
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
            $subdomain = Config::get('orbit.shop.ci_domain');

            $malls = Mall::excludeDeleted('merchants')
                ->select(
                    'merchants.merchant_id',
                    'merchants.name',
                    'merchants.city',
                    'merchants.country',
                    DB::raw('mall_group.name AS mall_group_name'),
                    'merchants.is_subscribed',
                    'merchants.status'
                )
                ->leftJoin('merchants AS mall_group', DB::raw('mall_group.merchant_id'), '=', 'merchants.parent_id')
                ->join('countries', 'countries.country_id', '=', 'merchants.country_id')
                ->groupBy('merchants.merchant_id');

            // Filter mall by Ids
            OrbitInput::get('merchant_id', function ($merchantIds) use ($malls) {
                $malls->whereIn('merchants.merchant_id', $merchantIds);
            });

            // Filter mall by name
            OrbitInput::get('name', function ($name) use ($malls) {
                $malls->whereIn('merchants.name', $name);
            });

            // Filter mall by name pattern
            OrbitInput::get('name_like', function ($name) use ($malls) {
                $malls->where('merchants.name', 'like', "%$name%");
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
                $malls->where('merchants.country_id', $countryId);
            });

            // Filter mall by country
            OrbitInput::get('country', function ($country) use ($malls) {
                $malls->whereIn('merchants.country', $country);
            });

            // Filter mall by country pattern
            OrbitInput::get('country_like', function ($country) use ($malls) {
                $malls->where('merchants.country', 'like', "%$country%");
            });

            // Filter mall by status
            OrbitInput::get('status', function ($status) use ($malls) {
                $malls->whereIn('merchants.status', $status);
            });

            // Filter mall by status
            OrbitInput::get('is_subscribed', function ($is_subscribed) use ($malls) {
                $malls->whereIn('merchants.is_subscribed', $is_subscribed);
            });

            // Filter mall by location (city country)
            OrbitInput::get('location', function($data) use ($malls, $prefix) {
                $check = strpos($data, ",");

                if(! empty($check)) {
                    $loc = explode(",", $data);
                    $city = $loc[0];
                    $country = substr($loc[1], 1);
                    $malls->where('merchants.city', 'like', "%$city%");
                    $malls->where('merchants.country', 'like', "%$country%");
                } else {
                    $malls->where(DB::raw("CONCAT(COALESCE({$prefix}merchants.city, ''), ' ', COALESCE({$prefix}merchants.country, ''))"), 'like', "%$data%");
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
                    'merchant_name'        => 'merchants.name',
                    'merchantid'           => 'merchants.merchant_id',
                    'merchant_cityid'      => 'merchants.city_id',
                    'merchant_city'        => 'merchants.city',
                    'merchant_countryid'   => 'merchants.country_id',
                    'merchant_country'     => 'merchants.country',
                    'merchant_status'      => 'merchants.status',
                    'mallgroup'            => 'mall_group_name',
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
     * @author Irianto <irianto@dominopos.com>
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
     * @param string     `operating_hours`          (optional) - Mall operating hours
     * @param string     `geo_point_latitude`       (optional) - Point of latitude
     * @param string     `geo_point_longitude`      (optional) - Point of longitude
     * @param string     `geo_area`                 (optional) - Geo fence of the mall
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

            $merchant_id = OrbitInput::post('merchant_id');
            $name = trim(OrbitInput::post('name'));
            $email = OrbitInput::post('email');
            $password = OrbitInput::post('password');
            $country = OrbitInput::post('country');
            $city = OrbitInput::post('city');
            $url = OrbitInput::post('url');
            $contact_person_email = OrbitInput::post('contact_person_email');
            $status = OrbitInput::post('status');
            $parent_id = OrbitInput::post('parent_id');
            $ticket_header = OrbitInput::post('ticket_header');
            $ticket_footer = OrbitInput::post('ticket_footer');
            $start_date_activity = OrbitInput::post('start_date_activity');
            $end_date_activity = OrbitInput::post('end_date_activity');
            $mobile_default_language = OrbitInput::post('mobile_default_language');
            $languages = OrbitInput::post('languages');
            $floors = OrbitInput::post('floors');
            $domain = OrbitInput::post('domain');
            $free_wifi_status = OrbitInput::post('free_wifi_status');
            $geo_point_latitude = OrbitInput::post('geo_point_latitude');
            $geo_point_longitude = OrbitInput::post('geo_point_longitude');
            $geo_area = OrbitInput::post('geo_area');
            $description = OrbitInput::post('description');
            $disable_ads = OrbitInput::post('disable_ads', 'n');
            $disable_ymal = OrbitInput::post('disable_ymal', 'n');
            $logo = OrbitInput::files('logo');
            $maps = OrbitInput::files('maps');
            $ipcountry = OrbitInput::post('ipcountry');
            $ipcity = OrbitInput::post('ipcity', []);

            // generate array validation image
            $logo_validation = $this->generate_validation_image('mall_logo', $logo, 'orbit.upload.mall.logo');
            $maps_validation = $this->generate_validation_image('mall_map', $maps, 'orbit.upload.mall.map', 3);

            $validation_data = [
                'merchant_id'             => $merchant_id,
                'name'                    => $name,
                'email'                   => $email,
                'password'                => $password,
                'country'                 => $country,
                'url'                     => $url,
                'contact_person_email'    => $contact_person_email,
                'status'                  => $status,
                'parent_id'               => $parent_id,
                'ticket_header'           => $ticket_header,
                'ticket_footer'           => $ticket_footer,
                'start_date_activity'     => $start_date_activity,
                'end_date_activity'       => $end_date_activity,
                'languages'               => $languages,
                'domain'                  => $domain,
                'mobile_default_language' => $mobile_default_language,
                'floors'                  => $floors,
                'free_wifi_status'        => $free_wifi_status,
                'geo_point_latitude'      => $geo_point_latitude,
                'geo_point_longitude'     => $geo_point_longitude,
                'geo_area'                => $geo_area,
                'ipcountry'               => $ipcountry,
                'disable_ads'             => $disable_ads,
                'disable_ymal'            => $disable_ymal,
                'ipcity'                  => $ipcity
            ];
            $validation_error = [
                'merchant_id'             => 'required|orbit.empty.mall',
                'name'                    => 'mall_name_exists_but_me',
                'email'                   => 'email|email_exists_but_me',
                'password'                => 'min:6',
                'country'                 => 'orbit.empty.country',
                'url'                     => 'orbit.formaterror.url.web',
                'contact_person_email'    => 'email',
                'status'                  => 'orbit.empty.mall_status|orbit_check_link_mallgroup|orbit_check_link_campaign|orbit_check_tenant_mall',
                'parent_id'               => 'orbit.empty.mallgroup',
                'ticket_header'           => 'ticket_header_max_length',
                'ticket_footer'           => 'ticket_footer_max_length',
                'start_date_activity'     => 'date_format:Y-m-d H:i:s',
                'end_date_activity'       => 'date_format:Y-m-d H:i:s',
                'vat_included'            => 'in:yes,no',
                'languages'               => 'array',
                'domain'                  => 'domain_exist_but_not_me:' . $merchant_id,
                'mobile_default_language' => 'size:2|orbit.formaterror.language',
                'floors'                  => 'array',
                'free_wifi_status'        => 'in:active,inactive',
                'geo_point_latitude'      => 'orbit.formaterror.geo_latitude',
                'geo_point_longitude'     => 'orbit.formaterror.geo_longitude',
                'geo_area'                => 'orbit.formaterror.geo_area',
                'ipcountry'               => 'required',
                'disable_ads'             => 'in:n,y',
                'disable_ymal'            => 'in:n,y',
                'ipcity'                  => 'required|array',
            ];
            $validation_error_message = [
               'domain_exist_but_not_me'    => Lang::get('validation.orbit.exists.domain'),
               'mall_name_exists_but_me'    => 'Mall name already exists',
               'email_exists_but_me'        => Lang::get('validation.orbit.exists.email'),
               'contact_person_email.email' => 'Email must be a valid email address',
               'orbit.empty.mall_status'    => 'Mall status you specified is not found',
               'orbit_check_link_mallgroup' => 'Mall is not linked to active mall group',
               'orbit_check_link_campaign'  => 'Mall is linked to active campaign(s)',
               'ticket_header_max_length'   => Lang::get('validation.orbit.formaterror.merchant.ticket_header.max_length'),
               'ticket_footer_max_length'   => Lang::get('validation.orbit.formaterror.merchant.ticket_footer.max_length'),
               'orbit_check_tenant_mall'    => 'Mall can not be deactivated, because it has active tenant'
            ];

            // add validation image
            if (! empty($logo_validation)) {
                $validation_data += $logo_validation['data'];
                $validation_error += $logo_validation['error'];
                $validation_error_message += $logo_validation['error_message'];
            }

            if (! empty($maps_validation)) {
                $validation_data += $maps_validation['data'];
                $validation_error += $maps_validation['error'];
                $validation_error_message += $maps_validation['error_message'];
            }

            $validator = Validator::make(
                $validation_data,
                $validation_error,
                $validation_error_message
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

            $updatedUser = User::excludeDeleted()
                            ->where('user_id', '=', $updatedmall->user_id)
                            ->first();

            OrbitInput::post('password', function($password) use ($updatedUser) {
                if (! empty(trim($password))) {
                    $updatedUser->user_password = Hash::make($password);
                }
            });

            OrbitInput::post('name', function($name) use ($updatedUser) {
                if (! empty(trim($name))) {
                    $updatedUser->user_firstname = $name;
                }
            });

            $updatedUser->modified_by = $this->api->user->user_id;

            Event::fire('orbit.mallgroup.postupdateuser.before.save', array($this, $updatedUser));

            $updatedUser->save();

            OrbitInput::post('is_subscribed', function($is_subscribed) use ($updatedmall) {
                $updatedmall->is_subscribed = $is_subscribed;
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

            OrbitInput::post('operating_hours', function($operating_hours) use ($updatedmall) {
                $updatedmall->operating_hours = $operating_hours;
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
                if ($postal_code == '') {
                  $updatedmall->postal_code = null;
                }
            });

            OrbitInput::post('city_id', function($city_id) use ($updatedmall) {
                $updatedmall->city_id = $city_id;
            });

            OrbitInput::post('city', function($city) use ($updatedmall) {
                $updatedmall->city = $city;
            });

            OrbitInput::post('province', function($province) use ($updatedmall) {
                $updatedmall->province = $province;
            });

            OrbitInput::post('country', function($country) use ($updatedmall) {
                $countryName = '';
                $countryObject = Country::find($country);
                if (is_object($countryObject)) {
                    $countryName = $countryObject->name;
                }

                $updatedmall->country_id = $country;
                $updatedmall->country = $countryName;
            });

            OrbitInput::post('phone', function($phone) use ($updatedmall) {
                $updatedmall->phone = $phone;
            });

            OrbitInput::post('fax', function($fax) use ($updatedmall) {
                $updatedmall->fax = $fax;
            });

            OrbitInput::post('start_date_activity', function($start_date_activity) use ($updatedmall) {
                if (empty(trim($start_date_activity))) {
                    $updatedmall->start_date_activity = NULL;
                } else {
                    $updatedmall->start_date_activity = $start_date_activity;
                }
            });

            OrbitInput::post('end_date_activity', function($end_date_activity) use ($updatedmall) {
                if (empty(trim($end_date_activity))) {
                    $updatedmall->end_date_activity = NULL;
                } else {
                    $updatedmall->end_date_activity = $end_date_activity;
                }
            });

            OrbitInput::post('status', function($status) use ($updatedmall) {
                $updatedmall->status = $status;
            });

            OrbitInput::post('logo', function($logo) use ($updatedmall) {
                $updatedmall->logo = $logo;
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

            OrbitInput::post('parent_id', function($parent_id) use ($updatedmall) {
                if (empty(trim($parent_id))) {
                    $updatedmall->parent_id = NULL;
                } else {
                    $updatedmall->parent_id = $parent_id;
                }
            });

            OrbitInput::post('url', function($url) use ($updatedmall) {
                $updatedmall->url = $url;
            });

            OrbitInput::post('domain', function($domain) use ($updatedmall) {
                $updatedmall->ci_domain = $domain . Config::get('orbit.shop.ci_domain');

                $setting_domain = Setting::where('setting_value', $updatedmall->merchant_id)
                                        ->where('setting_name', 'like', '%dom%')
                                        ->first();
                if (count($setting_domain) > 0) {
                    $setting_domain->setting_name = 'dom:' . $domain . Config::get('orbit.shop.ci_domain');
                    $setting_domain->save();
                }
            });

            OrbitInput::post('masterbox_number', function($masterbox_number) use ($updatedmall) {
                $updatedmall->masterbox_number = $masterbox_number;
            });

            OrbitInput::post('slavebox_number', function($slavebox_number) use ($updatedmall) {
                $updatedmall->slavebox_number = $slavebox_number;
            });

            OrbitInput::post('mobile_default_language', function($mobile_default_language) use ($updatedmall, $languages) {
                $old_mobile_default_language = $updatedmall->mobile_default_language;
                if ($old_mobile_default_language !== $mobile_default_language) {
                    $check_lang = Language::excludeDeleted()
                                    ->where('name', $old_mobile_default_language)
                                    ->first();

                    if (! empty($check_lang)) {
                        // news translation
                        $news_translations = NewsTranslation::excludeDeleted('news_translations')
                                                ->excludeDeleted('merchant_languages')
                                                ->join('news', 'news.news_id', '=', 'news_translations.news_id')
                                                ->join('news_merchant', 'news_merchant.news_id', '=', 'news.news_id')
                                                ->join('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                                                ->join('merchant_languages', function($q) {
                                                    $q->on('merchant_languages.language_id', '=', 'news_translations.merchant_language_id')
                                                        ->on('merchant_languages.merchant_id', '=', 'merchants.parent_id');
                                                })
                                                ->where('merchants.object_type', 'tenant')
                                                ->where('merchants.parent_id', $updatedmall->merchant_id)
                                                ->where('news_translations.merchant_language_id', '=', $check_lang->language_id)
                                                ->where('news.object_type', '=', 'news')
                                                ->first();
                        if (count($news_translations) > 0) {
                            $errorMessage = Lang::get('validation.orbit.exists.link_mobile_default_lang');
                            OrbitShopAPI::throwInvalidArgument($errorMessage);
                        }

                        // promotion translation
                        $promotion_translations = NewsTranslation::excludeDeleted('news_translations')
                                                ->excludeDeleted('merchant_languages')
                                                ->join('news', 'news.news_id', '=', 'news_translations.news_id')
                                                ->join('news_merchant', 'news_merchant.news_id', '=', 'news.news_id')
                                                ->join('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                                                ->join('merchant_languages', function($q) {
                                                    $q->on('merchant_languages.language_id', '=', 'news_translations.merchant_language_id')
                                                        ->on('merchant_languages.merchant_id', '=', 'merchants.parent_id');
                                                })
                                                ->where('merchants.object_type', 'tenant')
                                                ->where('merchants.parent_id', $updatedmall->merchant_id)
                                                ->where('news_translations.merchant_language_id', '=', $check_lang->language_id)
                                                ->where('news.object_type', '=', 'promotion')
                                                ->first();
                        if (count($promotion_translations) > 0) {
                            $errorMessage = Lang::get('validation.orbit.exists.link_mobile_default_lang');
                            OrbitShopAPI::throwInvalidArgument($errorMessage);
                        }

                        // coupon translation
                        $coupon_translations = CouponTranslation::excludeDeleted('coupon_translations')
                                                ->excludeDeleted('merchant_languages')
                                                ->join('promotion_retailer', 'promotion_retailer.promotion_id', '=', 'coupon_translations.promotion_id')
                                                ->join('merchants', 'merchants.merchant_id', '=', 'promotion_retailer.retailer_id')
                                                ->join('merchant_languages', function ($q) {
                                                    $q->on('merchant_languages.language_id', '=', 'coupon_translations.merchant_language_id')
                                                        ->on('merchant_languages.merchant_id', '=', 'merchants.parent_id');
                                                })
                                                ->where('merchants.object_type', 'tenant')
                                                ->where('merchants.parent_id', $updatedmall->merchant_id)
                                                ->where('coupon_translations.merchant_language_id', '=', $check_lang->language_id)
                                                ->first();
                        if (count($coupon_translations) > 0) {
                            $errorMessage = Lang::get('validation.orbit.exists.link_mobile_default_lang');
                            OrbitShopAPI::throwInvalidArgument($errorMessage);
                        }
                    }
                }

                if (in_array($mobile_default_language, $languages)) {
                    $updatedmall->mobile_default_language = $mobile_default_language;
                } else {
                    OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.empty.mobile_default_lang'));
                }
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

            OrbitInput::post('disable_ads', function($disable_ads) use ($updatedmall) {
                $updatedmall->disable_ads = $disable_ads;
            });

            OrbitInput::post('disable_ymal', function($disable_ymal) use ($updatedmall) {
                $updatedmall->disable_ymal = $disable_ymal;
            });

            OrbitInput::post('video_id_1', function($video_id_1) use ($updatedmall) {
                $updatedmall->video_id_1 = $video_id_1;
            });

            OrbitInput::post('video_id_2', function($video_id_2) use ($updatedmall) {
                $updatedmall->video_id_2 = $video_id_2;
            });

            OrbitInput::post('video_id_3', function($video_id_3) use ($updatedmall) {
                $updatedmall->video_id_3 = $video_id_3;
            });

            OrbitInput::post('video_id_4', function($video_id_4) use ($updatedmall) {
                $updatedmall->video_id_4 = $video_id_4;
            });

            OrbitInput::post('video_id_5', function($video_id_5) use ($updatedmall) {
                $updatedmall->video_id_5 = $video_id_5;
            });

            OrbitInput::post('video_id_6', function($video_id_6) use ($updatedmall) {
                $updatedmall->video_id_6 = $video_id_6;
            });

            OrbitInput::post('other_photo_section_title', function($other_photo_section_title) use ($updatedmall) {
                $updatedmall->other_photo_section_title = $other_photo_section_title;
            });

            OrbitInput::post('mall_google_indoor_map', function($mall_google_indoor_map) use ($updatedmall) {
                $updatedmall->mall_google_indoor_map = $mall_google_indoor_map;
            });

            OrbitInput::post('mall_google_indoor_streetview', function($mall_google_indoor_streetview) use ($updatedmall) {
                $updatedmall->mall_google_indoor_streetview = $mall_google_indoor_streetview;
            });

            $updatedmall->modified_by = $this->api->user->user_id;

            Event::fire('orbit.mall.postupdatemall.before.save', array($this, $updatedmall));

            $updatedmall->setUpdatedAt($updatedmall->freshTimestamp());
            $updatedmall->save();

            OrbitInput::post('facebook_uri', function ($fb_uri) use ($updatedmall) {
                $this->saveSocmedUri('facebook', $updatedmall->merchant_id, $fb_uri);

                // For response
                $updatedmall->facebook_uri = $fb_uri;
            });

            OrbitInput::post('languages', function($languages) use ($updatedmall, $mobile_default_language) {
                // new languages
                $all_mall_languages = [];
                foreach ($languages as $language_name) {
                    $validator = Validator::make(
                        array(
                            'language'             => $language_name
                        ),
                        array(
                            'language'             => 'required|size:2|orbit.formaterror.language'
                        )
                    );

                    // Run the validation
                    if ($validator->fails()) {
                        $errorMessage = $validator->messages()->first();
                        OrbitShopAPI::throwInvalidArgument($errorMessage);
                    }

                    $language_data = $this->valid_lang;

                    // check lang
                    $merchant_languages = MerchantLanguage::excludeDeleted()
                                                        ->where('merchant_id', '=', $updatedmall->merchant_id)
                                                        ->where('language_id', '=', $language_data->language_id)
                                                        ->get();

                    if (count($merchant_languages) > 0) {
                        foreach ($merchant_languages as $merchant_language) {
                            $all_mall_languages[] = $merchant_language->merchant_language_id;
                        }
                    } else {
                        $newmerchant_language = new MerchantLanguage();
                        $newmerchant_language->merchant_id = $updatedmall->merchant_id;
                        $newmerchant_language->status = 'active';
                        $newmerchant_language->language_id = Language::where('name', '=', $language_name)->first()->language_id;
                        $newmerchant_language->save();

                        $all_mall_languages[] = $newmerchant_language->merchant_language_id;
                    }
                }

                // find lang will be delete
                $languages_will_be_delete = MerchantLanguage::excludeDeleted('merchant_languages')
                                                ->leftjoin('languages', 'languages.language_id', '=', 'merchant_languages.language_id')
                                                ->where('merchant_languages.merchant_id', '=', $updatedmall->merchant_id)
                                                ->whereNotIn('merchant_languages.merchant_language_id', $all_mall_languages)
                                                ->get();

                if (count($languages_will_be_delete) > 0) {
                    $del_lang = [];
                    foreach ($languages_will_be_delete as $check_lang) {
                        if ($check_lang->name === $mobile_default_language) {
                            OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.exists.mobile_default_lang'));
                        }

                        //colect language will be delete
                        $del_lang[] = $check_lang->merchant_language_id;
                    }
                    if (count($del_lang) > 0) {
                        // delete languages
                        $delete_languages = MerchantLanguage::excludeDeleted()
                                                        ->where('merchant_id', '=', $updatedmall->merchant_id)
                                                        ->whereIn('merchant_language_id', $del_lang)
                                                        ->update(['status' => 'deleted']);
                    }
                }
            });

            OrbitInput::post('floors', function($floors) use ($updatedmall) {
                // floor
                // @author irianto <irianto@dominopos.com>
                if (count($floors) > 0) {
                    $colect_floor = [];
                    foreach ($floors as $floor_json) {
                        $floor = @json_decode($floor_json);
                        if (json_last_error() != JSON_ERROR_NONE) {
                            OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.jsonerror.format'));
                        }

                        $floor_id = Null;
                        if (empty($floor->id)) {
                            // handle a floor object without floor id but exist
                            $exist_floor = Object::excludeDeleted()
                                                ->where('object_type', 'floor')
                                                ->where('merchant_id', $updatedmall->merchant_id)
                                                ->where('object_name', $floor->name)
                                                ->first();
                            if (count($exist_floor) > 0) {
                                $floor_id = $exist_floor->object_id;
                            }
                        } else {
                            $floor_id = $floor->id;
                        }

                        if (! is_null($floor_id) && ! empty($floor->floor_delete)) {
                            if ($floor->floor_delete === 'yes') {
                                $will_del_floor = Object::excludeDeleted()
                                                    ->where('object_id', $floor_id)
                                                    ->where('object_type', 'floor')
                                                    ->where('merchant_id', $updatedmall->merchant_id)
                                                    ->first();

                                if (count($will_del_floor) > 0) {
                                    $tenant = Tenant::excludeDeleted()
                                                ->where('floor_id', $will_del_floor->object_id)
                                                ->where('parent_id', $updatedmall->merchant_id)
                                                ->first();
                                    if (count($tenant) > 0) {
                                      $errorMessage = Lang::get('validation.orbit.exists.link_floor');
                                      OrbitShopAPI::throwInvalidArgument($errorMessage);
                                    }

                                    $delete_floor = Object::excludeDeleted()
                                                  ->where('object_type', 'floor')
                                                  ->where('merchant_id', $updatedmall->merchant_id)
                                                  ->where('object_id', $will_del_floor->object_id)
                                                  ->update(["status" => "deleted"]);
                                }
                            }
                        } else {
                            if (is_null($floor_id)) { // if floor doesn't have id that's mean is a new floor
                                // create new floor
                                $newfloor = new Object();
                                $newfloor->merchant_id = $updatedmall->merchant_id;
                                $newfloor->object_name = $floor->name;
                                $newfloor->object_type = 'floor';
                                $newfloor->object_order = $floor->order;
                                $newfloor->status = 'active';
                                $newfloor->save();
                            } else {
                                // check exists floor name but not me
                                if (in_array($floor->name, $colect_floor))
                                {
                                    OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.exists.floor'));
                                }
                                // update name and order
                                $update_floor = Object::excludeDeleted()
                                                    ->where('object_type', 'floor')
                                                    ->where('merchant_id', $updatedmall->merchant_id)
                                                    ->where('object_id', $floor_id)
                                                    ->first();

                                if (count($update_floor) > 0) {
                                    // update name and order
                                    $update_floor->object_name = $floor->name;
                                    $update_floor->object_order = $floor->order;
                                    $update_floor->save();
                                }
                            }

                            $colect_floor[] = $floor->name;
                        }
                    }
                }
            });

            OrbitInput::post('free_wifi_status', function($free_wifi_status) use ($updatedmall){
                $languages_by_name = [];
                $languages_name = [];
                foreach ($updatedmall->languages as $language) {
                    $name_lang = $language->language->name;
                    $languages_by_name[$name_lang] = $language;
                    $languages_name[] = $name_lang;
                }
                // hide response about languages - remove this code to display languages response
                unset($updatedmall->languages);

                $update_free_wifi = Widget::excludeDeleted()
                        ->leftJoin('widget_retailer', 'widget_retailer.widget_id', '=', 'widgets.widget_id')
                        ->where('widget_type', 'free_wifi')
                        ->where('retailer_id', $updatedmall->merchant_id)
                        ->first();

                $widget_status = $free_wifi_status;
                if (count($update_free_wifi) > 0) {
                    $update_free_wifi->status = $free_wifi_status;
                    $update_free_wifi->modified_by = $this->api->user->user_id;
                    if ($update_free_wifi->status === 'inactive') {
                        $count_wiget = Widget::excludeDeleted()
                                ->leftJoin('widget_retailer', 'widget_retailer.widget_id', '=', 'widgets.widget_id')
                                ->where('retailer_id', $updatedmall->merchant_id)
                                ->count();

                        $update_free_wifi->widget_order = $count_wiget + 1;
                    }
                    $update_free_wifi->save();

                    $widget_status = $update_free_wifi->status;
                } else {
                    $new_widget = new stdClass();
                    foreach ($this->default['widgets'] as $data_widget) {
                        if ($data_widget['type'] === 'free_wifi') {
                            $new_widget = new Widget();
                            $new_widget->widget_type = $data_widget['type'];
                            $new_widget->widget_object_id = $data_widget['object_id'];
                            $new_widget->widget_slogan = $data_widget['slogan']['default'];
                            $new_widget->widget_order = $data_widget['order'];
                            $new_widget->merchant_id = $updatedmall->merchant_id;
                            $new_widget->animation = $data_widget['animation'];
                            $new_widget->status = $free_wifi_status;
                            $new_widget->save();

                            $widget_status = $new_widget->status;

                            // Sync also to the widget_retailer table
                            $new_widget->malls()->sync( [$updatedmall->merchant_id] );

                            // Insert the translation for the slogan
                            $new_widget_trans = new stdClass();
                            $slogan = $data_widget['slogan'];
                            foreach ($languages_name as $lang) {
                                if (isset($slogan[$lang])) {
                                    // Get the Language ID
                                    // The content for this particular language is available
                                    $new_widget_trans = new WidgetTranslation();
                                    $new_widget_trans->widget_id = $new_widget->widget_id;
                                    $new_widget_trans->merchant_language_id = $languages_by_name[$lang]->language_id;
                                    $new_widget_trans->widget_slogan = $slogan[$lang];
                                    $new_widget_trans->status = 'active';
                                    $new_widget_trans->save();
                                }
                            }
                        }
                    }
                }

                $setting_items = [
                    'enable_free_wifi'              => 'true',
                    'enable_free_wifi_widget'       => 'true',
                ];

                foreach ($setting_items as $setting_name => $setting_value) {
                    if ($free_wifi_status === 'inactive') {
                        $setting_value = 'false';
                    }

                    $setting = Setting::excludeDeleted()
                                ->where('setting_name', $setting_name)
                                ->where('object_id', $updatedmall->merchant_id)
                                ->where('object_type', 'merchant')
                                ->first();

                    if (count($setting) > 0 ) {
                        $setting->setting_value = $setting_value;
                        $setting->save();
                    } else {
                        $settings = new Setting();
                        $settings->setting_name = $setting_name;
                        $settings->setting_value = $setting_value;
                        $settings->object_id = $updatedmall->merchant_id;
                        $settings->object_type = 'merchant';
                        $settings->status = 'active';
                        $settings->modified_by = $this->api->user->user_id;

                        $settings->save();
                    }
                }
                $updatedmall->free_wifi_status = $widget_status;
            });

            // Update map geo fences
            if ($geo_point_latitude != '' || $geo_point_longitude != '' || $geo_area != '') {
                $latitude = (double)$geo_point_latitude;
                $longitude = (double)$geo_point_longitude;
                $area = preg_replace('/[^0-9\s,\-\.]/', '',  $geo_area);

                $fence = MerchantGeofence::where('merchant_id', $merchant_id)->first();

                if (count($fence) > 0) {
                    $fence->area = DB::raw("GEOMFROMTEXT(\"POLYGON(({$area}))\")");
                    $fence->position = DB::raw("POINT($latitude, $longitude)");
                    $fence->save();
                } else {
                    $new_fence = new MerchantGeofence();
                    $new_fence->merchant_id = $updatedmall->merchant_id;
                    $new_fence->area = DB::raw("GEOMFROMTEXT(\"POLYGON(({$area}))\")");
                    $new_fence->position = DB::raw("POINT($latitude, $longitude)");
                    $new_fence->save();
                }
            }

            // Insert to mall_countries
            $countryName = Country::where('country_id', $country)->first();
            $existkMallCountry = MallCountry::where('country_id', $country)->first();
            if (empty($existkMallCountry)) {
              $new_mall_country = new MallCountry();
              $new_mall_country->country_id = $country;
              $new_mall_country->country = $countryName->name;
              $new_mall_country->save();
            }

            // Insert to mall_cities
            $checkMallCity = MallCity::where('city', $city)->first();
            if (empty($checkMallCity)) {
              $new_mall_city = new MallCity();
              $new_mall_city->city = $city;
              $new_mall_city->country_id = $country;
              $new_mall_city->save();
            }

            // Insert vendor_gtm_country
            $existVendorGtmCountry = VendorGTMCountry::where('gtm_country', $countryName->name)->first();
            if (empty($existVendorGtmCountry)) {
                $new_vendor_gtm_country = new VendorGTMCountry();
                $new_vendor_gtm_country->vendor_country = $ipcountry;
                $new_vendor_gtm_country->gtm_country =$countryName->name;
                $new_vendor_gtm_country->save();
            }

            // Insert vendor_gtm_city
            $vendor_ip_database = Config::get('orbit.vendor_ip_database.default', 'dbip');
            $checkVendorGtmCity = VendorGTMCity::where('gtm_city', $city)->where('vendor_type', $vendor_ip_database)->first();
            if (! empty($checkVendorGtmCity)) {
              // Delete first if exist gtm_city
              $deleteVendorGtmCity = VendorGTMCity::where('gtm_city', $city)->where('vendor_type', $vendor_ip_database)->delete();
            }

            foreach ($ipcity as $vendorCity) {
              $new_vendor_gtm_city = new VendorGTMCity();
              $new_vendor_gtm_city->vendor_type = $vendor_ip_database;
              $new_vendor_gtm_city->vendor_city = $vendorCity;
              $new_vendor_gtm_city->vendor_country = $ipcountry;
              $new_vendor_gtm_city->gtm_city = $city;
              $new_vendor_gtm_city->country_id = $country;
              $new_vendor_gtm_city->save();
            }

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

            // update link to partner
            OrbitInput::post('partner_ids', function($partner_ids) use ($updatedmall) {
                // Delete old data
                $delete_partner = ObjectPartner::where('object_id', '=', $updatedmall->merchant_id);
                $delete_partner->delete(true);

                if (! empty($partner_ids)) {
                  // Insert new data
                  foreach ($partner_ids as $partner_id) {
                    if ($partner_id != "") {
                      $object_partner = new ObjectPartner();
                      $object_partner->object_id = $updatedmall->merchant_id;
                      $object_partner->object_type = 'mall';
                      $object_partner->partner_id = $partner_id;
                      $object_partner->save();
                    }
                  }
                }
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

            $merchant_id = OrbitInput::post('merchant_id');
            $password = OrbitInput::post('password');

            $validator = Validator::make(
                array(
                    'merchant_id' => $merchant_id,
                    'password'    => $password,
                ),
                array(
                    'merchant_id' => 'required|orbit.empty.mall|orbit.exists.mall_have_tenant',
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

        // Check the existance of parent id
        Validator::extend('orbit.empty.mallgroup', function ($attribute, $value, $parameters) {
            $mallgroup = MallGroup::excludeDeleted()
                        ->where('merchant_id', $value)
                        ->first();

            if (empty($mallgroup)) {
                return FALSE;
            }

            App::instance('orbit.empty.mallgroup', $mallgroup);

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
            $mall_id = OrbitInput::post('merchant_id');
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
            $mall_id = OrbitInput::post('merchant_id');
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

        // Check link mall group, it should active (for update)
        Validator::extend('orbit_check_link_mallgroup', function ($attribute, $value, $parameters) {
            $mallgroup_id = OrbitInput::post('parent_id');

            if ($value === 'active') {
                $mallgroup = MallGroup::excludeDeleted()
                            ->where('merchant_id', '=', $mallgroup_id)
                            ->where('status', '=', 'inactive')
                            ->first();

                if (! empty($mallgroup)) {
                    return FALSE;
                }
            }
            return TRUE;
        });

        // Check link campaign, it should not inactive (for update)
        Validator::extend('orbit_check_link_campaign', function ($attribute, $value, $parameters) {
            $mall_id = OrbitInput::post('merchant_id');

            if ($value === 'inactive') {
                $coupon = Coupon::excludeDeleted()
                                ->where('merchant_id', '=', $mall_id)
                                ->where('status', '=', 'active')
                                ->first();

                if (! empty($coupon)) {
                    return FALSE;
                }

                $news = News::excludeDeleted()
                            ->where('mall_id', '=', $mall_id)
                            ->where('object_type', '=', 'news')
                            ->where('status', '=', 'active')
                            ->first();

                if (! empty($news)) {
                    return FALSE;
                }

                $promotion = News::excludeDeleted()
                            ->where('mall_id', '=', $mall_id)
                            ->where('object_type', '=', 'promotion')
                            ->where('status', '=', 'active')
                            ->first();

                if (! empty($promotion)) {
                    return FALSE;
                }
            }
            return TRUE;
        });

        // Check tenant mall, it should not inactive (for update)
        Validator::extend('orbit_check_tenant_mall', function ($attribute, $value, $parameters) {
            $mall_id = OrbitInput::post('merchant_id');

            if ($value === 'inactive') {
                $tenant = Tenant::excludeDeleted()
                        ->where('parent_id', '=', $mall_id)
                        ->where('status', '=', 'active')
                        ->first();

                if (! empty($tenant)) {
                    return false;
                }
            }
            return TRUE;
        });

        // Check mall name, it should not exists
        Validator::extend('orbit.exists.mall_name', function ($attribute, $value, $parameters) {
            $mall = Mall::excludeDeleted()
                        ->where('name', $value)
                        ->where('object_type', 'mall')
                        ->first();

            if (! empty($mall)) {
                return FALSE;
            }

            App::instance('orbit.validation.mall_name', $mall);

            return TRUE;
        });

        // Check mall name, it should not exists (for update)
        Validator::extend('mall_name_exists_but_me', function ($attribute, $value, $parameters) {
            $mall_id = OrbitInput::post('merchant_id');

            $mall = Mall::excludeDeleted()
                        ->where('name', $value)
                        ->where('merchant_id', '!=', $mall_id)
                        ->where('object_type', 'mall')
                        ->first();

            if (! empty($mall)) {
                return FALSE;
            }

            App::instance('orbit.validation.mall_name', $mall);

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

        Validator::extend('orbit.exists.timezone', function($attribute, $value, $parameters) {
            $timezone = Timezone::where('timezone_name', $value)
                ->first();

            if (empty($timezone)) {
                return FALSE;
            }

            $this->valid_timezone = $timezone;

            return TRUE;
        });

        Validator::extend('orbit.formaterror.language', function($attribute, $value, $parameters)
        {
            $lang = Language::where('name', '=', $value)->where('status', '=', 'active')->first();

            if (empty($lang)) {
                return FALSE;
            }

            $this->valid_lang = $lang;
            return TRUE;
        });

        Validator::extend('orbit.exists.mall_language', function($attribute, $value, $parameters)
        {
            $mall_id = $parameters[0];
            $lang = MerchantLanguage::excludeDeleted('merchant_languages')
                        ->excludeDeleted('languages')
                        ->join('languages', 'languages.language_id', '=', 'merchant_languages.language_id')
                        ->where('merchant_languages.merchant_id', $mall_id)
                        ->where('languages.name', $value)
                        ->first();

            if (empty($lang)) {
                return FALSE;
            }

            $this->valid_mall_lang = $lang;
            return TRUE;
        });

        Validator::extend('orbit.exists.domain', function($attribute, $value, $parameters)
        {
            $ci_domain_config = Config::get('orbit.shop.ci_domain');
            $domain = Mall::excludeDeleted()
                        ->where('ci_domain', $value . $ci_domain_config)
                        ->first();

            if (! empty($domain)) {
                return FALSE;
            }

            return TRUE;
        });

        Validator::extend('domain_exist_but_not_me', function($attribute, $value, $parameters)
        {
            $merchant_id = $parameters[0];
            $ci_domain_config = Config::get('orbit.shop.ci_domain');
            $domain = Mall::excludeDeleted()
                        ->where('ci_domain', $value . $ci_domain_config)
                        ->where('merchant_id', '!=', $merchant_id)
                        ->first();

            if (! empty($domain)) {
                return FALSE;
            }

            return TRUE;
        });

        Validator::extend('orbit.formaterror.geo_latitude', function($attribute, $value, $parameters)
        {
            $geo_latitude = $value;

            if (! ($geo_latitude > -90 && $geo_latitude < 90)) {
                return FALSE;
            }

            return TRUE;
        });

        Validator::extend('orbit.formaterror.geo_longitude', function($attribute, $value, $parameters)
        {
            $geo_longitude = $value;

            if (! ($geo_longitude > -180 && $geo_longitude < 180)) {
                return FALSE;
            }

            return TRUE;
        });

        Validator::extend('orbit.formaterror.geo_area', function($attribute, $value, $parameters)
        {
            $geo_area = explode(',', $value);

            // check area array count
            if (count($geo_area) < 4) {
                return FALSE;
            }

            // check first and last point
            $first_point = reset($geo_area);
            $last_point = end($geo_area);

            if (trim($first_point) !== trim($last_point)) {
                return FALSE;
            }

            unset($geo_area[0]); // remove first idx to make uniq point
            $points = array();
            // check range longitude and latitude
            foreach ($geo_area as $idx => $point){
                $latlon = explode(' ', trim($point));

                // check lon
                if (! ($latlon[1] >= -180 && $latlon[1] <= 180)) {
                    return FALSE;
                }

                // check lat
                if (! ($latlon[0] >= -90 && $latlon[0] <= 90)) {
                    return FALSE;
                }

                // push point to points to check it
                array_push($points, trim($point));
            }

            // check the same point
            foreach ($points as $idx => $value) {
                unset($points[$idx]);

                if (in_array($value, $points)) {
                    return FALSE;
                }
            }

            return TRUE;
        });

        Validator::extend('orbit.file.max_size', function ($attribute, $value, $parameters) {
            $config_size = $parameters[0];
            $file_size = $value;

            if ($file_size > $config_size) {
                return false;
            }

            return true;
        });

        // Check the images, we are allowed array of images but not more than
        Validator::extend('nomore.than', function ($attribute, $value, $parameters) {
            $max_count = $parameters[0];

            if (is_array($value['name']) && count($value['name']) > $max_count) {
                return FALSE;
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
            $validRoles = ['super admin', 'mall admin', 'mall owner', 'consumer', 'campaign owner', 'campaign employee', 'campaign admin'];
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

            $base_price = CampaignBasePrice::where('merchant_id', '=', $merchant_id);

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

    protected function generate_validation_image($image_name, $images, $config, $max_count = 1) {
        $validation = [];
        if (! empty($images)) {
            $images_properties = OrbitUploader::simplifyFilesVar($images);
            $image_config = Config::get($config);
            $image_type =  "image/" . implode(",image/", $image_config['file_type']);
            $image_units = OrbitUploader::bytesToUnits($image_config['file_size']);

            $validation['data'] = [
                $image_name => $images
            ];
            $validation['error'] = [
                $image_name => 'nomore.than:' . $max_count
            ];
            $validation['error_message'] = [
                $image_name . '.nomore.than' => Lang::get('validation.max.array', array('max' => $max_count))
            ];

            foreach ($images_properties as $idx => $image) {
                $ext = strtolower(substr(strrchr($image->name, '.'), 1));
                $idx+=1;

                $validation['data'][$image_name . '_type_' . $idx] = $image->type;
                $validation['data'][$image_name . '_size_' . $idx] = $image->size;

                $validation['error'][$image_name . '_type_' . $idx] = 'in:' . $image_type;
                $validation['error'][$image_name . '_size_' . $idx] = 'orbit.file.max_size:' . $image_config['file_size'];

                $validation['error_message'][$image_name . '_type_' . $idx . '.in'] = Lang::get('validation.orbit.file.type', array('ext' => $ext));
                $validation['error_message'][$image_name . '_size_' . $idx . '.orbit.file.max_size'] = Lang::get('validation.orbit.file.max_size', array('name' => $image_config['name'], 'size' => $image_units['newsize'], 'unit' => $image_units['unit']));
            }
        }
        return $validation;
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }

    /**
     * API Controller to get Mall name and merchant ID only
     */
    public function getSearchMallName()
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

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = ['super admin', 'mall admin', 'mall owner', 'merchant database admin'];
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
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
                    'sort_by' => 'in:merchant_name',
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

            // Get Facebook social media ID
            $facebookSocmedId = SocialMedia::whereSocialMediaCode('facebook')->first()->social_media_id;

            $malls = Mall::excludeDeleted('merchants')
                ->select(
                    'merchants.name',
                    'merchants.merchant_id'
                )
                ->join('countries', 'countries.country_id', '=', 'merchants.country_id')
                ->groupBy('merchants.merchant_id');

            // Filter mall by Ids
            OrbitInput::get('merchant_id', function ($merchantIds) use ($malls) {
                $malls->whereIn('merchants.merchant_id', $merchantIds);
            });

            // Filter mall by name
            OrbitInput::get('name', function ($name) use ($malls) {
                $malls->whereIn('merchants.name', $name);
            });

            // Filter mall by name pattern
            OrbitInput::get('name_like', function ($name) use ($malls) {
                $malls->where('merchants.name', 'like', "%$name%");
            });

            // Filter mall by countryID
            OrbitInput::get('country_id', function ($countryId) use ($malls) {
                $malls->where('merchants.country_id', $countryId);
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
                    'merchant_name'        => 'merchants.name',
                    'merchantid'           => 'merchants.merchant_id',
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
}
