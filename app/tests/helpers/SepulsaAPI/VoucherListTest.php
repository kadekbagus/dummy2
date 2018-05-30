<?php

/**
 * Unit test for Sepulsa Login API
 * @author Ahmad <ahmad@dominopos.com>
 */

use Orbit\Helper\Sepulsa\API\VoucherList;

class VoucherListTest extends TestCase
{
    private $config = [
        // GTM Sepulsa ID
        'partner_id' => 76,
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
                'username' => 'gtm_user@sepulsa.com',
                'password' => '12345678',
            ]
        ],
        // cache key, the session will be saved to cache
        'session_key_name' => 'sepulsa_token'

        // redeem callback secret_token, to be given to sepulsa
        'callback_secret_token' => 'HXJQluD4hGV1UqgaaR4YJomdv0zsQXny',
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
        $response = VoucherList::create($this->config)->getList();

        // check response metas
        $this->assertTrue($response->meta->status);
        $this->assertSame('v1', $response->meta->version);

        // result data should be an array
        $this->assertTrue(isset($response->result->data));
        $this->assertTrue(is_array($response->result->data));
    }
}
