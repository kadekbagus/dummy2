<?php
/**
 * Test for API /api/v1/mallgroup/search
 */
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;

class getSearchMallGroupTest extends TestCase
{

    private $authData;

    public function setUp()
    {
        parent::setUp();

        DB::beginTransaction();

        $this->authData = Factory::create('apikey_super_admin');
    }

    private function makeRequest($data, $authData = null)
    {
        if ($authData === null) {
            $authData = $this->authData;
        }
        $_GET = array_merge($data, [
            'apikey' => $authData->api_key,
            'apitimestamp' => time(),
        ]);
        $_POST = [];
        $url = '/api/v1/mallgroup/search?' . http_build_query($_GET);
        $secretKey = $authData->api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $response = $this->call('GET', $url, $_POST)->getContent();
        $response = json_decode($response);
        return $response;
    }

    public function tearDown()
    {
        $this->useTruncate = false;

        parent::tearDown();
    }

    public function testSearchAcl()
    {
        $authData = Factory::create('Apikey');
        $permission = Factory::create('Permission', ['permission_name' => 'view_mall']);
        $mall_group = Factory::create('MallGroup', ['user_id' => $authData->user_id]);

        $response = $this->makeRequest([], $authData);
        $this->assertRegExp('/do not have permission/i', $response->message);
        $this->assertSame('error', $response->status);
        $this->assertResponseStatus(403);

        Factory::create('PermissionRole', ['role_id' => $authData->user->role->role_id, 'permission_id' => $permission->permission_id]);
        // must do this to clear the cached user which has no permission in its role
        \OrbitShop\API\v1\OrbitShopAPI::clearLookupCache($authData->api_key);
        $response = $this->makeRequest([], $authData);
        $this->assertSame('Request OK', $response->message);
        $this->assertSame('success', $response->status);
        $this->assertResponseStatus(200);
    }

    public function testSearchReturnsFields()
    {
        $faker = Faker\Factory::create();

        $data['name'] = $faker->company;
        $data['address_line1'] = $faker->streetAddress;
        $data['address_line2'] = $faker->city;
        $data['address_line3'] = $faker->state;
        // todo fix when postal code turns into string
        $data['postal_code'] = $faker->numberBetween(1000, 4000);
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

        $mall_group = Factory::create('MallGroup', $data);
        $response = $this->makeRequest([]);
        $this->assertSame('Request OK', $response->message);
        $this->assertSame('success', $response->status);
        $this->assertResponseStatus(200);
        $this->assertSame(1, $response->data->total_records);
        $this->assertSame(1, $response->data->returned_records);
        $returned_mall_group = $response->data->records[0];
        $this->assertSame((string)$mall_group->merchant_id, (string)$returned_mall_group->merchant_id);
        foreach (array_keys($data) as $field) {
            $this->assertSame((string)$data[$field], (string)$returned_mall_group->{$field});
        }
    }


