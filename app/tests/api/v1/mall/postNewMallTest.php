<?php
/**
 * PHP Unit Test for Mall API Controller postNewMall
 *
 * @author: Irianto Pratama <irianto@dominopos.com>
 */

use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;
use Faker\Factory as Faker;

class postNewMallTest extends TestCase
{
    private $apiUrl = 'api/v1/mall/new';

    public function setUp()
    {
        parent::setUp();

        $this->apiKey = Factory::create('apikey_super_admin');

        $this->enLang = Factory::create('Language', ['name' => 'en']);
        $this->idLang = Factory::create('Language', ['name' => 'id']);
        $this->zhLang = Factory::create('Language', ['name' => 'zh']);
        $this->jpLang = Factory::create('Language', ['name' => 'jp']);

        $this->country = Factory::create('Country');

        $this->timezone = Factory::create('timezone_jakarta');

        Factory::create('role_mall_owner');
    }

    public function setRequestPostNewMall($api_key, $api_secret_key, $new_data)
    {
        $_GET = [];
        $_POST = [];

        // Set the client API Keys
        $_GET['apikey'] = $api_key;
        $_GET['apitimestamp'] = time();

        foreach ($new_data as $field => $value) {
            $_POST[$field] = $value;
        }
        $url = $this->apiUrl . '?' . http_build_query($_GET);

        $secretKey = $api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $json = $this->call('POST', $url)->getContent();
        $response = json_decode($json);

        unset($_POST);
        unset($_GET);

        return $response;
    }

    public function testRequiredMallName()
    {
        /*
        * test mall name is required
        */
        $data = [];

        $response = $this->setRequestPostNewMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("Mall name is required", $response->message);
        $this->assertSame(NULL, $response->data);
    }

    public function testRequiredEmail()
    {
        /*
        * test email is required
        */
        $data = ['name' => 'antok mall'];

        $response = $this->setRequestPostNewMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("The email address is required", $response->message);
        $this->assertSame(NULL, $response->data);
    }

    public function testRequiredPassword()
    {
        /*
        * test password is required
        */
        $data = ['name' => 'antok mall',
            'email' => 'antokmall@bumi.com'
        ];

        $response = $this->setRequestPostNewMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("The password field is required", $response->message);
        $this->assertSame(NULL, $response->data);
    }

    public function testRequiredAddress()
    {
        /*
        * test address is required
        */
        $data = ['name' => 'antok mall',
            'email'    => 'antokmall@bumi.com',
            'password' => '123456'
        ];

        $response = $this->setRequestPostNewMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("The address is required", $response->message);
        $this->assertSame(NULL, $response->data);
    }

    public function testRequiredCity()
    {
        /*
        * test city is required
        */
        $data = ['name' => 'antok mall',
            'email'         => 'antokmall@bumi.com',
            'password'      => '123456',
            'address_line1' => 'jalan sudirman no 1'
        ];

        $response = $this->setRequestPostNewMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("The city field is required", $response->message);
        $this->assertSame(NULL, $response->data);
    }

    public function testRequiredCountry()
    {
        /*
        * test Country is required
        */
        $data = ['name' => 'antok mall',
            'email'         => 'antokmall@bumi.com',
            'password'      => '123456',
            'address_line1' => 'jalan sudirman no 1',
            'city'          => 'badung'
        ];

        $response = $this->setRequestPostNewMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("The country field is required", $response->message);
        $this->assertSame(NULL, $response->data);
    }

    public function testRequiredFloor()
    {
        /*
        * test required floor
        */
        $data = ['name' => 'antok mall',
            'email'                         => 'antokmall@bumi.com',
            'password'                      => '123456',
            'address_line1'                 => 'jalan sudirman no 1',
            'city'                          => 'badung',
            'country'                       => $this->country->country_id,
            'phone'                         => 123465,
            'contact_person_firstname'      => 'antok',
            'contact_person_lastname'       => 'mall',
            'status'                        => 'active',
            'languages'                     => ['jp','zh','id'],
            'mobile_default_language'       => 'id',
            'domain'                        => 'orbit',
            'geo_point_latitude'            => '-8.663937',
            'geo_point_longitude'           => '115.174142',
            'geo_area'                      => '-8.663007 115.174527,-8.662275 115.176930,-8.664174 115.177735,-8.665669 115.175836,-8.664842 115.174227,-8.663007 115.174527',
        ];

        $response = $this->setRequestPostNewMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("The floors field is required", $response->message);
    }

