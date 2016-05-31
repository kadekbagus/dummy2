<?php
/**
 * Test for API /api/v1/cust/promotion/detail
 * @author kadek <kadek@dominopos.com>
 */
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;
use Carbon\Carbon as Carbon;

class getPromotionDetailMobileCITest extends TestCase
{
    private $baseUrl = '/api/v1/cust/promotions/detail?';

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

    public function testMallIdAndPromotionIdNotGiven()
    {
        // validate the mall id
        $response = $this->makeRequest([], $this->apikey);

        $this->assertSame(14, $response->code);
        $this->assertSame('error', $response->status);
        $this->assertRegExp('/mall id field is required/i', $response->message);
    }

    public function testMallIdAndPromotionIdGiven()
    {
        // promotion id given should return data for that promotion
        $data = array('mall_id' => $this->mallA->merchant_id,
                      'promotion_id' => $this->promotion1->news_id);
                
        $response = $this->makeRequest($data, $this->apikey);

        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/Request OK/i', $response->message);

        $this->assertSame(1, count($response->data));
        $this->assertSame($this->promotion1->news_id, $response->data->news_id);
        $this->assertSame($this->promotion1->news_name, $response->data->news_name);
    }

    public function testExpiredPromotion()
    {
        // expired promotion should not appear on the list
        $promotion_expired = Factory::create('News', ['mall_id' => $this->mallA->merchant_id, 
                                                             'object_type' => 'promotion',
                                                             'link_object_type' => 'tenant',
                                                             'end_date' => Carbon::yesterday(),
                                                             'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                                             'is_all_gender' => 'Y',
                                                             'is_all_age' => 'Y'
                                                            ]
                                                    );

        $news_merchant = Factory::create('NewsMerchant', ['news_id' => $promotion_expired->news_id, 'merchant_id' => $this->mallA->merchant_id]);

        $data = array('mall_id' => $this->mallA->merchant_id,
                      'promotion_id' => $this->promotion1->news_id);
                
        $response = $this->makeRequest($data, $this->apikey);

        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/Request OK/i', $response->message);

        $this->assertSame(1, count($response->data));
        $this->assertSame($this->promotion1->news_id, $response->data->news_id);
        $this->assertSame($this->promotion1->news_name, $response->data->news_name);
    }