    public function testSorting()
    {
        $sortable_fields = [
            'merchant_omid',
            'registered_date',
            'merchant_name',
            'merchant_email',
            'merchant_userid',
            'merchant_description',
            'merchantid',
            'merchant_address1',
            'merchant_address2',
            'merchant_address3',
            'merchant_cityid',
            'merchant_city',
            'merchant_countryid',
            'merchant_country',
            'merchant_phone',
            'merchant_fax',
            'merchant_status',
            'merchant_currency',
            'start_date_activity',
            'total_mall',
        ];

        $faker = Faker\Factory::create();
        $user_1 = Factory::create('User');
        $user_2 = Factory::create('User');
        $country_1 = Factory::create('Country');
        $country_2 = Factory::create('Country');
        $mall_group_lower = Factory::create('MallGroup', [
            'omid' => 100,
            'created_at' => $faker->dateTimeBetween('-20 days', '-10 days')->format('Y-m-d H:i:s'),
            'name' => '111',
            'email' => '111@example.com',
            'user_id' => min($user_1->user_id, $user_2->user_id),
            'description' => '111',
            'address_line1' => '111',
            'address_line2' => '111',
            'address_line3' => '111',
            'city_id' => '111',
            'city' => 'City 111',
            'country_id' => min($country_1->country_id, $country_2->country_id),
            'country' => min($country_1->name, $country_2->name),
            'phone' => '111',
            'fax' => '111',
            'status' => 'active',
            'currency' => 'EUR',
            'start_date_activity' => $faker->dateTimeBetween('-20 days', '-10 days')->format('Y-m-d H:i:s'),
        ]);

        $mall_group_higher = Factory::create('MallGroup', [
            'omid' => 900,
            'created_at' => $faker->dateTimeBetween('-2 days', '-1 days')->format('Y-m-d H:i:s'),
            'name' => '999',
            'email' => '999@example.com',
            'user_id' => max($user_1->user_id, $user_2->user_id),
            'description' => '999',
            'address_line1' => '999',
            'address_line2' => '999',
            'address_line3' => '999',
            'city_id' => '999',
            'city' => 'City 999',
            'country_id' => max($country_1->country_id, $country_2->country_id),
            'country' => max($country_1->name, $country_2->name),
            'phone' => '999',
            'fax' => '999',
            'status' => 'inactive',
            'currency' => 'USD',
            'start_date_activity' => $faker->dateTimeBetween('-2 days', '-1 days')->format('Y-m-d H:i:s'),
        ]);
        Factory::create('Mall', ['parent_id' => $mall_group_higher->merchant_id]); // for total_mall sorting

        foreach ($sortable_fields as $field) {
            foreach (['asc', 'desc'] as $direction) {
                $response = $this->makeRequest(['sortby' => $field, 'sortmode' => $direction]);
                $this->assertSame('Request OK', $response->message);
                $this->assertSame('success', $response->status);
                $this->assertResponseStatus(200);
                $this->assertSame(2, $response->data->total_records);
                $this->assertSame(2, $response->data->returned_records);
                $first_returned_mall_group = $response->data->records[0];
                $second_returned_mall_group = $response->data->records[1];
                if ($direction == 'asc') {
                    $this->assertSame((string)$mall_group_lower->merchant_id, (string)$first_returned_mall_group->merchant_id);
                    $this->assertSame((string)$mall_group_higher->merchant_id, (string)$second_returned_mall_group->merchant_id);
                }
                else {
                    $this->assertSame((string)$mall_group_higher->merchant_id, (string)$first_returned_mall_group->merchant_id);
                    $this->assertSame((string)$mall_group_lower->merchant_id, (string)$second_returned_mall_group->merchant_id);
                }
            }
        }
    }

