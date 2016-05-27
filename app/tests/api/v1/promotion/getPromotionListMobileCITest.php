<?php
/**
 * Test for API /api/v1/cust/promotion
 * @author kadek <kadek@dominopos.com>
 */
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;
use Carbon\Carbon as Carbon;

class getPromotionListMobileCITest extends TestCase
{
    private $baseUrl = '/api/v1/cust/promotions?';

    public function setUp()
    {
        parent::setUp();

        DB::beginTransaction();

        $this->authData = Factory::create('Apikey');

        $this->timezone = Factory::create('timezone_jakarta');
        $this->campaign_status_ongoing = Factory::create('CampaignStatus', ['campaign_status_name' => 'ongoing']);

        $this->mallA = Factory::create('Mall', ['timezone_id' => $this->timezone->timezone_id]);
        $this->mallB = Factory::create('Mall', ['timezone_id' => $this->timezone->timezone_id]);

        $this->promotion1 = Factory::create('News', ['mall_id' => $this->mallA->merchant_id, 
                                                     'object_type' => 'promotion',
                                                     'link_object_type' => 'tenant',
                                                     'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                                     'is_all_gender' => 'Y',
                                                     'is_all_age' => 'Y'
                                                    ]
                                            );

        $this->promotion2 = Factory::create('News', ['mall_id' => $this->mallA->merchant_id, 
                                                     'object_type' => 'promotion',
                                                     'link_object_type' => 'tenant', 
                                                     'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                                     'is_all_gender' => 'Y',
                                                     'is_all_age' => 'Y'
                                                    ]
                                            );

        $this->news_merchant1 = Factory::create('NewsMerchant', ['news_id' => $this->promotion1->news_id, 'merchant_id' => $this->mallA->merchant_id]);
        $this->news_merchant2 = Factory::create('NewsMerchant', ['news_id' => $this->promotion2->news_id, 'merchant_id' => $this->mallA->merchant_id]);  

        $this->user = Factory::create('user_guest');
        $this->userdetail = Factory::create('UserDetail', [
            'user_id' => $this->user->user_id
        ]);
        $this->apikey = Factory::create('Apikey', [
            'user_id' => $this->user->user_id
        ]);
    }

    private function makeRequest($data, $authData = null)
    {
        if ($authData === null) {
            $authData = $this->authData;
        }

        $_GET = array_merge($data, [
            'apikey' => $authData->api_key,
            'apitimestamp' => time(),
        ]);

        $_GET['apikey'] = $authData->api_key;
        $_GET['apitimestamp'] = time();

        $_POST = [];
        $url = $this->baseUrl . http_build_query($_GET);
        $secretKey = $authData->api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $response = $this->call('GET', $url, $_POST)->getContent();
        $response = json_decode($response);
        return $response;
    }

    public function tearDown()
    {
        $this->useTruncate = false;

        parent::tearDown();
    }

    public function testNotAllowedUserRole()
    {
        // only super admin, guest and consumer can access the API
        $role = Factory::create('role_mall_owner');
        $user = Factory::create('User', ['user_role_id' => $role->role_id]);
        $apikey = Factory::create('Apikey', ['user_id' => $user->user_id]);

        $response = $this->makeRequest([], $apikey);

        $this->assertSame(13, $response->code);
        $this->assertSame('error', $response->status);
        $this->assertRegExp('/Your role are not allowed to access this resource/i', $response->message);
    }

    public function testAllowedUserRole()
    {
        // only super admin, guest and consumer can access the API
        $authData = Factory::create('Apikey');
        $response = $this->makeRequest([], $authData);

        $this->assertSame(14, $response->code);
        $this->assertSame('error', $response->status);
        $this->assertRegExp('/mall id field is required/i', $response->message);
    }

    public function testMallIdNotGiven()
    {
        // validate the mall id
        $response = $this->makeRequest([], $this->apikey);

        $this->assertSame(14, $response->code);
        $this->assertSame('error', $response->status);
        $this->assertRegExp('/mall id field is required/i', $response->message);
    }

    public function testMallIdGiven()
    {
        // mall id given should return promotion for that mall
        $data = array('mall_id' => $this->mallA->merchant_id);
                
        $response = $this->makeRequest($data, $this->apikey);

        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/Request OK/i', $response->message);

        $this->assertSame(2, $response->data->returned_records);
        $this->assertSame(2, $response->data->total_records);
        $this->assertSame(2, count($response->data->records));
    }

    public function testNewPromotionAdded()
    {
        // new promotion that is valid should appear on the list
        $new_promotion1 = Factory::create('News', ['mall_id' => $this->mallA->merchant_id, 
                                                             'object_type' => 'promotion',
                                                             'link_object_type' => 'tenant',
                                                             'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                                             'is_all_gender' => 'Y',
                                                             'is_all_age' => 'Y'
                                                            ]
                                                    );

        $new_promotion2 = Factory::create('News', ['mall_id' => $this->mallA->merchant_id, 
                                                     'object_type' => 'promotion',
                                                     'link_object_type' => 'tenant',
                                                     'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                                     'is_all_gender' => 'Y',
                                                     'is_all_age' => 'Y'
                                                    ]
                                            );

        $news_merchant1 = Factory::create('NewsMerchant', ['news_id' => $new_promotion1->news_id, 'merchant_id' => $this->mallA->merchant_id]);
        $news_merchant2 = Factory::create('NewsMerchant', ['news_id' => $new_promotion2->news_id, 'merchant_id' => $this->mallA->merchant_id]);

        $data = array('mall_id' => $this->mallA->merchant_id);
                
        $response = $this->makeRequest($data, $this->apikey);

        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/Request OK/i', $response->message);

        $this->assertSame(4, $response->data->returned_records);
        $this->assertSame(4, $response->data->total_records);
        $this->assertSame(4, count($response->data->records));
    }

    public function testExpiredPromotion()
    {
        // expired promotion should not appear on the list
        $this->promotion_expired = Factory::create('News', ['mall_id' => $this->mallA->merchant_id, 
                                                             'object_type' => 'promotion',
                                                             'link_object_type' => 'tenant',
                                                             'end_date' => Carbon::yesterday(),
                                                             'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                                             'is_all_gender' => 'Y',
                                                             'is_all_age' => 'Y'
                                                            ]
                                                    );

        $this->news_merchant = Factory::create('NewsMerchant', ['news_id' => $this->promotion_expired->news_id, 'merchant_id' => $this->mallA->merchant_id]);

        $data = array('mall_id' => $this->mallA->merchant_id);
                
        $response = $this->makeRequest($data, $this->apikey);

        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/Request OK/i', $response->message);

        $this->assertSame(2, $response->data->returned_records);
        $this->assertSame(2, $response->data->total_records);
        $this->assertSame(2, count($response->data->records));
    }

    public function testGetPromotionFromAnotherMall()
    {
        // promotions from another mall should not appear on the list
        $this->promotion_mallB = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                                         'object_type' => 'promotion',
                                                         'link_object_type' => 'tenant',
                                                         'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                                         'is_all_gender' => 'Y',
                                                         'is_all_age' => 'Y'
                                                        ]
                                                );
        
        $this->news_merchant = Factory::create('NewsMerchant', ['news_id' => $this->promotion_mallB->news_id, 'merchant_id' => $this->mallB->merchant_id]); 

        $data = array('mall_id' => $this->mallA->merchant_id);

        $response = $this->makeRequest($data, $this->apikey);

        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/Request OK/i', $response->message);

        $this->assertSame(2, $response->data->returned_records);
        $this->assertSame(2, $response->data->total_records);
        $this->assertSame(2, count($response->data->records));
    }


}