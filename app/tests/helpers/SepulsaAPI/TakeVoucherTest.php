<?php

/**
 * Unit test for Sepulsa Login API
 * @author Ahmad <ahmad@dominopos.com>
 */

use Orbit\Helper\Sepulsa\API\TakeVoucher;
use Orbit\Helper\Sepulsa\API\VoucherList;

class TakeVoucherTest extends TestCase
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
        // get tokens
        $listResponse = VoucherList::create($this->config)->getList();

        if (isset($listResponse->result->data) && ! empty($listResponse->result->data)) {
            $token = isset($listResponse->result->data[0]->token) ? $listResponse->result->data[0]->token : null;

            if (! is_null($token)) {
                $trxId = '123';
                $tokens = [
                    ["token" => $token]
                ];

                $response = TakeVoucher::create($this->config)->take($trxId, $tokens);

                // check response metas
                $this->assertTrue($response->meta->status);
                $this->assertSame('v1', $response->meta->version);

                // result data should be an array
                $this->assertTrue(isset($response->result));
                $this->assertTrue(is_array($response->result));
                $this->assertTrue(isset($response->result[0]));
                $this->assertTrue(is_object($response->result[0]));
                // code and redeem_url should exist & not empty
                $this->assertTrue(! empty($response->result[0]->code));
                $this->assertTrue(is_string($response->result[0]->code));
                $this->assertTrue(! empty($response->result[0]->redeem_url));
                $this->assertTrue(is_string($response->result[0]->redeem_url));
            }
        }
    }
}
