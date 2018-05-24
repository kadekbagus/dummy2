<?php

/**
 * Unit test for Sepulsa Login API
 * @author Ahmad <ahmad@dominopos.com>
 */

use Orbit\Helper\Sepulsa\API\Login;

class LoginTest extends TestCase
{
    private $config = [
        // GTM Sepulsa ID
        'partner_id' => 42,
        // set to true to switch base uri to production
        'is_production' => false,
        // use sandbox base uri for development (with trailing slash)
        'base_uri' => [
            'production' => 'http://sepulsa.prod',
            'sandbox' => 'https://bonabo.sepulsa.co.id/api/v1/'
        ],
        'auth' => [
            'production' => [
                'username' => 'produser',
                'password' => 'prodpass',
            ],
            'sandbox' => [
                'username' => 'buzzebees@sepulsa.com',
                'password' => 'masukaja',
            ]
        ],
        // cache key, the session will be saved to cache
        'session_key_name' => 'sepulsa_token'
    ];

    public function setUp()
    {
        parent::setUp();
    }

    public function tearDown()
    {

    }

    public function testOK()
    {
        $response = Login::create($this->config)->login();

        // check response metas
        $this->assertTrue($response->response->meta->status);
        $this->assertSame('v1', $response->response->meta->version);

        // token should be in response
        $this->assertTrue(isset($response->response->result->token));
        $this->assertTrue(! empty($response->response->result->token));
        $this->assertTrue(is_string($response->response->result->token));
    }

    public function testEnvSwitchOK()
    {
        // test if the config selector works when env is switched to production
        $this->config['is_production'] = true;
        $config = Login::create($this->config)->getConfigAfterSelector();

        // check response metas
        $this->assertSame($config['base_uri'], $this->config['base_uri']['production']);
        $this->assertSame($config['auth']['username'], $this->config['auth']['production']['username']);
        $this->assertSame($config['auth']['password'], $this->config['auth']['production']['password']);
    }

    public function testFAIL()
    {
        $this->config['auth']['sandbox']['username'] = 'wrongUsername';
        $response = Login::create($this->config)->login();

        $this->assertTrue(is_null($response->response));
    }
}