    public function testGetPromotionFromAnotherMall()
    {
        // promotions from another mall should not appear
        $promotion_mallB = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                                         'object_type' => 'promotion',
                                                         'link_object_type' => 'tenant',
                                                         'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                                         'is_all_gender' => 'Y',
                                                         'is_all_age' => 'Y'
                                                        ]
                                                );
        
        $news_merchant = Factory::create('NewsMerchant', ['news_id' => $promotion_mallB->news_id, 'merchant_id' => $this->mallB->merchant_id]); 

        $data = array('mall_id' => $this->mallA->merchant_id,
                      'promotion_id' => $this->promotion1->news_id);

        $response = $this->makeRequest($data, $this->apikey);

        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/Request OK/i', $response->message);

        $this->assertSame(1, count($response->data));
        $this->assertSame($this->promotion1->news_id, $response->data->news_id);
        $this->assertSame($this->promotion1->news_name, $response->data->news_name);
    }

    public function testInvalidMallId()
    {
        // what happend if promotion id is wrong?
        $promotion1 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                             'object_type' => 'promotion',
                                             'link_object_type' => 'tenant', 
                                             'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                             'is_all_gender' => 'Y',
                                             'is_all_age' => 'Y'
                                            ]
                                    );

        $promotion2 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                             'object_type' => 'promotion',
                                             'link_object_type' => 'tenant', 
                                             'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                             'is_all_gender' => 'Y',
                                             'is_all_age' => 'Y'
                                            ]
                                    );
        
        $news_merchant1 = Factory::create('NewsMerchant', ['news_id' => $promotion1->news_id, 'merchant_id' => $this->mallB->merchant_id]);
        $news_merchant2 = Factory::create('NewsMerchant', ['news_id' => $promotion2->news_id, 'merchant_id' => $this->mallB->merchant_id]);  

        $data = array('mall_id' => '123456',
                      'promotion_id' => $this->promotion1->news_id);

        $response = $this->makeRequest($data, $this->apikey);

        $this->assertSame(14, $response->code);
        $this->assertSame('error', $response->status);
        $this->assertRegExp('/Mall ID you specified is not found/i', $response->message);
    }

    public function testInvalidPromotionId()
    {
        // test if promotion id is wrong
        $promotion1 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                             'object_type' => 'promotion',
                                             'link_object_type' => 'tenant', 
                                             'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                             'is_all_gender' => 'Y',
                                             'is_all_age' => 'Y'
                                            ]
                                    );

        $promotion2 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                             'object_type' => 'promotion',
                                             'link_object_type' => 'tenant', 
                                             'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                             'is_all_gender' => 'Y',
                                             'is_all_age' => 'Y'
                                            ]
                                    );
        
        $news_merchant1 = Factory::create('NewsMerchant', ['news_id' => $promotion1->news_id, 'merchant_id' => $this->mallB->merchant_id]);
        $news_merchant2 = Factory::create('NewsMerchant', ['news_id' => $promotion2->news_id, 'merchant_id' => $this->mallB->merchant_id]);  

        $data = array('mall_id' => $this->mallB->merchant_id,
                      'promotion_id' => '123213');

        $response = $this->makeRequest($data, $this->apikey);

        $this->assertSame(14, $response->code);
        $this->assertSame('error', $response->status);
        $this->assertRegExp('/Promotion ID you specified is not found/i', $response->message);
    }

    public function testGetCorrectDataPromotion()
    {
        // get the correct data promotion based on promotion id
        $tenant1 = Factory::create('Tenant', ['parent_id' => $this->mallB->merchant_id, 'status' => 'active']);
        $tenant2 = Factory::create('Tenant', ['parent_id' => $this->mallB->merchant_id, 'status' => 'active']);
        $tenant3 = Factory::create('Tenant', ['parent_id' => $this->mallB->merchant_id, 'status' => 'active']);
        $tenant4 = Factory::create('Tenant', ['parent_id' => $this->mallB->merchant_id, 'status' => 'active']);

        $promotion1 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                             'object_type' => 'promotion',
                                             'link_object_type' => 'tenant', 
                                             'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                             'is_all_gender' => 'Y',
                                             'is_all_age' => 'Y'
                                            ]
                                    );

        $promotion2 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                             'object_type' => 'promotion',
                                             'link_object_type' => 'tenant', 
                                             'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                             'is_all_gender' => 'Y',
                                             'is_all_age' => 'Y'
                                            ]
                                    );

        $promotion3 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                             'object_type' => 'promotion',
                                             'link_object_type' => 'tenant', 
                                             'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                             'is_all_gender' => 'Y',
                                             'is_all_age' => 'Y'
                                            ]
                                    );

        $news_merchant_promotion_1 = Factory::create('NewsMerchant', ['news_id' => $promotion1->news_id, 'merchant_id' => $tenant1->merchant_id, 'object_type' => 'retailer']);
        $news_merchant_promotion_1 = Factory::create('NewsMerchant', ['news_id' => $promotion1->news_id, 'merchant_id' => $tenant3->merchant_id, 'object_type' => 'retailer']);

        $news_merchant_promotion_2 = Factory::create('NewsMerchant', ['news_id' => $promotion2->news_id, 'merchant_id' => $tenant1->merchant_id, 'object_type' => 'retailer']);
        $news_merchant_promotion_2 = Factory::create('NewsMerchant', ['news_id' => $promotion2->news_id, 'merchant_id' => $tenant2->merchant_id, 'object_type' => 'retailer']);

        $news_merchant_promotion_3 = Factory::create('NewsMerchant', ['news_id' => $promotion3->news_id, 'merchant_id' => $tenant3->merchant_id, 'object_type' => 'retailer']);
        $news_merchant_promotion_3 = Factory::create('NewsMerchant', ['news_id' => $promotion3->news_id, 'merchant_id' => $tenant4->merchant_id, 'object_type' => 'retailer']);

        // promotion1
        $data = array('mall_id' => $this->mallB->merchant_id, 'promotion_id' => $promotion1->news_id);

        $response = $this->makeRequest($data, $this->apikey);

        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/Request OK/i', $response->message);

        $this->assertSame($promotion1->news_id, $response->data->news_id);
        $this->assertSame($promotion1->news_name, $response->data->news_name);
        $this->assertSame($promotion1->description, $response->data->description);

        // promotion2
        $data = array('mall_id' => $this->mallB->merchant_id, 'promotion_id' => $promotion2->news_id);

        $response = $this->makeRequest($data, $this->apikey);

        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/Request OK/i', $response->message);

        $this->assertSame($promotion2->news_id, $response->data->news_id);
        $this->assertSame($promotion2->news_name, $response->data->news_name);
        $this->assertSame($promotion2->description, $response->data->description);

        // promotion3
        $data = array('mall_id' => $this->mallB->merchant_id, 'promotion_id' => $promotion3->news_id);

        $response = $this->makeRequest($data, $this->apikey);

        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/Request OK/i', $response->message);

        $this->assertSame($promotion3->news_id, $response->data->news_id);
        $this->assertSame($promotion3->news_name, $response->data->news_name);
        $this->assertSame($promotion3->description, $response->data->description);
    }

    public function testGetAllTenantInactiveOrNot()
    {
        // this test is for checking value of all_tenant_inactive true or false based on active and inactive tenants
        $tenant1 = Factory::create('Tenant', ['parent_id' => $this->mallB->merchant_id, 'status' => 'active']);
        $tenant2 = Factory::create('Tenant', ['parent_id' => $this->mallB->merchant_id, 'status' => 'active']);
        $tenant3 = Factory::create('Tenant', ['parent_id' => $this->mallB->merchant_id, 'status' => 'inactive']);
        $tenant4 = Factory::create('Tenant', ['parent_id' => $this->mallB->merchant_id, 'status' => 'inactive']);

        $promotion1 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                             'object_type' => 'promotion',
                                             'link_object_type' => 'tenant', 
                                             'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                             'is_all_gender' => 'Y',
                                             'is_all_age' => 'Y'
                                            ]
                                    );

        $promotion2 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                             'object_type' => 'promotion',
                                             'link_object_type' => 'tenant', 
                                             'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                             'is_all_gender' => 'Y',
                                             'is_all_age' => 'Y'
                                            ]
                                    );

        $promotion3 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                             'object_type' => 'promotion',
                                             'link_object_type' => 'tenant', 
                                             'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                             'is_all_gender' => 'Y',
                                             'is_all_age' => 'Y'
                                            ]
                                    );

        $news_merchant_promotion_1 = Factory::create('NewsMerchant', ['news_id' => $promotion1->news_id, 'merchant_id' => $tenant1->merchant_id, 'object_type' => 'retailer']);
        $news_merchant_promotion_1 = Factory::create('NewsMerchant', ['news_id' => $promotion1->news_id, 'merchant_id' => $tenant3->merchant_id, 'object_type' => 'retailer']);

        $news_merchant_promotion_2 = Factory::create('NewsMerchant', ['news_id' => $promotion2->news_id, 'merchant_id' => $tenant1->merchant_id, 'object_type' => 'retailer']);
        $news_merchant_promotion_2 = Factory::create('NewsMerchant', ['news_id' => $promotion2->news_id, 'merchant_id' => $tenant2->merchant_id, 'object_type' => 'retailer']);

        $news_merchant_promotion_3 = Factory::create('NewsMerchant', ['news_id' => $promotion3->news_id, 'merchant_id' => $tenant3->merchant_id, 'object_type' => 'retailer']);
        $news_merchant_promotion_3 = Factory::create('NewsMerchant', ['news_id' => $promotion3->news_id, 'merchant_id' => $tenant4->merchant_id, 'object_type' => 'retailer']);


        // promotion1 should return all_tenant_inactive false
        $data = array('mall_id' => $this->mallB->merchant_id, 'promotion_id' => $promotion1->news_id);

        $response = $this->makeRequest($data, $this->apikey);

        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/Request OK/i', $response->message);

        $this->assertSame($promotion1->news_id, $response->data->news_id);
        $this->assertSame($promotion1->news_name, $response->data->news_name);
        $this->assertFalse($response->data->all_tenant_inactive);

        // promotion2 should return all_tenant_inactive false
        $data = array('mall_id' => $this->mallB->merchant_id, 'promotion_id' => $promotion2->news_id);

        $response = $this->makeRequest($data, $this->apikey);

        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/Request OK/i', $response->message);

        $this->assertSame($promotion2->news_id, $response->data->news_id);
        $this->assertSame($promotion2->news_name, $response->data->news_name);
        $this->assertFalse($response->data->all_tenant_inactive);

        // promotion3 should return all_tenant_inactive true
        $data = array('mall_id' => $this->mallB->merchant_id, 'promotion_id' => $promotion3->news_id);

        $response = $this->makeRequest($data, $this->apikey);

        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/Request OK/i', $response->message);

        $this->assertSame($promotion3->news_id, $response->data->news_id);
        $this->assertSame($promotion3->news_name, $response->data->news_name);
        $this->assertTrue($response->data->all_tenant_inactive);
    }


    public function testTranslationPromotionDetail() 
    {
        $language_en = Factory::create('Language', ['name' => 'en']);
        $language_id = Factory::create('Language', ['name' => 'id']);
        $language_jp = Factory::create('Language', ['name' => 'jp']);

        $merchant_language1 = Factory::create('MerchantLanguage', ['language_id' => $language_en->language_id, 'merchant_id' => $this->mallB->merchant_id]);
        $merchant_language2 = Factory::create('MerchantLanguage', ['language_id' => $language_id->language_id, 'merchant_id' => $this->mallB->merchant_id]);
        $merchant_language3 = Factory::create('MerchantLanguage', ['language_id' => $language_jp->language_id, 'merchant_id' => $this->mallB->merchant_id]);

        $promotion1 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                     'object_type' => 'promotion',
                                     'link_object_type' => 'tenant', 
                                     'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                     'is_all_gender' => 'Y',
                                     'is_all_age' => 'Y'
                                    ]
                            );

        $promotion_translation_en = Factory::create('NewsTranslation', ['news_id' => $promotion1->news_id, 
                                                                        'merchant_id' => $this->mallB->merchant_id, 
                                                                        'merchant_language_id' => $language_en->language_id]
                                                    );      

        $promotion_translation_id = Factory::create('NewsTranslation', ['news_id' => $promotion1->news_id, 
                                                                'merchant_id' => $this->mallB->merchant_id, 
                                                                'merchant_language_id' => $language_id->language_id]
                                            );  

        $promotion_translation_jp = Factory::create('NewsTranslation', ['news_id' => $promotion1->news_id, 
                                                                'merchant_id' => $this->mallB->merchant_id, 
                                                                'merchant_language_id' => $language_jp->language_id]
                                            );  

        $news_merchant_promotion_1 = Factory::create('NewsMerchant', ['news_id' => $promotion1->news_id, 'merchant_id' => $this->mallB->merchant_id, 'object_type' => 'mall']);

        // test translation english
        $data = array('mall_id' => $this->mallB->merchant_id, 'promotion_id' => $promotion1->news_id, 'language_id' => $language_en->language_id);

        $response = $this->makeRequest($data, $this->apikey);

        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/Request OK/i', $response->message);

        $this->assertSame($promotion_translation_en->news_id, $response->data->news_id);
        $this->assertSame($promotion_translation_en->news_name, $response->data->news_name);

        // test translation indonesia
        $data = array('mall_id' => $this->mallB->merchant_id, 'promotion_id' => $promotion1->news_id, 'language_id' => $language_id->language_id);

        $response = $this->makeRequest($data, $this->apikey);

        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/Request OK/i', $response->message);

        $this->assertSame($promotion_translation_id->news_id, $response->data->news_id);
        $this->assertSame($promotion_translation_id->news_name, $response->data->news_name);

        // test translation japan
        $data = array('mall_id' => $this->mallB->merchant_id, 'promotion_id' => $promotion1->news_id, 'language_id' => $language_jp->language_id);

        $response = $this->makeRequest($data, $this->apikey);

        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/Request OK/i', $response->message);

        $this->assertSame($promotion_translation_jp->news_id, $response->data->news_id);
        $this->assertSame($promotion_translation_jp->news_name, $response->data->news_name);
    }
}