    public function testFloorMustAnArray()
    {
        /*
        * test floor must an array
        */
        $data = ['name' => 'antok mall',
            'email'                         => 'antokmall@bumi.com',
            'password'                      => '123456',
            'address_line1'                 => 'jalan sudirman no 1',
            'city'                          => 'badung',
            'country'                       => $this->country->country_id,
            'phone'                         => 123465,
            'contact_person_firstname'      => 'antok',
            'contact_person_lastname'       => 'mall',
            'status'                        => 'active',
            'languages'                     => ['jp','zh','id'],
            'mobile_default_language'       => 'id',
            'domain'                        => 'orbit',
            'geo_point_latitude'            => '-8.663937',
            'geo_point_longitude'           => '115.174142',
            'floors'                         => 'L1',
            'geo_area'                      => '-8.663007 115.174527,-8.662275 115.176930,-8.664174 115.177735,-8.665669 115.175836,-8.664842 115.174227,-8.663007 115.174527',
        ];

        $response = $this->setRequestPostNewMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("The floors must be an array", $response->message);
    }

    public function testRequiredSubdomain()
    {
        /*
        * test floor must an array
        */
        $data = ['name' => 'antok mall',
            'email'                         => 'antokmall@bumi.com',
            'password'                      => '123456',
            'address_line1'                 => 'jalan sudirman no 1',
            'city'                          => 'badung',
            'country'                       => $this->country->country_id,
            'phone'                         => 123465,
            'contact_person_firstname'      => 'antok',
            'contact_person_lastname'       => 'mall',
            'status'                        => 'active',
            'languages'                     => ['jp','zh','id'],
            'mobile_default_language'       => 'id',
            'geo_point_latitude'            => '-8.663937',
            'geo_point_longitude'           => '115.174142',
            'floors'                         => 'L1',
            'geo_area'                      => '-8.663007 115.174527,-8.662275 115.176930,-8.664174 115.177735,-8.665669 115.175836,-8.664842 115.174227,-8.663007 115.174527',
        ];

        $response = $this->setRequestPostNewMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("The domain field is required", $response->message); // was handle on frontend 'Mall URL Application Domain is required'
    }

    public function testExistMallName()
    {
        /*
        * test exist mall name
        */
        Factory::create('Mall', ['name' => 'antok mall']);

        $data = ['name' => 'antok mall'];

        $response = $this->setRequestPostNewMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("Mall name already exists", $response->message);
        $this->assertSame(NULL, $response->data);
    }

    public function testWidgetFreeWifiActive()
    {
        /*
        * test widget free wifi set to active
        */
        $data = ['name' => 'antok mall',
            'email'                         => 'antokmall@bumi.com',
            'password'                      => '123456',
            'address_line1'                 => 'jalan sudirman no 1',
            'city'                          => 'badung',
            'country'                       => $this->country->country_id,
            'phone'                         => 123465,
            'contact_person_firstname'      => 'antok',
            'contact_person_lastname'       => 'mall',
            'status'                        => 'active',
            'languages'                     => ['en'],
            'mobile_default_language'       => 'en',
            'domain'                        => 'orbit-mall',
            'geo_point_latitude'            => '-8.663937',
            'geo_point_longitude'           => '115.174142',
            'geo_area'                      => '-8.663007 115.174527,-8.662275 115.176930,-8.664174 115.177735,-8.665669 115.175836,-8.664842 115.174227,-8.663007 115.174527',
            'floors'                        => ["{\"name\":\"B3\",\"order\":\"1\"}"],
            'free_wifi_status'              => 'active',
        ];

        $response = $this->setRequestPostNewMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
        $this->assertSame("active", $response->data->free_wifi_status);
    }

    public function testWidgetFreeWifiInactive()
    {
        /*
        * test widget get internet access set to inactive
        */
        $data = ['name' => 'antok mall',
            'email'                         => 'antokmall@bumi.com',
            'password'                      => '123456',
            'address_line1'                 => 'jalan sudirman no 1',
            'city'                          => 'badung',
            'country'                       => $this->country->country_id,
            'phone'                         => 123465,
            'contact_person_firstname'      => 'antok',
            'contact_person_lastname'       => 'mall',
            'status'                        => 'active',
            'languages'                     => ['en'],
            'mobile_default_language'       => 'en',
            'domain'                        => 'orbit-mall',
            'geo_point_latitude'            => '-8.663937',
            'geo_point_longitude'           => '115.174142',
            'geo_area'                      => '-8.663007 115.174527,-8.662275 115.176930,-8.664174 115.177735,-8.665669 115.175836,-8.664842 115.174227,-8.663007 115.174527',
            'floors'                        => ["{\"name\":\"B3\",\"order\":\"1\"}"],
            'free_wifi_status'              => 'inactive',
        ];

        $response = $this->setRequestPostNewMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
        $this->assertSame("inactive", $response->data->free_wifi_status);
    }