    public function testExactSetFiltering()
    {
        $faker = Faker\Factory::create();
        $user_1 = Factory::create('User');
        $user_2 = Factory::create('User');
        $country_1 = Factory::create('Country');
        $country_2 = Factory::create('Country');

        $mall_group_lower = Factory::create('MallGroup', $data_lower = [
            'omid' => 100,
            'created_at' => $faker->dateTimeBetween('-20 days', '-10 days')->format('Y-m-d H:i:s'),
            'name' => '111',
            'email' => '111@example.com',
            'user_id' => min($user_1->user_id, $user_2->user_id),
            'description' => '111',
            'address_line1' => '111',
            'address_line2' => '111',
            'address_line3' => '111',
            'postal_code' => 111,
            'city_id' => '111',
            'city' => 'City 111',
            'country_id' => min($country_1->country_id, $country_2->country_id),
            'country' => min($country_1->name, $country_2->name),
            'phone' => '111',
            'fax' => '111',
            'status' => 'active',
            'currency' => 'EUR',
            'contact_person_firstname' => '111',
            'contact_person_lastname' => '111',
            'contact_person_position' => '111',
            'contact_person_phone' => '111111',
            'contact_person_phone2' => '111222',
            'contact_person_email' => '111@example.com',
            'sector_of_activity' => '111',
            'url' => '111.example.com/111',
            'masterbox_number' => '1111',
            'slavebox_number' => '1112',
            'mobile_default_language' => 'en',
            'pos_language' => 'en',
            'start_date_activity' => $faker->dateTimeBetween('-20 days', '-10 days')->format('Y-m-d H:i:s'),
        ]);

        $mall_group_higher = Factory::create('MallGroup', $data_higher = [
            'omid' => 900,
            'created_at' => $faker->dateTimeBetween('-2 days', '-1 days')->format('Y-m-d H:i:s'),
            'name' => '999',
            'email' => '999@example.com',
            'user_id' => max($user_1->user_id, $user_2->user_id),
            'description' => '999',
            'address_line1' => '999',
            'address_line2' => '999',
            'address_line3' => '999',
            'postal_code' => 999,
            'city_id' => '999',
            'city' => 'City 999',
            'country_id' => max($country_1->country_id, $country_2->country_id),
            'country' => max($country_1->name, $country_2->name),
            'phone' => '999',
            'fax' => '999',
            'status' => 'inactive',
            'currency' => 'USD',
            'contact_person_firstname' => '999',
            'contact_person_lastname' => '999',
            'contact_person_position' => '999',
            'contact_person_phone' => '999111',
            'contact_person_phone2' => '999222',
            'contact_person_email' => '999@example.com',
            'sector_of_activity' => '999',
            'url' => '999.example.com/999',
            'masterbox_number' => '9991',
            'slavebox_number' => '9992',
            'mobile_default_language' => 'ja',
            'pos_language' => 'ja',
            'start_date_activity' => $faker->dateTimeBetween('-2 days', '-1 days')->format('Y-m-d H:i:s'),
        ]);

        $exact_filters = [
            'merchant_id',
            'omid',
            'user_id',
            'name',
            'description',
            'email',
            'address1',
            'address2',
            'address3',
            'postal_code',
            'city_id',
            'city',
            'country_id',
            'country',
            'phone',
            'fax',
            'status',
            'currency',
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
        $filter_to_field = [
            'address1' => 'address_line1',
            'address2' => 'address_line2',
            'address3' => 'address_line3',
        ];
        $data_lower['merchant_id'] = $mall_group_lower->merchant_id;
        $data_higher['merchant_id'] = $mall_group_higher->merchant_id;

        foreach ($exact_filters as $filter) {
            $field = $filter;
            if (isset($filter_to_field[$filter])) {
                $field = $filter_to_field[$filter];
            }

            $filters = [$filter => [$data_lower[$field]]];
            $response = $this->makeRequest($filters);
            $this->assertSame('Request OK', $response->message);
            $this->assertSame('success', $response->status);
            $this->assertResponseStatus(200);
            $this->assertSame(1, $response->data->total_records, sprintf('Must return 1 record for filter %s value %s', $filter, $data_lower[$field]));
            $this->assertSame(1, $response->data->returned_records);
            $returned_mall_group = $response->data->records[0];
            $this->assertSame((string)$mall_group_lower->merchant_id, (string)$returned_mall_group->merchant_id);

            $filters = [$filter => [$data_higher[$field]]];
            $response = $this->makeRequest($filters);
            $this->assertSame('Request OK', $response->message);
            $this->assertSame('success', $response->status);
            $this->assertResponseStatus(200);
            $this->assertSame(1, $response->data->total_records);
            $this->assertSame(1, $response->data->returned_records);
            $returned_mall_group = $response->data->records[0];
            $this->assertSame((string)$mall_group_higher->merchant_id, (string)$returned_mall_group->merchant_id);

            $filters = [$filter => [$data_lower[$field], $data_higher[$field]], 'sortby' => 'merchantid', 'sortmode' => 'asc'];
            $response = $this->makeRequest($filters);
            $this->assertSame('Request OK', $response->message);
            $this->assertSame('success', $response->status);
            $this->assertResponseStatus(200);
            $this->assertSame(2, $response->data->total_records);
            $this->assertSame(2, $response->data->returned_records);
            $first_returned_mall_group = $response->data->records[0];
            $second_returned_mall_group = $response->data->records[1];
            $this->assertSame((string)$mall_group_lower->merchant_id, (string)$first_returned_mall_group->merchant_id);
            $this->assertSame((string)$mall_group_higher->merchant_id, (string)$second_returned_mall_group->merchant_id);
        }
    }

    public function testWildcardFiltering()
    {
        $faker = Faker\Factory::create();
        $user_1 = Factory::create('User');
        $user_2 = Factory::create('User');
        $country_1 = Factory::create('Country', ['name' => '1115']);
        $country_2 = Factory::create('Country', ['name' => '9995']);

        $mall_group_lower = Factory::create('MallGroup', $data_lower = [
            'omid' => 100,
            'created_at' => $faker->dateTimeBetween('-20 days', '-10 days')->format('Y-m-d H:i:s'),
            'name' => '1115',
            'email' => '1115@example.com',
            'user_id' => min($user_1->user_id, $user_2->user_id),
            'description' => '1115',
            'address_line1' => '1115',
            'address_line2' => '1115',
            'address_line3' => '1115',
            'postal_code' => 111,
            'city_id' => '111',
            'city' => 'City 1115',
            'country_id' => min($country_1->country_id, $country_2->country_id),
            'country' => min($country_1->name, $country_2->name),
            'phone' => '111',
            'fax' => '111',
            'status' => 'active',
            'currency' => 'EUR',
            'contact_person_firstname' => '1115',
            'contact_person_lastname' => '1115',
            'contact_person_position' => '1115',
            'contact_person_phone' => '111111',
            'contact_person_phone2' => '111222',
            'contact_person_email' => '111@example.com',
            'sector_of_activity' => '111',
            'url' => '111.example.com/111',
            'masterbox_number' => '1111',
            'slavebox_number' => '1112',
            'mobile_default_language' => 'en',
            'pos_language' => 'en',
            'start_date_activity' => $faker->dateTimeBetween('-20 days', '-10 days')->format('Y-m-d H:i:s'),
        ]);

        $mall_group_higher = Factory::create('MallGroup', $data_higher = [
            'omid' => 900,
            'created_at' => $faker->dateTimeBetween('-2 days', '-1 days')->format('Y-m-d H:i:s'),
            'name' => '9995',
            'email' => '9995@example.com',
            'user_id' => max($user_1->user_id, $user_2->user_id),
            'description' => '9995',
            'address_line1' => '9995',
            'address_line2' => '9995',
            'address_line3' => '9995',
            'postal_code' => 999,
            'city_id' => '999',
            'city' => 'City 9995',
            'country_id' => max($country_1->country_id, $country_2->country_id),
            'country' => max($country_1->name, $country_2->name),
            'phone' => '999',
            'fax' => '999',
            'status' => 'inactive',
            'currency' => 'USD',
            'contact_person_firstname' => '9995',
            'contact_person_lastname' => '9995',
            'contact_person_position' => '9995',
            'contact_person_phone' => '999111',
            'contact_person_phone2' => '999222',
            'contact_person_email' => '999@example.com',
            'sector_of_activity' => '999',
            'url' => '999.example.com/999',
            'masterbox_number' => '9991',
            'slavebox_number' => '9992',
            'mobile_default_language' => 'ja',
            'pos_language' => 'ja',
            'start_date_activity' => $faker->dateTimeBetween('-2 days', '-1 days')->format('Y-m-d H:i:s'),
        ]);

        $wildcard_filters = [
            'name_like',
            'description_like',
            'email_like',
            'address1_like',
            'address2_like',
            'address3_like',
            'city_like',
            'country_like',
            'contact_person_firstname_like',
            'contact_person_lastname_like',
            'contact_person_position_like',
        ];
        $filter_to_field = [
            'address1' => 'address_line1',
            'address2' => 'address_line2',
            'address3' => 'address_line3',
        ];
        $data_lower['merchant_id'] = $mall_group_lower->merchant_id;
        $data_higher['merchant_id'] = $mall_group_higher->merchant_id;

        foreach ($wildcard_filters as $filter) {
            $field = str_replace('_like', '', $filter);
            if (isset($filter_to_field[$field])) {
                $field = $filter_to_field[$field];
            }

            $filters = [$filter => '1'];
            $response = $this->makeRequest($filters);
            $this->assertSame('Request OK', $response->message);
            $this->assertSame('success', $response->status);
            $this->assertResponseStatus(200);
            $this->assertSame(1, $response->data->total_records, sprintf('Must return 1 record for filter %s value %s', $filter, $data_lower[$field]));
            $this->assertSame(1, $response->data->returned_records);
            $returned_mall_group = $response->data->records[0];
            $this->assertSame((string)$mall_group_lower->merchant_id, (string)$returned_mall_group->merchant_id);

            $filters = [$filter => '9'];
            $response = $this->makeRequest($filters);
            $this->assertSame('Request OK', $response->message);
            $this->assertSame('success', $response->status);
            $this->assertResponseStatus(200);
            $this->assertSame(1, $response->data->total_records);
            $this->assertSame(1, $response->data->returned_records);
            $returned_mall_group = $response->data->records[0];
            $this->assertSame((string)$mall_group_higher->merchant_id, (string)$returned_mall_group->merchant_id);

            $filters = [$filter => '5', 'sortby' => 'merchantid', 'sortmode' => 'asc'];
            $response = $this->makeRequest($filters);
            $this->assertSame('Request OK', $response->message);
            $this->assertSame('success', $response->status);
            $this->assertResponseStatus(200);
            $this->assertSame(2, $response->data->total_records);
            $this->assertSame(2, $response->data->returned_records);
            $first_returned_mall_group = $response->data->records[0];
            $second_returned_mall_group = $response->data->records[1];
            $this->assertSame((string)$mall_group_lower->merchant_id, (string)$first_returned_mall_group->merchant_id);
            $this->assertSame((string)$mall_group_higher->merchant_id, (string)$second_returned_mall_group->merchant_id);
        }
    }


}
