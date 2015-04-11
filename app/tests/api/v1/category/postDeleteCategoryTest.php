<?php
/**
 * PHP Unit Test for Category Controller postDeleteCategory
 *
 * @author: Yudi Rahono <yudi.rahono@dominopos.com>
 */

use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;

class postDeleteCategoryTest extends TestCase
{
    private $baseUrl = '/api/v1/family/delete';

    public function setUp()
    {
        parent::setUp();

        $this->authData = Factory::create('apikey_super_admin');
        $this->categories = Factory::times(3)->create('Category');
    }

    public function testOK_delete_category_as_super_admin()
    {
        $_GET['apikey']       = $this->authData->api_key;
        $_GET['apitimestamp'] = time();

        $_POST['category_id']    = $this->categories[2]->category_id;
        $_POST['category_name']  = 'Unique Submitted';
        $_POST['category_level'] = '1';
        $_POST['status']         = 'active';

        $url = $this->baseUrl . '?' . http_build_query($_GET);

        $secretKey = $this->authData->api_secret_key;
        $_SERVER['REQUEST_METHOD']         = 'POST';
        $_SERVER['REQUEST_URI']            = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        // should have correct count before request
        $this->assertSame(3, Category::excludeDeleted()->count());

        $response = $this->call('POST', $url, $_POST)->getContent();
        $response = json_decode($response);

        // Should be OK
        $this->assertResponseOk();

        // should say successfully deleted
        $this->assertSame(Status::OK, $response->code);
        $this->assertSame('Category has been successfully deleted.', $response->message);

        // should decrease number of categories
        $currentCategoryCount = Category::excludeDeleted()->count();
        $this->assertSame(2, $currentCategoryCount);
    }

    public function testOK_delete_owned_category()
    {
        $role = Factory::create('role_admin');
        $permission = Factory::create('Permission', ['permission_name' => 'delete_category']);
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

        // should have correct count before request
        $this->assertSame(4, Category::excludeDeleted()->count());

        $response = $this->call('POST', $url, $_POST)->getContent();
        $response = json_decode($response);

        // Should be OK
        $this->assertResponseOk();

        // should say successfully deleted
        $this->assertSame(Status::OK, $response->code);
        $this->assertSame('Category has been successfully deleted.', $response->message);

        // should decrease number of categories
        $currentCategoryCount = Category::excludeDeleted()->count();
        $this->assertSame(3, $currentCategoryCount);
    }

    public function testOK_delete_same_merchant_owner()
    {
        $role = Factory::create('role_admin');
        $permission = Factory::create('Permission', ['permission_name' => 'delete_category']);
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

        // should say successfully deleted
        $this->assertSame(Status::OK, $response->code);
        $this->assertSame('Category has been successfully deleted.', $response->message);

        // should decrease number of categories
        $currentCategoryCount = Category::excludeDeleted()->count();
        $this->assertSame(3, $currentCategoryCount);
    }
}
