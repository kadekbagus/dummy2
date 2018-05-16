<?php

/**
 * Unit test for Sepulsa Login API
 * @author Ahmad <ahmad@dominopos.com>
 */

use Orbit\Helper\Sepulsa\API\VoucherList;
use Orbit\Helper\Sepulsa\API\VoucherDetail;

class VoucherDetailTest extends TestCase
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
        $listResponse = VoucherList::create($this->config)->getList();

        if (isset($listResponse->result->data) && ! empty($listResponse->result->data)) {
            $token = isset($listResponse->result->data[0]->token) ? $listResponse->result->data[0]->token : null;

            if (! is_null($token)) {
                $response = VoucherDetail::create($this->config)->getDetail($token);

                // check response metas
                $this->assertTrue($response->meta->status);
                $this->assertSame('v1', $response->meta->version);

                // check result
                $this->assertTrue(isset($response->result));
                $this->assertTrue(is_object($response->result));

                // should return the same token
                $this->assertSame($token, $response->result->token);
            }
        }
    }

    public function testFAIL()
    {
        $token = 'blablabla';

        $response = VoucherDetail::create($this->config)->getDetail($token);

        // check response metas
        $this->assertTrue($response->meta->status);
        $this->assertSame('v1', $response->meta->version);

        // check response metas
        $this->assertTrue(is_null($response->result));
    }
}