    public function testInsertFloor()
    {
        /*
        * test insert floor when create mall
        */
        $floor_array = ["{\"name\":\"B3\",\"order\":\"0\"}","{\"name\":\"B2\",\"order\":\"1\"}","{\"name\":\"B1\",\"order\":\"2\"}"];

        $data = ['name' => 'antok mall',
            'email'                         => 'antokmall@bumi.com',
            'password'                      => '123456',
            'address_line1'                 => 'jalan sudirman no 1',
            'city'                          => 'badung',
            'country'                       => $this->country->country_id,
            'phone'                         => 123465,
            'contact_person_firstname'      => 'antok',
            'contact_person_lastname'       => 'mall',
            'status'                        => 'active',
            'languages'                     => ['en'],
            'mobile_default_language'       => 'en',
            'domain'                        => 'orbit',
            'geo_point_latitude'            => '-8.663937',
            'geo_point_longitude'           => '115.174142',
            'geo_area'                      => '-8.663007 115.174527,-8.662275 115.176930,-8.664174 115.177735,-8.665669 115.175836,-8.664842 115.174227,-8.663007 115.174527',
            'floors'                        => $floor_array
        ];

        $response = $this->setRequestPostNewMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);

        $floor_on_db = Object::excludeDeleted()
                        ->where('merchant_id', $response->data->merchant_id)
                        ->where('object_type', 'floor')
                        ->get();

        $this->assertSame(3, count($floor_on_db));

