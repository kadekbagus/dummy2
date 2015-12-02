<?php
/**
 * Unit test for API /api/v1/mall/delete
 */
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;
use Faker\Factory as Faker;

class postDeleteMall extends TestCase
{

    private $authData;
    private $country;
    private $password;

    public function setUp()
    {
        parent::setUp();

        $this->authData = Factory::create('apikey_super_admin');
        $this->authData->user->user_password = Hash::make($this->password = 'password');
        $this->authData->user->save();
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

    private function makeRequest($merchant_id, $password, $authData = null)
    {
        if ($authData === null) {
            $authData = $this->authData;
        }
        $_GET = [
            'apikey' => $authData->api_key,
            'apitimestamp' => time(),
        ];
        $_POST = [];
        if (isset($merchant_id)) {
            $_POST['merchant_id'] = $merchant_id;
        }
        if (isset($password)) {
            $_POST['password'] = $password;
        }
        $url = '/api/v1/mall/delete?' . http_build_query($_GET);
        $secretKey = $authData->api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $response = $this->call('POST', $url, $_POST)->getContent();
        $response = json_decode($response);
        return $response;
    }

    public function testAclDeleteMall()
    {
        $mall = $this->createMall();
        $user = $mall->user;
        $user->user_password = Hash::make('password');
        $user->save();
        $role = $user->role;
        $authData = Factory::create('Apikey', ['user_id' => $user->user_id]);
        $permission = Factory::create('Permission', ['permission_name' => 'delete_mall']);

        // no permission
        $count_before = Mall::excludeDeleted()->count();

        $response = $this->makeRequest($mall->merchant_id, 'password', $authData);
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

        $response = $this->makeRequest($mall->merchant_id, 'password', $authData);
        $this->assertRegExp('/mall .*deleted/i', $response->message);
        $this->assertSame('success', $response->status);
        $this->assertResponseStatus(200);

        $count_after = Mall::excludeDeleted()->count();
        $this->assertSame($count_before - 1, $count_after);
    }

    public function testCanDelete()
    {
        $mall = $this->createMall();
        $mall_user = $mall->user;
        $mall_apikey = $mall->user->apikey;

        $count_before = Mall::excludeDeleted()->count();

        $response = $this->makeRequest($mall->merchant_id, $this->password);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/mall .*deleted/i', $response->message);
        $this->assertResponseStatus(200);

        $count_after = Mall::excludeDeleted()->count();
        $this->assertSame($count_before - 1, $count_after);
        $db_user = User::find($mall_user->user_id);
        $this->assertSame('deleted', $db_user->status);
        $db_apikey = Apikey::find($mall_apikey->apikey_id);
        $this->assertSame('deleted', $db_apikey->status);
    }

    public function testDeleteWithBadPassword()
    {
        $mall = $this->createMall();
        $count_before = Mall::excludeDeleted()->count();

        $response = $this->makeRequest($mall->merchant_id, 'wrong-password');
        $this->assertSame('error', $response->status);
        $this->assertRegExp('/password.*incorrect/i', $response->message);
        $this->assertResponseStatus(403);

        $count_after = Mall::excludeDeleted()->count();
        $this->assertSame($count_before, $count_after);
    }

    public function testDeleteWithNoPassword()
    {
        $mall = $this->createMall();
        $count_before = Mall::excludeDeleted()->count();

        $response = $this->makeRequest($mall->merchant_id, null);
        $this->assertSame('error', $response->status);
        $this->assertRegExp('/password.*required/i', $response->message);
        $this->assertResponseStatus(403);

        $count_after = Mall::excludeDeleted()->count();
        $this->assertSame($count_before, $count_after);
    }

    public function testDeleteOtherUserMall()
    {
        $mall_1 = $this->createMall();
        $mall_2 = $this->createMall();
        $user = $mall_2->user;
        $user->user_password = Hash::make('password');
        $user->save();
        $role = $user->role;
        $authData = Factory::create('Apikey', ['user_id' => $user->user_id]);
        $permission = Factory::create('Permission', ['permission_name' => 'delete_mall']);
        Factory::create('PermissionRole', ['role_id' => $role->role_id, 'permission_id' => $permission->permission_id]);

        $count_before = Mall::excludeDeleted()->count();

        $response = $this->makeRequest($mall_1->merchant_id, 'password', $authData);
        $this->assertSame('error', $response->status);
        $this->assertRegExp('/mall id.*not found/i', $response->message);
        $this->assertResponseStatus(403);

        $count_after = Mall::excludeDeleted()->count();
        $this->assertSame($count_before, $count_after);
    }

    // public function testDeleteWithExistingMall()
    // {
    //     $mall = $this->createMall();
    //     Factory::create('Mall', ['parent_id' => $mall->merchant_id]);
    //     $count_before = Mall::excludeDeleted()->count();

    //     $response = $this->makeRequest($mall->merchant_id, $this->password);
    //     $this->assertSame('error', $response->status);
    //     $this->assertRegExp('/mall linked.*cannot be deleted/i', $response->message);
    //     $this->assertResponseStatus(403);

    //     $count_after = Mall::excludeDeleted()->count();
    //     $this->assertSame($count_before, $count_after);
    // }

}
