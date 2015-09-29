<?php
/**
 * Unit test for API /api/v1/mall/update
 */
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;
use Faker\Factory as Faker;

class postUpdateMall extends TestCase
{

    private $authData;
    private $country;

    public function setUp()
    {
        parent::setUp();

        $this->authData = Factory::create('apikey_super_admin');
        $this->country = Factory::create('Country');
        Factory::create('role_mall_owner');
    }

    private function createMall()
    {
        $faker = Faker::create();
        $response = $this->makeCreateRequest([
            'email' => $faker->email,
            'name' => 'Dummy Name',
            'status' => 'active',
            'country' => $this->country->country_id
        ]);
        $this->assertResponseOk();
        return Mall::find($response->data->merchant_id);
    }

    private function makeRequestData($mall)
    {
        return [
            'merchant_id' => $mall->merchant_id,
            'status' => $mall->status
        ];
    }

    private function makeCreateRequest($post_data, $authData = null)
    {
        if ($authData === null) {
            $authData = $this->authData;
        }
        $_GET = [
            'apikey' => $authData->api_key,
            'apitimestamp' => time(),
        ];
        $_POST = $post_data;
        $url = '/api/v1/mall/new?' . http_build_query($_GET);
        $secretKey = $authData->api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $response = $this->call('POST', $url, $_POST)->getContent();
        $response = json_decode($response);
        return $response;
    }

    private function makeRequest($id, $post_data, $authData = null)
    {
        if ($authData === null) {
            $authData = $this->authData;
        }
        $_GET = [
            'apikey' => $authData->api_key,
            'apitimestamp' => time(),
        ];
        $_POST = array_merge(['merchant_id' => $id], $post_data);
        $url = '/api/v1/mall/update?' . http_build_query($_GET);
        $secretKey = $authData->api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $response = $this->call('POST', $url, $_POST)->getContent();
        $response = json_decode($response);
        return $response;
    }

    public function testCanUpdate()
    {

    }

    public function testAclUpdateMall()
    {
        $mall = $this->createMall();
        $user = $mall->user;
        $role = $user->role;
        $authData = Factory::create('Apikey', ['user_id' => $user->user_id]);
        $permission = Factory::create('Permission', ['permission_name' => 'update_mall']);

        // no permission
        $count_before = Mall::excludeDeleted()->count();

        $response = $this->makeRequest($mall->merchant_id, $this->makeRequestData($mall), $authData);
        $this->assertSame('error', $response->status);
        $this->assertRegExp('/not have permission/i', $response->message);
        $this->assertResponseStatus(200);

        $count_after = Mall::excludeDeleted()->count();
        $this->assertSame($count_before, $count_after);

        // with permission
        Factory::create('PermissionRole', ['role_id' => $role->role_id, 'permission_id' => $permission->permission_id]);
        // must do this to clear the cached user which has no permission in its role
        \OrbitShop\API\v1\OrbitShopAPI::clearLookupCache($authData->api_key);
        $count_before = Mall::excludeDeleted()->count();

        $response = $this->makeRequest($mall->merchant_id, $this->makeRequestData($mall), $authData);
        $this->assertSame('Request OK', $response->message);
        $this->assertSame('success', $response->status);
        $this->assertResponseStatus(200);

        $count_after = Mall::excludeDeleted()->count();
        $this->assertSame($count_before, $count_after);
    }

    public function testCanUpdateFields()
    {
        $mall = $this->createMall();
        $country = Factory::create('Country');

        $faker = Faker::create();
        $data['country'] = $country->country_id;
        $data['email'] = $faker->safeEmail;
        $data['name'] = $faker->company;
        $data['password'] = $faker->bothify('???###???');
        $data['address_line1'] = $faker->streetAddress;
        $data['address_line2'] = $faker->city;
        $data['address_line3'] = $faker->state;
        $data['city'] = $faker->city;
        // todo fix when postal code turns into string
        $data['postal_code'] = $faker->numberBetween(1000, 4000);
        $data['description'] = $faker->sentence();
        $data['phone'] = $faker->phoneNumber;
        $data['fax'] = $faker->phoneNumber;
        $data['start_date_activity'] = $faker->dateTimeBetween('-10 days', '-2 days')->format('Y-m-d H:i:s');
        $data['end_date_activity'] = $faker->dateTimeBetween('+2 days', '+10 days')->format('Y-m-d H:i:s');
        $data['currency'] = 'USD';
        $data['currency_symbol'] = '$';
        $data['tax_code1'] = $faker->bothify('???###???');
        $data['tax_code2'] = $faker->bothify('???###???');
        $data['tax_code3'] = $faker->bothify('???###???');
        $data['slogan'] = $faker->sentence();
        $data['vat_included'] = $faker->randomElement(['yes', 'no']);
        $data['contact_person_firstname'] = $faker->firstName;
        $data['contact_person_lastname'] = $faker->lastName;
        $data['contact_person_position'] = $faker->sentence(2);
        $data['contact_person_phone'] = $faker->phoneNumber;
        $data['contact_person_phone2'] = $faker->phoneNumber;
        $data['contact_person_email'] = $faker->safeEmail;
        $data['sector_of_activity'] = $faker->sentence(3);
        $data['url'] = str_replace('http://', '', str_replace('https://', '', $faker->url));
        $data['masterbox_number'] = $faker->bothify('???########');
        $data['slavebox_number'] = $faker->bothify('???########');
        $data['mobile_default_language'] = $faker->languageCode;
        $data['pos_language'] = $faker->languageCode;

        $count_before = Mall::excludeDeleted()->count();

        $response = $this->makeRequest($mall->merchant_id, $data);
        $this->assertSame('success', $response->status);
        $this->assertSame('Request OK', $response->message);
        $this->assertResponseStatus(200);

        $count_after = Mall::excludeDeleted()->count();
        $this->assertSame($count_before, $count_after);

        $db_mall = Mall::find($mall->merchant_id);

        $fields = [
            'name',
            'description',
            'address_line1',
            'address_line2',
            'address_line3',
            'city',
            'postal_code',
            'phone',
            'fax',
            'currency',
            'currency_symbol',
            'tax_code1',
            'tax_code2',
            'tax_code3',
            'slogan',
            'vat_included',
            'contact_person_firstname',
            'contact_person_lastname',
            'contact_person_position',
            'contact_person_phone',
            'contact_person_phone2',
            'contact_person_email',
            'sector_of_activity',
            'url',
            'masterbox_number',
            'slavebox_number',
            'mobile_default_language',
            'pos_language',
        ];

        foreach ($fields as $field) {
            $this->assertSame((string)$data[$field], (string)$db_mall->{$field}, 'Field ' . $field . ' must match');
        }

        $datetime_fields = [
            'start_date_activity',
            'end_date_activity',
        ];

        foreach ($datetime_fields as $field) {
            $this->assertSame($data[$field], $db_mall->{$field});
        }

        $this->assertSame((string)$country->country_id, (string)$db_mall->country_id);
        $this->assertSame($country->name, $db_mall->country);

    }

