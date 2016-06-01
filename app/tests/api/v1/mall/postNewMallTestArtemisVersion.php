<?php
/**
 * PHP Unit Test for Mall API Controller postNewMall
 *
 * @author: Irianto Pratama <irianto@dominopos.com>
 */

use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;
use Faker\Factory as Faker;

class postNewMallTestArtemisVersion extends TestCase
{
    private $apiUrl = 'api/v1/mall/new';

    public function setUp()
    {
        parent::setUp();

        $this->apiKey = Factory::create('apikey_super_admin');

        $this->enLang = Factory::create('Language', ['name' => 'en']);

        $this->country = Factory::create('Country');

        $this->timezone = Factory::create('Timezone');

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

    public function testNotRequiredCategory()
    {
        /*
        * test Category is not more required
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
            'contact_person_phone'          => 321654,
            'contact_person_email'          => 'antok@adminmall.com',
            'status'                        => 'active',
            'timezone'                      => $this->timezone->timezone_name,
            'currency'                      => 'IDR',
            'currency_symbol'               => 'Rp',
            'vat_included'                  => 'yes',
            'sector_of_activity'            => 'Mall',
            'languages'                     => ['en'],
            'mobile_default_language'       => 'en',
            'domain'                        => 'orbit-mall.mall.irianto',
            'geo_point_latitude'            => '-8.663937',
            'geo_point_longitude'           => '115.174142',
            'geo_area'                      => '-8.663007 115.174527,-8.662275 115.176930,-8.664174 115.177735,-8.665669 115.175836,-8.664842 115.174227,-8.663007 115.174527',
            'campaign_base_price_promotion' => 100,
            'campaign_base_price_coupon'    => 200,
            'campaign_base_price_news'      => 300,
            'floors'                        => ["{\"name\":\"B3\",\"order\":\"1\"}"]
        ];

        $response = $this->setRequestPostNewMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
    }

    public function testWidgetGetInternetAccessActive()
    {
        /*
        * test widget get internet access set to active
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
            'contact_person_phone'          => 321654,
            'contact_person_email'          => 'antok@adminmall.com',
            'status'                        => 'active',
            'timezone'                      => $this->timezone->timezone_name,
            'currency'                      => 'IDR',
            'currency_symbol'               => 'Rp',
            'vat_included'                  => 'yes',
            'sector_of_activity'            => 'Mall',
            'languages'                     => ['en'],
            'mobile_default_language'       => 'en',
            'domain'                        => 'orbit-mall.mall.irianto',
            'geo_point_latitude'            => '-8.663937',
            'geo_point_longitude'           => '115.174142',
            'geo_area'                      => '-8.663007 115.174527,-8.662275 115.176930,-8.664174 115.177735,-8.665669 115.175836,-8.664842 115.174227,-8.663007 115.174527',
            'campaign_base_price_promotion' => 100,
            'campaign_base_price_coupon'    => 200,
            'campaign_base_price_news'      => 300,
            'floors'                        => ["{\"name\":\"B3\",\"order\":\"1\"}"],
            'get_internet_access_status'    => 'active',
        ];

        $response = $this->setRequestPostNewMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
        $this->assertSame("active", $response->data->get_internet_access_status);
    }

    public function testWidgetGetInternetAccessInactive()
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
            'contact_person_phone'          => 321654,
            'contact_person_email'          => 'antok@adminmall.com',
            'status'                        => 'active',
            'timezone'                      => $this->timezone->timezone_name,
            'currency'                      => 'IDR',
            'currency_symbol'               => 'Rp',
            'vat_included'                  => 'yes',
            'sector_of_activity'            => 'Mall',
            'languages'                     => ['en'],
            'mobile_default_language'       => 'en',
            'domain'                        => 'orbit-mall.mall.irianto',
            'geo_point_latitude'            => '-8.663937',
            'geo_point_longitude'           => '115.174142',
            'geo_area'                      => '-8.663007 115.174527,-8.662275 115.176930,-8.664174 115.177735,-8.665669 115.175836,-8.664842 115.174227,-8.663007 115.174527',
            'campaign_base_price_promotion' => 100,
            'campaign_base_price_coupon'    => 200,
            'campaign_base_price_news'      => 300,
            'floors'                        => ["{\"name\":\"B3\",\"order\":\"1\"}"],
            'get_internet_access_status'    => 'inactive',
        ];

        $response = $this->setRequestPostNewMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
        $this->assertSame("inactive", $response->data->get_internet_access_status);
    }
}