        foreach ($floor_on_db as $floor_db) {
            foreach ($floor_array as $floor_json) {
                $floor = @json_decode($floor_json);
                if ($floor_db->object_order === $floor->order) {
                    $this->assertSame($floor_db->object_name, $floor->name);
                }
            }
        }
    }

    public function testInsertFloorErrorWhenDuplicateFloorName()
    {
        /*
        * test insert floor when create mall
        */
        $floor_array = ["{\"name\":\"B3\",\"order\":\"0\"}","{\"name\":\"B2\",\"order\":\"1\"}","{\"name\":\"B3\",\"order\":\"2\"}"];

        $data = ['name' => 'antok mall',
            'email'                         => 'antokmall@bumi.com',
            'password'                      => '123456',
            'address_line1'                 => 'jalan sudirman no 1',
            'city'                          => 'badung',
            'country'                       => $this->country->country_id,
            'phone'                         => 123465,
            'contact_person_firstname'      => 'antok',
            'contact_person_lastname'       => 'mall',
            'status'                        => 'active',
            'languages'                     => ['en'],
            'mobile_default_language'       => 'en',
            'domain'                        => 'orbit',
            'geo_point_latitude'            => '-8.663937',
            'geo_point_longitude'           => '115.174142',
            'geo_area'                      => '-8.663007 115.174527,-8.662275 115.176930,-8.664174 115.177735,-8.665669 115.175836,-8.664842 115.174227,-8.663007 115.174527',
            'floors'                        => $floor_array
        ];

        $response = $this->setRequestPostNewMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("Floor name has already been used", $response->message);
    }

    public function testInsertSubDomain()
    {
        /*
        * test insert sub domain when create mall
        */
        $subdomain = 'lippomall';

        $data = ['name' => 'antok mall',
            'email'                         => 'antokmall@bumi.com',
            'password'                      => '123456',
            'address_line1'                 => 'jalan sudirman no 1',
            'city'                          => 'badung',
            'country'                       => $this->country->country_id,
            'phone'                         => 123465,
            'contact_person_firstname'      => 'antok',
            'contact_person_lastname'       => 'mall',
            'status'                        => 'active',
            'languages'                     => ['en'],
            'mobile_default_language'       => 'en',
            'domain'                        => $subdomain,
            'geo_point_latitude'            => '-8.663937',
            'geo_point_longitude'           => '115.174142',
            'geo_area'                      => '-8.663007 115.174527,-8.662275 115.176930,-8.664174 115.177735,-8.665669 115.175836,-8.664842 115.174227,-8.663007 115.174527',
            'floors'                        => ["{\"name\":\"B3\",\"order\":\"0\"}","{\"name\":\"B2\",\"order\":\"1\"}","{\"name\":\"B1\",\"order\":\"2\"}"]
        ];

        $response = $this->setRequestPostNewMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
        $this->assertSame($subdomain . Config::get('orbit.shop.ci_domain'), $response->data->ci_domain);

        // check domain setting
        $dom_setting = Setting::where('setting_value', $response->data->merchant_id)
                            ->where('setting_name', 'like', '%dom%')
                            ->first();

        $this->assertSame('dom:' . $subdomain . Config::get('orbit.shop.ci_domain'), $dom_setting->setting_name);
    }

    public function testInsertSubDomainAlphaNumericDash()
    {
        /*
        * test insert sub domain when create mall
        */
        $subdomain = 'lippomall-23';

        $data = ['name' => 'antok mall',
            'email'                         => 'antokmall@bumi.com',
            'password'                      => '123456',
            'address_line1'                 => 'jalan sudirman no 1',
            'city'                          => 'badung',
            'country'                       => $this->country->country_id,
            'phone'                         => 123465,
            'contact_person_firstname'      => 'antok',
            'contact_person_lastname'       => 'mall',
            'status'                        => 'active',
            'languages'                     => ['en'],
            'mobile_default_language'       => 'en',
            'domain'                        => $subdomain,
            'geo_point_latitude'            => '-8.663937',
            'geo_point_longitude'           => '115.174142',
            'geo_area'                      => '-8.663007 115.174527,-8.662275 115.176930,-8.664174 115.177735,-8.665669 115.175836,-8.664842 115.174227,-8.663007 115.174527',
            'floors'                        => ["{\"name\":\"B3\",\"order\":\"0\"}","{\"name\":\"B2\",\"order\":\"1\"}","{\"name\":\"B1\",\"order\":\"2\"}"]
        ];

        $response = $this->setRequestPostNewMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
        $this->assertSame($subdomain . Config::get('orbit.shop.ci_domain'), $response->data->ci_domain);

        // check domain setting
        $dom_setting = Setting::where('setting_value', $response->data->merchant_id)
                            ->where('setting_name', 'like', '%dom%')
                            ->first();

        $this->assertSame('dom:' . $subdomain . Config::get('orbit.shop.ci_domain'), $dom_setting->setting_name);
    }

    public function testInsertSubDomainAlphaNumericDashUnderscore()
    {
        /*
        * test insert sub domain when create mall
        */
        $subdomain = 'lippomall-2_3';

        $data = ['name' => 'antok mall',
            'email'                         => 'antokmall@bumi.com',
            'password'                      => '123456',
            'address_line1'                 => 'jalan sudirman no 1',
            'city'                          => 'badung',
            'country'                       => $this->country->country_id,
            'phone'                         => 123465,
            'contact_person_firstname'      => 'antok',
            'contact_person_lastname'       => 'mall',
            'status'                        => 'active',
            'languages'                     => ['en'],
            'mobile_default_language'       => 'en',
            'domain'                        => $subdomain,
            'geo_point_latitude'            => '-8.663937',
            'geo_point_longitude'           => '115.174142',
            'geo_area'                      => '-8.663007 115.174527,-8.662275 115.176930,-8.664174 115.177735,-8.665669 115.175836,-8.664842 115.174227,-8.663007 115.174527',
            'floors'                        => ["{\"name\":\"B3\",\"order\":\"0\"}","{\"name\":\"B2\",\"order\":\"1\"}","{\"name\":\"B1\",\"order\":\"2\"}"]
        ];

        $response = $this->setRequestPostNewMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
        $this->assertSame($subdomain . Config::get('orbit.shop.ci_domain'), $response->data->ci_domain);

        // check domain setting
        $dom_setting = Setting::where('setting_value', $response->data->merchant_id)
                            ->where('setting_name', 'like', '%dom%')
                            ->first();

        $this->assertSame('dom:' . $subdomain . Config::get('orbit.shop.ci_domain'), $dom_setting->setting_name);
    }

    public function testInsertSubDomainAlphaNumericDashDot()
    {
        /*
        * test insert sub domain when create mall
        */
        $subdomain = 'lippomall-23.mall';

        $data = ['name' => 'antok mall',
            'email'                         => 'antokmall@bumi.com',
            'password'                      => '123456',
            'address_line1'                 => 'jalan sudirman no 1',
            'city'                          => 'badung',
            'country'                       => $this->country->country_id,
            'phone'                         => 123465,
            'contact_person_firstname'      => 'antok',
            'contact_person_lastname'       => 'mall',
            'status'                        => 'active',
            'languages'                     => ['en'],
            'mobile_default_language'       => 'en',
            'domain'                        => $subdomain,
            'geo_point_latitude'            => '-8.663937',
            'geo_point_longitude'           => '115.174142',
            'geo_area'                      => '-8.663007 115.174527,-8.662275 115.176930,-8.664174 115.177735,-8.665669 115.175836,-8.664842 115.174227,-8.663007 115.174527',
            'floors'                        => ["{\"name\":\"B3\",\"order\":\"0\"}","{\"name\":\"B2\",\"order\":\"1\"}","{\"name\":\"B1\",\"order\":\"2\"}"]
        ];

        $response = $this->setRequestPostNewMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("The domain may only contain letters, numbers, and dashes", $response->message);
    }

    public function testInsertSubDomainAlphaNumericDashOtherChar()
    {
        /*
        * test insert sub domain when create mall
        */
        $subdomain = 'lippomall-23m#$%^@all';

        $data = ['name' => 'antok mall',
            'email'                         => 'antokmall@bumi.com',
            'password'                      => '123456',
            'address_line1'                 => 'jalan sudirman no 1',
            'city'                          => 'badung',
            'country'                       => $this->country->country_id,
            'phone'                         => 123465,
            'contact_person_firstname'      => 'antok',
            'contact_person_lastname'       => 'mall',
            'status'                        => 'active',
            'languages'                     => ['en'],
            'mobile_default_language'       => 'en',
            'domain'                        => $subdomain,
            'geo_point_latitude'            => '-8.663937',
            'geo_point_longitude'           => '115.174142',
            'geo_area'                      => '-8.663007 115.174527,-8.662275 115.176930,-8.664174 115.177735,-8.665669 115.175836,-8.664842 115.174227,-8.663007 115.174527',
            'floors'                        => ["{\"name\":\"B3\",\"order\":\"0\"}","{\"name\":\"B2\",\"order\":\"1\"}","{\"name\":\"B1\",\"order\":\"2\"}"]
        ];

        $response = $this->setRequestPostNewMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("The domain may only contain letters, numbers, and dashes", $response->message);
    }

    public function testInsertDuplicateSubDomain()
    {
        /*
        * test insert duplicate sub domain when create mall
        */
        $mall_a = Factory::Create('Mall', ['ci_domain' => 'lippomall.gotomalls.cool']);

        $subdomain = 'lippomall';

        $data = ['name' => 'antok mall',
            'email'                         => 'antokmall@bumi.com',
            'password'                      => '123456',
            'address_line1'                 => 'jalan sudirman no 1',
            'city'                          => 'badung',
            'country'                       => $this->country->country_id,
            'phone'                         => 123465,
            'contact_person_firstname'      => 'antok',
            'contact_person_lastname'       => 'mall',
            'status'                        => 'active',
            'languages'                     => ['en'],
            'mobile_default_language'       => 'en',
            'domain'                        => $subdomain,
            'geo_point_latitude'            => '-8.663937',
            'geo_point_longitude'           => '115.174142',
            'geo_area'                      => '-8.663007 115.174527,-8.662275 115.176930,-8.664174 115.177735,-8.665669 115.175836,-8.664842 115.174227,-8.663007 115.174527',
            'floors'                        => ["{\"name\":\"B3\",\"order\":\"0\"}","{\"name\":\"B2\",\"order\":\"1\"}","{\"name\":\"B1\",\"order\":\"2\"}"]
        ];

        $response = $this->setRequestPostNewMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("Mall URL Application Domain name has already been taken", $response->message);
    }

    public function testInsertDescriptionNotRequired()
    {
        /*
        * test insert description when create mall
        */
        $description = 'lippomall';

        $data = ['name' => 'antok mall',
            'email'                         => 'antokmall@bumi.com',
            'password'                      => '123456',
            'address_line1'                 => 'jalan sudirman no 1',
            'city'                          => 'badung',
            'country'                       => $this->country->country_id,
            'phone'                         => 123465,
            'contact_person_firstname'      => 'antok',
            'contact_person_lastname'       => 'mall',
            'status'                        => 'active',
            'languages'                     => ['en'],
            'mobile_default_language'       => 'en',
            'domain'                        => 'lippomall',
            'geo_point_latitude'            => '-8.663937',
            'geo_point_longitude'           => '115.174142',
            'geo_area'                      => '-8.663007 115.174527,-8.662275 115.176930,-8.664174 115.177735,-8.665669 115.175836,-8.664842 115.174227,-8.663007 115.174527',
            'floors'                        => ["{\"name\":\"B3\",\"order\":\"0\"}","{\"name\":\"B2\",\"order\":\"1\"}","{\"name\":\"B1\",\"order\":\"2\"}"]
        ];

        $response = $this->setRequestPostNewMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
    }

    public function testInsertDescriptionMaxChar()
    {
        /*
        * test insert description when create mall
        */
        $description = 'lippomall';

        $data = ['name' => 'antok mall',
            'email'                         => 'antokmall@bumi.com',
            'password'                      => '123456',
            'description'                   => 'mall antok bagus banget ss',
            'address_line1'                 => 'jalan sudirman no 1',
            'city'                          => 'badung',
            'country'                       => $this->country->country_id,
            'phone'                         => 123465,
            'contact_person_firstname'      => 'antok',
            'contact_person_lastname'       => 'mall',
            'status'                        => 'active',
            'languages'                     => ['en'],
            'mobile_default_language'       => 'en',
            'domain'                        => 'lippomall',
            'geo_point_latitude'            => '-8.663937',
            'geo_point_longitude'           => '115.174142',
            'geo_area'                      => '-8.663007 115.174527,-8.662275 115.176930,-8.664174 115.177735,-8.665669 115.175836,-8.664842 115.174227,-8.663007 115.174527',
            'floors'                        => ["{\"name\":\"B3\",\"order\":\"0\"}","{\"name\":\"B2\",\"order\":\"1\"}","{\"name\":\"B1\",\"order\":\"2\"}"]
        ];

        $response = $this->setRequestPostNewMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("The description may not be greater than 25 characters", $response->message);
    }

    public function testInsertLanguagesAndMobileLanguagesNotEnglish()
    {
        /*
        * test insert languages and mobile default languages
        */
        $languages               = ['jp','zh','id'];
        $mobile_default_language = 'id';
        $data = ['name' => 'antok mall',
            'email'                         => 'antokmall@bumi.com',
            'password'                      => '123456',
            'address_line1'                 => 'jalan sudirman no 1',
            'city'                          => 'badung',
            'country'                       => $this->country->country_id,
            'phone'                         => 123465,
            'contact_person_firstname'      => 'antok',
            'contact_person_lastname'       => 'mall',
            'status'                        => 'active',
            'languages'                     => $languages,
            'mobile_default_language'       => $mobile_default_language,
            'domain'                        => 'orbit',
            'geo_point_latitude'            => '-8.663937',
            'geo_point_longitude'           => '115.174142',
            'geo_area'                      => '-8.663007 115.174527,-8.662275 115.176930,-8.664174 115.177735,-8.665669 115.175836,-8.664842 115.174227,-8.663007 115.174527',
            'floors'                        => ["{\"name\":\"B3\",\"order\":\"1\"}"]
        ];

        $response = $this->setRequestPostNewMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
        // check data is on database

    }

    public function testMobileDefaultLangIsNotOnLanguages()
    {
        /*
        * test mobile default language not on language list
        */
        $languages               = ['jp','zh','id'];
        $mobile_default_language = 'en';
        $data = ['name' => 'antok mall',
            'email'                         => 'antokmall@bumi.com',
            'password'                      => '123456',
            'address_line1'                 => 'jalan sudirman no 1',
            'city'                          => 'badung',
            'country'                       => $this->country->country_id,
            'phone'                         => 123465,
            'contact_person_firstname'      => 'antok',
            'contact_person_lastname'       => 'mall',
            'status'                        => 'active',
            'languages'                     => $languages,
            'mobile_default_language'       => $mobile_default_language,
            'domain'                        => 'orbit',
            'geo_point_latitude'            => '-8.663937',
            'geo_point_longitude'           => '115.174142',
            'geo_area'                      => '-8.663007 115.174527,-8.662275 115.176930,-8.664174 115.177735,-8.665669 115.175836,-8.664842 115.174227,-8.663007 115.174527',
            'floors'                        => ["{\"name\":\"B3\",\"order\":\"1\"}"]
        ];

        $response = $this->setRequestPostNewMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("Mobile default language must on list languages", $response->message);
    }

    public function testGeofenceLatitudeRequired()
    {
        /*
        * test geofence
        */
        $geo_point_latitude  = NULL;
        $geo_point_longitude = '115.174142';
        $geo_point_area      = '-8.663007 115.174527,-8.662275 115.176930,-8.664174 115.177735,-8.665669 115.175836,-8.664842 115.174227,-8.663007 115.174527';

        $data = ['name' => 'antok mall',
            'email'                         => 'antokmall@bumi.com',
            'password'                      => '123456',
            'address_line1'                 => 'jalan sudirman no 1',
            'city'                          => 'badung',
            'country'                       => $this->country->country_id,
            'phone'                         => 123465,
            'contact_person_firstname'      => 'antok',
            'contact_person_lastname'       => 'mall',
            'status'                        => 'active',
            'languages'                     => ['jp','zh','id'],
            'mobile_default_language'       => 'id',
            'domain'                        => 'orbit',
            'geo_point_latitude'            => $geo_point_latitude,
            'geo_point_longitude'           => $geo_point_longitude,
            'geo_area'                      => $geo_point_area,
            'floors'                        => ["{\"name\":\"B3\",\"order\":\"1\"}"]
        ];

        $response = $this->setRequestPostNewMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("The geo point latitude field is required", $response->message);
    }

    public function testGeofenceLongitudeRequired()
    {
        /*
        * test geofence
        */
        $geo_point_latitude  = '-8.663937';
        $geo_point_longitude = NULL;
        $geo_point_area      = '-8.663007 115.174527,-8.662275 115.176930,-8.664174 115.177735,-8.665669 115.175836,-8.664842 115.174227,-8.663007 115.174527';

        $data = ['name' => 'antok mall',
            'email'                         => 'antokmall@bumi.com',
            'password'                      => '123456',
            'address_line1'                 => 'jalan sudirman no 1',
            'city'                          => 'badung',
            'country'                       => $this->country->country_id,
            'phone'                         => 123465,
            'contact_person_firstname'      => 'antok',
            'contact_person_lastname'       => 'mall',
            'status'                        => 'active',
            'languages'                     => ['jp','zh','id'],
            'mobile_default_language'       => 'id',
            'domain'                        => 'orbit',
            'geo_point_latitude'            => $geo_point_latitude,
            'geo_point_longitude'           => $geo_point_longitude,
            'geo_area'                      => $geo_point_area,
            'floors'                        => ["{\"name\":\"B3\",\"order\":\"1\"}"]
        ];

        $response = $this->setRequestPostNewMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("The geo point longitude field is required", $response->message);
    }

    public function testGeofenceAreaRequired()
    {
        /*
        * test geofence
        */
        $geo_point_latitude  = '-8.663937';
        $geo_point_longitude = '115.174142';
        $geo_point_area      = NULL;

        $data = ['name' => 'antok mall',
            'email'                         => 'antokmall@bumi.com',
            'password'                      => '123456',
            'address_line1'                 => 'jalan sudirman no 1',
            'city'                          => 'badung',
            'country'                       => $this->country->country_id,
            'phone'                         => 123465,
            'contact_person_firstname'      => 'antok',
            'contact_person_lastname'       => 'mall',
            'status'                        => 'active',
            'languages'                     => ['jp','zh','id'],
            'mobile_default_language'       => 'id',
            'domain'                        => 'orbit',
            'geo_point_latitude'            => $geo_point_latitude,
            'geo_point_longitude'           => $geo_point_longitude,
            'geo_area'                      => $geo_point_area,
            'floors'                        => ["{\"name\":\"B3\",\"order\":\"1\"}"]
        ];

        $response = $this->setRequestPostNewMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("The geo area field is required", $response->message);
    }

    public function testGeofenceLatitudeRangeError()
    {
        /*
        * test geofence
        */
        $geo_point_latitude  = '100';
        $geo_point_longitude = '115.174142';
        $geo_point_area      = '-8.663007 115.174527,-8.662275 115.176930,-8.664174 115.177735,-8.665669 115.175836,-8.664842 115.174227,-8.663007 115.174527';

        $data = ['name' => 'antok mall',
            'email'                         => 'antokmall@bumi.com',
            'password'                      => '123456',
            'address_line1'                 => 'jalan sudirman no 1',
            'city'                          => 'badung',
            'country'                       => $this->country->country_id,
            'phone'                         => 123465,
            'contact_person_firstname'      => 'antok',
            'contact_person_lastname'       => 'mall',
            'status'                        => 'active',
            'languages'                     => ['jp','zh','id'],
            'mobile_default_language'       => 'id',
            'domain'                        => 'orbit',
            'geo_point_latitude'            => $geo_point_latitude,
            'geo_point_longitude'           => $geo_point_longitude,
            'geo_area'                      => $geo_point_area,
            'floors'                        => ["{\"name\":\"B3\",\"order\":\"1\"}"]
        ];

        $response = $this->setRequestPostNewMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("The Geofence latitude is not on range", $response->message);
    }

    public function testGeofenceLongitudeRangeError()
    {
        /*
        * test geofence
        */
        $geo_point_latitude  = '-8.663937';
        $geo_point_longitude = '200';
        $geo_point_area      = '-8.663007 115.174527,-8.662275 115.176930,-8.664174 115.177735,-8.665669 115.175836,-8.664842 115.174227,-8.663007 115.174527';

        $data = ['name' => 'antok mall',
            'email'                         => 'antokmall@bumi.com',
            'password'                      => '123456',
            'address_line1'                 => 'jalan sudirman no 1',
            'city'                          => 'badung',
            'country'                       => $this->country->country_id,
            'phone'                         => 123465,
            'contact_person_firstname'      => 'antok',
            'contact_person_lastname'       => 'mall',
            'status'                        => 'active',
            'languages'                     => ['jp','zh','id'],
            'mobile_default_language'       => 'id',
            'domain'                        => 'orbit',
            'geo_point_latitude'            => $geo_point_latitude,
            'geo_point_longitude'           => $geo_point_longitude,
            'geo_area'                      => $geo_point_area,
            'floors'                        => ["{\"name\":\"B3\",\"order\":\"1\"}"]
        ];

        $response = $this->setRequestPostNewMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("The Geofence longitude is not on range", $response->message);
    }

    public function testGeofenceAreaPolygonFormatError()
    {
        /*
        * test geofence
        */
        $geo_point_latitude  = '-8.663937';
        $geo_point_longitude = '115.174142';
        $geo_point_area      = '-8.663007 115.174527,-8.662275 115.176930,-8.663007 115.174527';

        $data = ['name' => 'antok mall',
            'email'                         => 'antokmall@bumi.com',
            'password'                      => '123456',
            'address_line1'                 => 'jalan sudirman no 1',
            'city'                          => 'badung',
            'country'                       => $this->country->country_id,
            'phone'                         => 123465,
            'contact_person_firstname'      => 'antok',
            'contact_person_lastname'       => 'mall',
            'status'                        => 'active',
            'languages'                     => ['jp','zh','id'],
            'mobile_default_language'       => 'id',
            'domain'                        => 'orbit',
            'geo_point_latitude'            => $geo_point_latitude,
            'geo_point_longitude'           => $geo_point_longitude,
            'geo_area'                      => $geo_point_area,
            'floors'                        => ["{\"name\":\"B3\",\"order\":\"1\"}"]
        ];

        $response = $this->setRequestPostNewMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("The Geofence area is not valid", $response->message);
    }

    public function testGeofenceAreaPolygonFirstAndLastPointDifferent()
    {
        /*
        * test geofence
        */
        $geo_point_latitude  = '-8.663937';
        $geo_point_longitude = '115.174142';
        $geo_point_area      = '-8.663007 115.174527,-8.662275 115.176930,-8.664174 115.177735,-8.665669 115.175836,-8.664842 115.174227,-8.663007 115.174537';

        $data = ['name' => 'antok mall',
            'email'                         => 'antokmall@bumi.com',
            'password'                      => '123456',
            'address_line1'                 => 'jalan sudirman no 1',
            'city'                          => 'badung',
            'country'                       => $this->country->country_id,
            'phone'                         => 123465,
            'contact_person_firstname'      => 'antok',
            'contact_person_lastname'       => 'mall',
            'status'                        => 'active',
            'languages'                     => ['jp','zh','id'],
            'mobile_default_language'       => 'id',
            'domain'                        => 'orbit',
            'geo_point_latitude'            => $geo_point_latitude,
            'geo_point_longitude'           => $geo_point_longitude,
            'geo_area'                      => $geo_point_area,
            'floors'                        => ["{\"name\":\"B3\",\"order\":\"1\"}"]
        ];

        $response = $this->setRequestPostNewMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("The Geofence area is not valid", $response->message);
    }

    public function testGeofenceAreaLatLonFormatError()
    {
        /*
        * test geofence
        */
        $geo_point_latitude  = '-8.663937';
        $geo_point_longitude = '115.174142';
        $geo_point_area      = '-190 80,-191 81,-192 83,-190 80';

        $data = ['name' => 'antok mall',
            'email'                         => 'antokmall@bumi.com',
            'password'                      => '123456',
            'address_line1'                 => 'jalan sudirman no 1',
            'city'                          => 'badung',
            'country'                       => $this->country->country_id,
            'phone'                         => 123465,
            'contact_person_firstname'      => 'antok',
            'contact_person_lastname'       => 'mall',
            'status'                        => 'active',
            'languages'                     => ['jp','zh','id'],
            'mobile_default_language'       => 'id',
            'domain'                        => 'orbit',
            'geo_point_latitude'            => $geo_point_latitude,
            'geo_point_longitude'           => $geo_point_longitude,
            'geo_area'                      => $geo_point_area,
            'floors'                        => ["{\"name\":\"B3\",\"order\":\"1\"}"]
        ];

        $response = $this->setRequestPostNewMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("The Geofence area is not valid", $response->message);
    }

    public function testGeofenceAreaSamePointWillErrorExceptFirstAndLastPoint()
    {
        /*
        * test geofence
        */
        $geo_point_latitude  = '-8.663937';
        $geo_point_longitude = '115.174142';
        $geo_point_area      = '-90 180,-90 180,-90 180,-90 180';

        $data = ['name' => 'antok mall',
            'email'                         => 'antokmall@bumi.com',
            'password'                      => '123456',
            'address_line1'                 => 'jalan sudirman no 1',
            'city'                          => 'badung',
            'country'                       => $this->country->country_id,
            'phone'                         => 123465,
            'contact_person_firstname'      => 'antok',
            'contact_person_lastname'       => 'mall',
            'status'                        => 'active',
            'languages'                     => ['jp','zh','id'],
            'mobile_default_language'       => 'id',
            'domain'                        => 'orbit',
            'geo_point_latitude'            => $geo_point_latitude,
            'geo_point_longitude'           => $geo_point_longitude,
            'geo_area'                      => $geo_point_area,
            'floors'                        => ["{\"name\":\"B3\",\"order\":\"1\"}"]
        ];

        $response = $this->setRequestPostNewMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("The Geofence area is not valid", $response->message);
    }

    public function testGeofenceSuccess()
    {
        /*
        * test geofence
        */
        $geo_point_latitude  = '-8.663937';
        $geo_point_longitude = '115.174142';
        $geo_point_area      = '-0.219726 112.5, 0.329588 114.785156, -1.58183 114.345703, -0.219726 112.5';

        $data = ['name' => 'antok mall',
            'email'                         => 'antokmall@bumi.com',
            'password'                      => '123456',
            'address_line1'                 => 'jalan sudirman no 1',
            'city'                          => 'badung',
            'country'                       => $this->country->country_id,
            'phone'                         => 123465,
            'contact_person_firstname'      => 'antok',
            'contact_person_lastname'       => 'mall',
            'status'                        => 'active',
            'languages'                     => ['jp','zh','id'],
            'mobile_default_language'       => 'id',
            'domain'                        => 'orbit',
            'geo_point_latitude'            => $geo_point_latitude,
            'geo_point_longitude'           => $geo_point_longitude,
            'geo_area'                      => $geo_point_area,
            'floors'                        => ["{\"name\":\"B3\",\"order\":\"1\"}"]
        ];

        $response = $this->setRequestPostNewMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
        // check data is on database

        $geo_mall = MerchantGeofence::select(
                            DB::Raw('
                                asText(area) as area,
                                X(position) as latitude,
                                Y(position) as longitude
                            ')
                        )
                        ->where('merchant_id', $response->data->merchant_id)
                        ->first();

        $this->assertSame($geo_point_latitude, $geo_mall->latitude);
        $this->assertSame($geo_point_longitude, $geo_mall->longitude);
    }
}