    public function testUpdateDuplicateEmail()
    {
        $mall_1 = $this->createMall();
        $mall_2 = $this->createMall();

        // update to other group's email
        $count_before = Mall::excludeDeleted()->count();

        $response = $this->makeRequest($mall_2->merchant_id, ['email' => $mall_1->email]);
        $this->assertSame('error', $response->status);
        $this->assertRegExp('/Email.*taken/i', $response->message);
        $this->assertResponseStatus(403);

        $count_after = Mall::excludeDeleted()->count();
        $this->assertSame($count_before, $count_after);

        // update to same email
        $count_before = Mall::excludeDeleted()->count();

        $response = $this->makeRequest($mall_2->merchant_id, ['email' => $mall_2->email]);
        $this->assertSame('success', $response->status);
        $this->assertSame('Request OK', $response->message);
        $this->assertResponseStatus(200);

        $count_after = Mall::excludeDeleted()->count();
        $this->assertSame($count_before, $count_after);
    }

    public function testUpdateDuplicateOmid()
    {
        $mall_1 = $this->createMall();
        $mall_2 = $this->createMall();

        // update to other group's email
        $count_before = Mall::excludeDeleted()->count();

        $response = $this->makeRequest($mall_2->merchant_id, ['omid' => $mall_1->omid]);
        $this->assertSame('error', $response->status);
        $this->assertRegExp('/OMID.*taken/i', $response->message);
        $this->assertResponseStatus(403);

        $count_after = Mall::excludeDeleted()->count();
        $this->assertSame($count_before, $count_after);

        // update to same email
        $count_before = Mall::excludeDeleted()->count();

        $response = $this->makeRequest($mall_2->merchant_id, ['omid' => $mall_2->omid]);
        $this->assertSame('success', $response->status);
        $this->assertSame('Request OK', $response->message);
        $this->assertResponseStatus(200);

        $count_after = Mall::excludeDeleted()->count();
        $this->assertSame($count_before, $count_after);
    }

    public function testUpdateWithBadStatus()
    {
        $mall = $this->createMall();

        // no status
        $count_before = Mall::excludeDeleted()->count();

        $response = $this->makeRequest($mall->merchant_id, []);
        $this->assertSame('success', $response->status);
        $this->assertSame('Request OK', $response->message);
        $this->assertResponseStatus(200);

        $count_after = Mall::excludeDeleted()->count();
        $this->assertSame($count_before, $count_after);

        // bad status
        $response = $this->makeRequest($mall->merchant_id, ['status' => 'permaclosed']);
        $this->assertSame('error', $response->status);
        $this->assertRegExp('/status/i', $response->message);
        $this->assertResponseStatus(403);

        $count_after = Mall::excludeDeleted()->count();
        $this->assertSame($count_before, $count_after);
    }

    public function testUpdateWithBadUrl()
    {
        $mall = $this->createMall();
        $count_before = Mall::excludeDeleted()->count();

        $response = $this->makeRequest($mall->merchant_id, ['url' => 'not-a-url']);
        $this->assertSame('error', $response->status);
        $this->assertRegExp('/url.*not valid/i', $response->message);
        $this->assertResponseStatus(403);

        $count_after = Mall::excludeDeleted()->count();
        $this->assertSame($count_before, $count_after);
    }

    public function testUpdateEmptyPosLanguage()
    {
        $mall = $this->createMall();
        $mall->pos_language = 'en';
        $mall->save();
        $count_before = Mall::excludeDeleted()->count();

        // empty
        $response = $this->makeRequest($mall->merchant_id, ['pos_language' => '']);
        $this->assertSame('success', $response->status);
        $this->assertSame('Request OK', $response->message);
        $this->assertResponseStatus(200);

        $mall_db = Mall::find($mall->merchant_id);
        $this->assertNull($mall_db->pos_language);

        // spaces
        $response = $this->makeRequest($mall->merchant_id, ['pos_language' => ' ']);
        $this->assertSame('success', $response->status);
        $this->assertSame('Request OK', $response->message);
        $this->assertResponseStatus(200);

        $mall_db = Mall::find($mall->merchant_id);
        $this->assertNull($mall_db->pos_language);

        $count_after = Mall::excludeDeleted()->count();
        $this->assertSame($count_before, $count_after);
    }
}
