<?php
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;

/**
 * UUID Smoke Test: Employee API
 */
class UpdateEmployeeTest extends TestCase
{
    /** @var Apikey */
    private $authData;
    /** @var Retailer */
    private $retailer;
    /** @var Role */
    private $role;

    public function setUp()
    {
        parent::setUp();

        $this->authData = Factory::create('apikey_super_admin');
        $this->retailer = Factory::create('Retailer', ['is_mall' => 'yes']);
        $this->role = Factory::create('Role', ['role_name' => 'assistant']);
    }

    private function makeRequest($data, $authData = null)
    {
        if ($authData === null) {
            $authData = $this->authData;
        }
        $_GET = array_merge([], [
            'apikey' => $authData->api_key,
            'apitimestamp' => time(),
        ]);
        $_POST = $data;
        $url = '/api/v1/employee/update?' . http_build_query($_GET);
        $secretKey = $authData->api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $response = $this->call('POST', $url, $_POST)->getContent();
        $response = json_decode($response);
        return $response;
    }

    public function testUpdateEmployee()
    {
        $user = Factory::create('User', ['user_role_id' => $this->role->role_id]);
        $user_detail = Factory::create('UserDetail', [
            'user_id' => $user->user_id,
            'retailer_id' => null
        ]);
        $employee = new Employee();
        $employee->user_id = $user->user_id;
        $employee->employee_id_char = 'XYZ123';
        $employee->position = 'Supreme Commander';
        $employee->status = 'active';
        $this->assertTrue($employee->save());
        $response = $this->makeRequest([
            'user_id' => $user->user_id,
            'retailer_ids' => [$this->retailer->merchant_id],
        ]);
        $this->assertSame('Request OK', $response->message);
        $this->assertSame('success', $response->status);
        $user = User::find($response->data->user_id);
        $this->assertNotNull($user);
        $employee = Employee::find($response->data->employee->employee_id);
        $this->assertNotNull($employee);
    }


}
