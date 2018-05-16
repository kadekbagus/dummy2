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
            'production' => '',
            'sandbox' => 'https://bonabo.sepulsa.co.id/api/v1/'
        ],
        'auth' => [
            'production' => [
                'username' => '',
                'password' => '',
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

    public function testFAIL()
    {
        $this->config['auth']['sandbox']['username'] = 'wrongUsername';
        $response = Login::create($this->config)->login();

        $this->assertTrue(is_null($response->response));
    }
}
