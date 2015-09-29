<?php
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;

/**
 * UUID Smoke Test: Setting API
 */
class UpdateSettingTest extends TestCase
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
        $url = '/api/v1/setting/update?' . http_build_query($_GET);
        $secretKey = $authData->api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $response = $this->call('POST', $url, $_POST)->getContent();
        $response = json_decode($response);
        return $response;
    }

    public function testUpdateSetting()
    {
        $setting = new Setting();
        $setting->setting_name = 'unique.name';
        $setting->setting_value = 'value';
        $setting->object_id = null;
        $setting->object_type = null;
        $setting->status = 'active';
        $setting->save();
        $response = $this->makeRequest([
            'setting_name' => $setting->setting_name,
            'setting_value' => 'value',
            'object_id' => (string)$this->authData->apikey_id,
            'object_type' => 'Apikey',
        ]);
        $this->assertSame('Request OK', $response->message);
        $this->assertSame('success', $response->status);
        $setting = Setting::find($setting->setting_id);
        $this->assertNotNull($setting);
        $this->assertEquals((string)$this->authData->apikey_id, (string)$setting->object_id);

        $response = $this->makeRequest([
            'setting_name' => 'nonexistent.setting',
            'setting_value' => 'value',
            'object_id' => (string)$this->authData->apikey_id,
            'object_type' => 'Apikey',
        ]);
        $this->assertSame('Request OK', $response->message);
        $this->assertSame('success', $response->status);
        $setting = Setting::find($response->data->setting_id);
        $this->assertNotNull($setting);
        $this->assertEquals('nonexistent.setting', $setting->setting_name);
        $this->assertEquals((string)$this->authData->apikey_id, (string)$setting->object_id);
    }


}
