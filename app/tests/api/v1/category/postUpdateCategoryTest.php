<?php
/**
 * PHP Unit Test for Category Controller postUpdateCategory
 *
 * @author: Yudi Rahono <yudi.rahono@dominopos.com>
 */
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;

class postUpdateCategoryTest extends TestCase
{
    private $baseUrl = '/api/v1/family/update/';

    public function setUp()
    {
        parent::setUp();

        $this->authData = Factory::create('Apikey', ['user_id' => 'factory:user_super_admin']);
        $this->category = Factory::create('Category');
    }

    public function testError_update_non_owned_category()
    {
        $role = Factory::create('role_admin');
        $permission = Factory::create('Permission', ['permission_name' => 'update_category']);
        $user = Factory::create('User', ['user_role_id' => $role->role_id]);
        $authData = Factory::create('Apikey', ['user_id' => $user->user_id]);
        $category = Factory::create('Category', ['created_by' => $user->user_id, 'category_name' => 'Should Not Updated']);

        Factory::create('PermissionRole', ['permission_id' => $permission->permission_id, 'role_id' => $role->role_id]);

        $_GET['apikey']       = $authData->api_key;
        $_GET['apitimestamp'] = time();

        $_POST['category_id']    = $category->category_id;
        $_POST['category_name']  = 'Unique Submitted';
        $_POST['category_level'] = '1';
        $_POST['status']         = 'active';

        $url = $this->baseUrl . '?' . http_build_query($_GET);

        $secretKey = $authData->api_secret_key;
        $_SERVER['REQUEST_METHOD']         = 'POST';
        $_SERVER['REQUEST_URI']            = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('POST', $url, $_POST)->getContent();
        $response = json_decode($response);

        // Should be failed
        // TODO: should not 200
        // $this->assertResponseOk();

        // should say merchant not found
        // TODO: Bugs Caused By zero code
        // $this->assertSame(Status::UNKNOWN_ERROR, $response->code);

        // should update the category
        $currentCategory = Category::where('category_id', $category->category_id)->first();
        $this->assertSame('Should Not Updated', $currentCategory->category_name);
    }

    public function testOK_update_category_as_super_admin()
    {
        $_GET['apikey']       = $this->authData->api_key;
        $_GET['apitimestamp'] = time();

        $_POST['category_id']    = $this->category->category_id;
        $_POST['category_name']  = 'Unique Submitted';
        $_POST['category_level'] = '1';
        $_POST['status']         = 'active';

        $url = $this->baseUrl . '?' . http_build_query($_GET);

        $secretKey = $this->authData->api_secret_key;
        $_SERVER['REQUEST_METHOD']         = 'POST';
        $_SERVER['REQUEST_URI']            = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('POST', $url, $_POST)->getContent();
        $response = json_decode($response);

        // Should be OK
        $this->assertResponseOk();

        // should say OK
        $this->assertSame(Status::OK, $response->code);
        $this->assertSame(Status::OK_MSG, $response->message);

        // should update the category
        $currentCategory = Category::where('category_id', $this->category->category_id)->first();
        $this->assertSame('Unique Submitted', $currentCategory->category_name);
    }

    public function testOK_update_owned_category()
    {
        $role = Factory::create('role_admin');
        $permission = Factory::create('Permission', ['permission_name' => 'update_category']);
        $user = Factory::create('User', ['user_role_id' => $role->role_id]);
        $authData = Factory::create('Apikey', ['user_id' => $user->user_id]);
        $merchant = Factory::create('Merchant', ['user_id' => $user->user_id]);
        $category = Factory::create('Category', ['created_by' => $user->user_id, 'merchant_id' => $merchant->merchant_id]);

        Factory::create('PermissionRole', ['permission_id' => $permission->permission_id, 'role_id' => $role->role_id]);

        $_GET['apikey']       = $authData->api_key;
        $_GET['apitimestamp'] = time();

        $_POST['category_id']    = $category->category_id;
        $_POST['category_name']  = 'Unique Submitted';
        $_POST['category_level'] = '1';
        $_POST['status']         = 'active';

        $url = $this->baseUrl . '?' . http_build_query($_GET);

        $secretKey = $authData->api_secret_key;
        $_SERVER['REQUEST_METHOD']         = 'POST';
        $_SERVER['REQUEST_URI']            = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('POST', $url, $_POST)->getContent();
        $response = json_decode($response);

        // Should be OK
        $this->assertResponseOk();

        // should say request OK
        $this->assertSame(Status::OK, $response->code);
        $this->assertSame(Status::OK_MSG, $response->message);

        // should update the category
        $currentCategory = Category::where('category_id', $category->category_id)->first();
        $this->assertSame('Unique Submitted', $currentCategory->category_name);
    }

    public function testOK_update_same_merchant_owner()
    {
        $role = Factory::create('role_admin');
        $permission = Factory::create('Permission', ['permission_name' => 'update_category']);
        $user = Factory::create('User', ['user_role_id' => $role->role_id]);
        $authData = Factory::create('Apikey', ['user_id' => $user->user_id]);
        $merchant = Factory::create('Merchant', ['user_id' => $user->user_id]);
        $category = Factory::create('Category', ['merchant_id' => $merchant->merchant_id]);

        Factory::create('PermissionRole', ['permission_id' => $permission->permission_id, 'role_id' => $role->role_id]);

        $_GET['apikey']       = $authData->api_key;
        $_GET['apitimestamp'] = time();

        $_POST['category_id']    = $category->category_id;
        $_POST['category_name']  = 'Unique Submitted';
        $_POST['category_level'] = '1';
        $_POST['status']         = 'active';

        $url = $this->baseUrl . '?' . http_build_query($_GET);

        $secretKey = $authData->api_secret_key;
        $_SERVER['REQUEST_METHOD']         = 'POST';
        $_SERVER['REQUEST_URI']            = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('POST', $url, $_POST)->getContent();
        $response = json_decode($response);

        // Should be OK
        $this->assertResponseOk();

        // should say request OK
        $this->assertSame(Status::OK, $response->code);
        $this->assertSame(Status::OK_MSG, $response->message);

        // should update the category
        $currentCategory = Category::where('category_id', $category->category_id)->first();
        $this->assertSame('Unique Submitted', $currentCategory->category_name);
    }
}
