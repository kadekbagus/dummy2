<?php
/**
 * Unit test for API /api/v1/mallgroup/new
 */
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;
use Faker\Factory as Faker;

class postNewMallGroup extends TestCase
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

    private function makeRequestData()
    {
        return [
            'email' => 'test@example.com',
            'name' => 'Dummy Name',
            'status' => 'active',
            'country' => $this->country->country_id
        ];
    }

    private function makeRequest($post_data, $authData = null)
    {
        if ($authData === null) {
            $authData = $this->authData;
        }
        $_GET = [
            'apikey' => $authData->api_key,
            'apitimestamp' => time(),
        ];
        $_POST = $post_data;
        $url = '/api/v1/mallgroup/new?' . http_build_query($_GET);
        $secretKey = $authData->api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $response = $this->call('POST', $url, $_POST)->getContent();
        $response = json_decode($response);
        return $response;
    }

    public function testCanCreateNewMallGroup()
    {
        $count_before = MallGroup::excludeDeleted()->count();

        $response = $this->makeRequest($this->makeRequestData());
        $this->assertSame('success', $response->status);
        $this->assertSame('Request OK', $response->message);
        $this->assertResponseStatus(200);

        $count_after = MallGroup::excludeDeleted()->count();
        $this->assertSame($count_before + 1, $count_after);
    }

    public function testAclMallGroup()
    {
        // no permission
        $authData = Factory::create('Apikey');
        $count_before = MallGroup::excludeDeleted()->count();

        $response = $this->makeRequest($this->makeRequestData(), $authData);
        $this->assertSame('error', $response->status);
        $this->assertRegExp('/not have permission/i', $response->message);
        $this->assertResponseStatus(403);

        $count_after = MallGroup::excludeDeleted()->count();
        $this->assertSame($count_before, $count_after);

        // with permission
        $authData = Factory::create('Apikey');
        $role = $authData->user->role;
        $permission = Factory::create('Permission', ['permission_name' => 'create_mall_group']);
        Factory::create('PermissionRole', ['role_id' => $role->role_id, 'permission_id' => $permission->permission_id]);
        $count_before = MallGroup::excludeDeleted()->count();

        $response = $this->makeRequest($this->makeRequestData(), $authData);
        $this->assertSame('success', $response->status);
        $this->assertSame('Request OK', $response->message);
        $this->assertResponseStatus(200);

        $count_after = MallGroup::excludeDeleted()->count();
        $this->assertSame($count_before + 1, $count_after);
    }

    public function testFieldsStored()
    {
        $data = $this->makeRequestData();
        $faker = Faker::create();
        $data['name'] = $faker->company;
        $data['password'] = $faker->bothify('???###???');
        $data['address_line1'] = $faker->streetAddress;
        $data['address_line2'] = $faker->city;
        $data['address_line3'] = $faker->state;
        $data['city'] = $faker->city;
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

        $count_before = MallGroup::excludeDeleted()->count();

        $response = $this->makeRequest($data);
        $this->assertSame('success', $response->status);
        $this->assertSame('Request OK', $response->message);
        $this->assertResponseStatus(200);

        $count_after = MallGroup::excludeDeleted()->count();
        $this->assertSame($count_before + 1, $count_after);

        $db_mall_group = MallGroup::find($response->data->merchant_id);

        $fields = [
            'name',
            'description',
            'address_line1',
            'address_line2',
            'address_line3',
            'city',
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
            $this->assertSame($data[$field], $db_mall_group->{$field}, 'Field ' . $field . ' must match');
        }

        $datetime_fields = [
            'start_date_activity',
            'end_date_activity',
        ];

        foreach ($datetime_fields as $field) {
            $this->assertSame($data[$field], $db_mall_group->{$field});
        }

        $this->assertSame((string)$this->country->country_id, (string)$db_mall_group->country_id);
        $this->assertSame($this->country->name, $db_mall_group->country);

        // check the created user
        $user = $db_mall_group->user;
        Hash::check($data['password'], $user->user_password);
        $this->assertSame($data['email'], $user->user_email);
        $this->assertSame($data['email'], $user->username);
        $this->assertSame($data['status'], $user->status);
    }

    public function testAddDuplicateEmail()
    {
        $data = $this->makeRequestData();
        $count_before = MallGroup::excludeDeleted()->count();

        $response = $this->makeRequest($data);
        $this->assertSame('success', $response->status);
        $this->assertSame('Request OK', $response->message);
        $this->assertResponseStatus(200);

        $count_after = MallGroup::excludeDeleted()->count();
        $this->assertSame($count_before + 1, $count_after);

        // try again (same email)
        $count_before = MallGroup::excludeDeleted()->count();

        $response = $this->makeRequest($data);
        $this->assertSame('error', $response->status);
        $this->assertRegExp('/email.*taken/i', $response->message);
        $this->assertResponseStatus(403);

        $count_after = MallGroup::excludeDeleted()->count();
        $this->assertSame($count_before, $count_after);
    }

    public function testAddWithBadStatus()
    {
        // no status
        $count_before = MallGroup::excludeDeleted()->count();

        $data = $this->makeRequestData();
        unset($data['status']);
        $response = $this->makeRequest($data);
        $this->assertSame('error', $response->status);
        $this->assertRegExp('/status.*required/i', $response->message);
        $this->assertResponseStatus(403);

        $count_after = MallGroup::excludeDeleted()->count();
        $this->assertSame($count_before, $count_after);

        // bad status
        $data['status'] = 'permaclosed';
        $response = $this->makeRequest($data);
        $this->assertSame('error', $response->status);
        $this->assertRegExp('/status/i', $response->message);
        $this->assertResponseStatus(403);

        $count_after = MallGroup::excludeDeleted()->count();
        $this->assertSame($count_before, $count_after);
    }

    public function testAddWithBadUrl()
    {
        $count_before = MallGroup::excludeDeleted()->count();

        $data = $this->makeRequestData();
        $data['url'] = 'not-a-url';
        $response = $this->makeRequest($data);
        $this->assertSame('error', $response->status);
        $this->assertRegExp('/url.*not valid/i', $response->message);
        $this->assertResponseStatus(403);

        $count_after = MallGroup::excludeDeleted()->count();
        $this->assertSame($count_before, $count_after);
    }
}
