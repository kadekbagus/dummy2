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
            'user_id' => $this->user->user_id,
            'gender'  => null,
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

    public function testGenderProfilingMale()
    {
        $user = Factory::create('user_consumer');
        $user_detail = Factory::create('UserDetail', ['user_id' => $user->user_id, 'gender' => 'm']);
        $apikey = Factory::create('Apikey', ['user_id' => $user->user_id]);

        $promotion1 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                                     'object_type' => 'promotion',
                                                     'link_object_type' => 'tenant', 
                                                     'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                                     'is_all_gender' => 'N',
                                                     'is_all_age' => 'Y'
                                                    ]
                                            );

        $promotion2 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                             'object_type' => 'promotion',
                                             'link_object_type' => 'tenant', 
                                             'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                             'is_all_gender' => 'N',
                                             'is_all_age' => 'Y'
                                            ]
                                    );

        // promotion1 is for female and promotion2 is for male
        $campaign_gender1 = Factory::create('CampaignGender', ['campaign_type' => 'promotion', 'campaign_id' => $promotion1->news_id, 'gender_value' => 'F']);
        $campaign_gender2 = Factory::create('CampaignGender', ['campaign_type' => 'promotion', 'campaign_id' => $promotion2->news_id, 'gender_value' => 'M']);
        
        $news_merchant1 = Factory::create('NewsMerchant', ['news_id' => $promotion1->news_id, 'merchant_id' => $this->mallB->merchant_id]);
        $news_merchant2 = Factory::create('NewsMerchant', ['news_id' => $promotion2->news_id, 'merchant_id' => $this->mallB->merchant_id]);

        $data = array('mall_id' => $this->mallB->merchant_id);

        // user with gender male
        $response = $this->makeRequest($data, $apikey);

        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/Request OK/i', $response->message);

        $this->assertSame(1, $response->data->returned_records);
        $this->assertSame(1, $response->data->total_records);
        $this->assertSame(1, count($response->data->records));

        // the result should be promotion2 because it match with user gender profile
        $this->assertSame($promotion2->news_id, $response->data->records[0]->news_id);
        $this->assertSame($promotion2->news_name, $response->data->records[0]->news_name);
    }

    public function testGenderProfilingFemale()
    {
        $user = Factory::create('user_consumer');
        $user_detail = Factory::create('UserDetail', ['user_id' => $user->user_id, 'gender' => 'f']);
        $apikey = Factory::create('Apikey', ['user_id' => $user->user_id]);

        $promotion1 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                                     'object_type' => 'promotion',
                                                     'link_object_type' => 'tenant', 
                                                     'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                                     'is_all_gender' => 'N',
                                                     'is_all_age' => 'Y'
                                                    ]
                                            );

        $promotion2 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                             'object_type' => 'promotion',
                                             'link_object_type' => 'tenant', 
                                             'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                             'is_all_gender' => 'N',
                                             'is_all_age' => 'Y'
                                            ]
                                    );

        // promotion1 is for female and promotion2 is for male
        $campaign_gender1 = Factory::create('CampaignGender', ['campaign_type' => 'promotion', 'campaign_id' => $promotion1->news_id, 'gender_value' => 'F']);
        $campaign_gender2 = Factory::create('CampaignGender', ['campaign_type' => 'promotion', 'campaign_id' => $promotion2->news_id, 'gender_value' => 'M']);
        
        $news_merchant1 = Factory::create('NewsMerchant', ['news_id' => $promotion1->news_id, 'merchant_id' => $this->mallB->merchant_id]);
        $news_merchant2 = Factory::create('NewsMerchant', ['news_id' => $promotion2->news_id, 'merchant_id' => $this->mallB->merchant_id]);

        $data = array('mall_id' => $this->mallB->merchant_id);

        // user with gender male
        $response = $this->makeRequest($data, $apikey);

        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/Request OK/i', $response->message);

        $this->assertSame(1, $response->data->returned_records);
        $this->assertSame(1, $response->data->total_records);
        $this->assertSame(1, count($response->data->records));

        // the result should be promotion1 because it match with user gender profile
        $this->assertSame($promotion1->news_id, $response->data->records[0]->news_id);
        $this->assertSame($promotion1->news_name, $response->data->records[0]->news_name);
    }

    public function testGenderProfilingUnknown()
    {
        $user = Factory::create('user_consumer');
        $user_detail = Factory::create('UserDetail', ['user_id' => $user->user_id, 'gender' => null]);
        $apikey = Factory::create('Apikey', ['user_id' => $user->user_id]);

        $promotion1 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                                     'object_type' => 'promotion',
                                                     'link_object_type' => 'tenant', 
                                                     'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                                     'is_all_gender' => 'N',
                                                     'is_all_age' => 'Y'
                                                    ]
                                            );

        $promotion2 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                             'object_type' => 'promotion',
                                             'link_object_type' => 'tenant', 
                                             'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                             'is_all_gender' => 'N',
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

        // promotion1 is for female and promotion2 is for male
        $campaign_gender1 = Factory::create('CampaignGender', ['campaign_type' => 'promotion', 'campaign_id' => $promotion1->news_id, 'gender_value' => 'F']);
        $campaign_gender2 = Factory::create('CampaignGender', ['campaign_type' => 'promotion', 'campaign_id' => $promotion2->news_id, 'gender_value' => 'M']);
        
        $news_merchant1 = Factory::create('NewsMerchant', ['news_id' => $promotion1->news_id, 'merchant_id' => $this->mallB->merchant_id]);
        $news_merchant2 = Factory::create('NewsMerchant', ['news_id' => $promotion2->news_id, 'merchant_id' => $this->mallB->merchant_id]);
        $news_merchant3 = Factory::create('NewsMerchant', ['news_id' => $promotion3->news_id, 'merchant_id' => $this->mallB->merchant_id]);

        $data = array('mall_id' => $this->mallB->merchant_id);

        // user with gender unknown
        $response = $this->makeRequest($data, $apikey);

        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/Request OK/i', $response->message);

        $this->assertSame(1, $response->data->returned_records);
        $this->assertSame(1, $response->data->total_records);
        $this->assertSame(1, count($response->data->records));

        // the result should be promotion3 because it's for all gender
        $this->assertSame($promotion3->news_id, $response->data->records[0]->news_id);
        $this->assertSame($promotion3->news_name, $response->data->records[0]->news_name);
    }

    public function testAgeProfiling1()
    {
        // user with age 20
        $user = Factory::create('user_consumer');
        $user_detail = Factory::create('UserDetail', [
            'user_id' => $user->user_id,
            'birthdate' => date('Y-m-d', strtotime('-20 year')),
            'gender' => 'm'
        ]);

        $apikey = Factory::create('Apikey', ['user_id' => $user->user_id]);

        $promotion1 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                                     'object_type' => 'promotion',
                                                     'link_object_type' => 'tenant', 
                                                     'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                                     'is_all_gender' => 'Y',
                                                     'is_all_age' => 'N'
                                                    ]
                                            );

        $promotion2 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                             'object_type' => 'promotion',
                                             'link_object_type' => 'tenant', 
                                             'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                             'is_all_gender' => 'Y',
                                             'is_all_age' => 'N'
                                            ]
                                    );

        $promotion3 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                             'object_type' => 'promotion',
                                             'link_object_type' => 'tenant', 
                                             'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                             'is_all_gender' => 'Y',
                                             'is_all_age' => 'N'
                                            ]
                                    );

        $news_merchant1 = Factory::create('NewsMerchant', ['news_id' => $promotion1->news_id, 'merchant_id' => $this->mallB->merchant_id]);
        $news_merchant2 = Factory::create('NewsMerchant', ['news_id' => $promotion2->news_id, 'merchant_id' => $this->mallB->merchant_id]);
        $news_merchant3 = Factory::create('NewsMerchant', ['news_id' => $promotion3->news_id, 'merchant_id' => $this->mallB->merchant_id]);

        $age_range1 = Factory::create('AgeRange', ['merchant_id' => $this->mallB->merchant_id, 'min_value' => 0, 'max_value' => 14]);
        $age_range2 = Factory::create('AgeRange', ['merchant_id' => $this->mallB->merchant_id, 'min_value' => 15, 'max_value' => 24]);
        $age_range3 = Factory::create('AgeRange', ['merchant_id' => $this->mallB->merchant_id, 'min_value' => 25, 'max_value' => 34]);

        $campaign_age1 = Factory::create('CampaignAge', ['campaign_type' => 'promotion', 'campaign_id' => $promotion1->news_id, 'age_range_id' => $age_range1->age_range_id]);
        $campaign_age2 = Factory::create('CampaignAge', ['campaign_type' => 'promotion', 'campaign_id' => $promotion2->news_id, 'age_range_id' => $age_range2->age_range_id]);
        $campaign_age3 = Factory::create('CampaignAge', ['campaign_type' => 'promotion', 'campaign_id' => $promotion3->news_id, 'age_range_id' => $age_range3->age_range_id]);

        $data = array('mall_id' => $this->mallB->merchant_id);

        $response = $this->makeRequest($data, $apikey);

        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/Request OK/i', $response->message);

        $this->assertSame(1, $response->data->returned_records);
        $this->assertSame(1, $response->data->total_records);
        $this->assertSame(1, count($response->data->records));

        // the result should be promotion2
        $this->assertSame($promotion2->news_id, $response->data->records[0]->news_id);
        $this->assertSame($promotion2->news_name, $response->data->records[0]->news_name);
    }

    public function testAgeProfiling2()
    {
        // user with age 8
        $user = Factory::create('user_consumer');
        $user_detail = Factory::create('UserDetail', [
            'user_id' => $user->user_id,
            'birthdate' => date('Y-m-d', strtotime('-8 year')),
            'gender' => 'm'
        ]);

        $apikey = Factory::create('Apikey', ['user_id' => $user->user_id]);

        $promotion1 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                                     'object_type' => 'promotion',
                                                     'link_object_type' => 'tenant', 
                                                     'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                                     'is_all_gender' => 'Y',
                                                     'is_all_age' => 'N'
                                                    ]
                                            );

        $promotion2 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                             'object_type' => 'promotion',
                                             'link_object_type' => 'tenant', 
                                             'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                             'is_all_gender' => 'Y',
                                             'is_all_age' => 'N'
                                            ]
                                    );

        $promotion3 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                             'object_type' => 'promotion',
                                             'link_object_type' => 'tenant', 
                                             'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                             'is_all_gender' => 'Y',
                                             'is_all_age' => 'N'
                                            ]
                                    );

        $news_merchant1 = Factory::create('NewsMerchant', ['news_id' => $promotion1->news_id, 'merchant_id' => $this->mallB->merchant_id]);
        $news_merchant2 = Factory::create('NewsMerchant', ['news_id' => $promotion2->news_id, 'merchant_id' => $this->mallB->merchant_id]);
        $news_merchant3 = Factory::create('NewsMerchant', ['news_id' => $promotion3->news_id, 'merchant_id' => $this->mallB->merchant_id]);

        $age_range1 = Factory::create('AgeRange', ['merchant_id' => $this->mallB->merchant_id, 'min_value' => 0, 'max_value' => 14]);
        $age_range2 = Factory::create('AgeRange', ['merchant_id' => $this->mallB->merchant_id, 'min_value' => 15, 'max_value' => 24]);
        $age_range3 = Factory::create('AgeRange', ['merchant_id' => $this->mallB->merchant_id, 'min_value' => 25, 'max_value' => 34]);

        $campaign_age1 = Factory::create('CampaignAge', ['campaign_type' => 'promotion', 'campaign_id' => $promotion1->news_id, 'age_range_id' => $age_range1->age_range_id]);
        $campaign_age2 = Factory::create('CampaignAge', ['campaign_type' => 'promotion', 'campaign_id' => $promotion2->news_id, 'age_range_id' => $age_range2->age_range_id]);
        $campaign_age3 = Factory::create('CampaignAge', ['campaign_type' => 'promotion', 'campaign_id' => $promotion3->news_id, 'age_range_id' => $age_range3->age_range_id]);

        $data = array('mall_id' => $this->mallB->merchant_id);

        $response = $this->makeRequest($data, $apikey);

        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/Request OK/i', $response->message);

        $this->assertSame(1, $response->data->returned_records);
        $this->assertSame(1, $response->data->total_records);
        $this->assertSame(1, count($response->data->records));

        // the result should be promotion1
        $this->assertSame($promotion1->news_id, $response->data->records[0]->news_id);
        $this->assertSame($promotion1->news_name, $response->data->records[0]->news_name);
    }

    public function testAgeProfiling3()
    {
        // user with age 34
        $user = Factory::create('user_consumer');
        $user_detail = Factory::create('UserDetail', [
            'user_id' => $user->user_id,
            'birthdate' => date('Y-m-d', strtotime('-34 year')),
            'gender' => 'm'
        ]);

        $apikey = Factory::create('Apikey', ['user_id' => $user->user_id]);

        $promotion1 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                                     'object_type' => 'promotion',
                                                     'link_object_type' => 'tenant', 
                                                     'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                                     'is_all_gender' => 'Y',
                                                     'is_all_age' => 'N'
                                                    ]
                                            );

        $promotion2 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                             'object_type' => 'promotion',
                                             'link_object_type' => 'tenant', 
                                             'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                             'is_all_gender' => 'Y',
                                             'is_all_age' => 'N'
                                            ]
                                    );

        $promotion3 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                             'object_type' => 'promotion',
                                             'link_object_type' => 'tenant', 
                                             'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                             'is_all_gender' => 'Y',
                                             'is_all_age' => 'N'
                                            ]
                                    );

        $news_merchant1 = Factory::create('NewsMerchant', ['news_id' => $promotion1->news_id, 'merchant_id' => $this->mallB->merchant_id]);
        $news_merchant2 = Factory::create('NewsMerchant', ['news_id' => $promotion2->news_id, 'merchant_id' => $this->mallB->merchant_id]);
        $news_merchant3 = Factory::create('NewsMerchant', ['news_id' => $promotion3->news_id, 'merchant_id' => $this->mallB->merchant_id]);

        $age_range1 = Factory::create('AgeRange', ['merchant_id' => $this->mallB->merchant_id, 'min_value' => 0, 'max_value' => 14]);
        $age_range2 = Factory::create('AgeRange', ['merchant_id' => $this->mallB->merchant_id, 'min_value' => 15, 'max_value' => 24]);
        $age_range3 = Factory::create('AgeRange', ['merchant_id' => $this->mallB->merchant_id, 'min_value' => 25, 'max_value' => 34]);

        $campaign_age1 = Factory::create('CampaignAge', ['campaign_type' => 'promotion', 'campaign_id' => $promotion1->news_id, 'age_range_id' => $age_range1->age_range_id]);
        $campaign_age2 = Factory::create('CampaignAge', ['campaign_type' => 'promotion', 'campaign_id' => $promotion2->news_id, 'age_range_id' => $age_range2->age_range_id]);
        $campaign_age3 = Factory::create('CampaignAge', ['campaign_type' => 'promotion', 'campaign_id' => $promotion3->news_id, 'age_range_id' => $age_range3->age_range_id]);

        $data = array('mall_id' => $this->mallB->merchant_id);

        $response = $this->makeRequest($data, $apikey);

        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/Request OK/i', $response->message);

        $this->assertSame(1, $response->data->returned_records);
        $this->assertSame(1, $response->data->total_records);
        $this->assertSame(1, count($response->data->records));

        // the result should be promotion3
        $this->assertSame($promotion3->news_id, $response->data->records[0]->news_id);
        $this->assertSame($promotion3->news_name, $response->data->records[0]->news_name);
    }

    public function testAgeAndGenderProfiling1()
    {
        // user female age 24
        $user = Factory::create('user_consumer');
        $user_detail = Factory::create('UserDetail', [
            'user_id' => $user->user_id,
            'birthdate' => date('Y-m-d', strtotime('-24 year')),
            'gender' => 'f'
        ]);

        $apikey = Factory::create('Apikey', ['user_id' => $user->user_id]);

        $promotion1 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                                     'object_type' => 'promotion',
                                                     'link_object_type' => 'tenant', 
                                                     'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                                     'is_all_gender' => 'N',
                                                     'is_all_age' => 'N'
                                                    ]
                                            );

        $promotion2 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                             'object_type' => 'promotion',
                                             'link_object_type' => 'tenant', 
                                             'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                             'is_all_gender' => 'N',
                                             'is_all_age' => 'N'
                                            ]
                                    );

        $promotion3 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                             'object_type' => 'promotion',
                                             'link_object_type' => 'tenant', 
                                             'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                             'is_all_gender' => 'Y',
                                             'is_all_age' => 'N'
                                            ]
                                    );

        $news_merchant1 = Factory::create('NewsMerchant', ['news_id' => $promotion1->news_id, 'merchant_id' => $this->mallB->merchant_id]);
        $news_merchant2 = Factory::create('NewsMerchant', ['news_id' => $promotion2->news_id, 'merchant_id' => $this->mallB->merchant_id]);
        $news_merchant3 = Factory::create('NewsMerchant', ['news_id' => $promotion3->news_id, 'merchant_id' => $this->mallB->merchant_id]);

        $age_range1 = Factory::create('AgeRange', ['merchant_id' => $this->mallB->merchant_id, 'min_value' => 0, 'max_value' => 14]);
        $age_range2 = Factory::create('AgeRange', ['merchant_id' => $this->mallB->merchant_id, 'min_value' => 15, 'max_value' => 24]);
        $age_range3 = Factory::create('AgeRange', ['merchant_id' => $this->mallB->merchant_id, 'min_value' => 25, 'max_value' => 34]);

        $campaign_age1 = Factory::create('CampaignAge', ['campaign_type' => 'promotion', 'campaign_id' => $promotion1->news_id, 'age_range_id' => $age_range1->age_range_id]);
        $campaign_age2 = Factory::create('CampaignAge', ['campaign_type' => 'promotion', 'campaign_id' => $promotion2->news_id, 'age_range_id' => $age_range2->age_range_id]);
        $campaign_age3 = Factory::create('CampaignAge', ['campaign_type' => 'promotion', 'campaign_id' => $promotion3->news_id, 'age_range_id' => $age_range3->age_range_id]);

        // promotion1 is for female and promotion2 is for male
        $campaign_gender1 = Factory::create('CampaignGender', ['campaign_type' => 'promotion', 'campaign_id' => $promotion1->news_id, 'gender_value' => 'F']);
        $campaign_gender2 = Factory::create('CampaignGender', ['campaign_type' => 'promotion', 'campaign_id' => $promotion2->news_id, 'gender_value' => 'M']);

        $data = array('mall_id' => $this->mallB->merchant_id);

        $response = $this->makeRequest($data, $apikey);

        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/Request OK/i', $response->message);

        $this->assertSame(0, $response->data->returned_records);
        $this->assertSame(0, $response->data->total_records);
        $this->assertSame(0, count($response->data->records));
    }

    public function testAgeAndGenderProfiling2()
    {
        // user male age 24
        $user = Factory::create('user_consumer');
        $user_detail = Factory::create('UserDetail', [
            'user_id' => $user->user_id,
            'birthdate' => date('Y-m-d', strtotime('-24 year')),
            'gender' => 'm'
        ]);

        $apikey = Factory::create('Apikey', ['user_id' => $user->user_id]);

        $promotion1 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                                     'object_type' => 'promotion',
                                                     'link_object_type' => 'tenant', 
                                                     'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                                     'is_all_gender' => 'N',
                                                     'is_all_age' => 'N'
                                                    ]
                                            );

        $promotion2 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                             'object_type' => 'promotion',
                                             'link_object_type' => 'tenant', 
                                             'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                             'is_all_gender' => 'N',
                                             'is_all_age' => 'N'
                                            ]
                                    );

        $promotion3 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                             'object_type' => 'promotion',
                                             'link_object_type' => 'tenant', 
                                             'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                             'is_all_gender' => 'Y',
                                             'is_all_age' => 'N'
                                            ]
                                    );

        $news_merchant1 = Factory::create('NewsMerchant', ['news_id' => $promotion1->news_id, 'merchant_id' => $this->mallB->merchant_id]);
        $news_merchant2 = Factory::create('NewsMerchant', ['news_id' => $promotion2->news_id, 'merchant_id' => $this->mallB->merchant_id]);
        $news_merchant3 = Factory::create('NewsMerchant', ['news_id' => $promotion3->news_id, 'merchant_id' => $this->mallB->merchant_id]);

        $age_range1 = Factory::create('AgeRange', ['merchant_id' => $this->mallB->merchant_id, 'min_value' => 0, 'max_value' => 14]);
        $age_range2 = Factory::create('AgeRange', ['merchant_id' => $this->mallB->merchant_id, 'min_value' => 15, 'max_value' => 24]);
        $age_range3 = Factory::create('AgeRange', ['merchant_id' => $this->mallB->merchant_id, 'min_value' => 25, 'max_value' => 34]);

        $campaign_age1 = Factory::create('CampaignAge', ['campaign_type' => 'promotion', 'campaign_id' => $promotion1->news_id, 'age_range_id' => $age_range1->age_range_id]);
        $campaign_age2 = Factory::create('CampaignAge', ['campaign_type' => 'promotion', 'campaign_id' => $promotion2->news_id, 'age_range_id' => $age_range2->age_range_id]);
        $campaign_age3 = Factory::create('CampaignAge', ['campaign_type' => 'promotion', 'campaign_id' => $promotion3->news_id, 'age_range_id' => $age_range3->age_range_id]);

        // promotion1 is for female and promotion2 is for male
        $campaign_gender1 = Factory::create('CampaignGender', ['campaign_type' => 'promotion', 'campaign_id' => $promotion1->news_id, 'gender_value' => 'F']);
        $campaign_gender2 = Factory::create('CampaignGender', ['campaign_type' => 'promotion', 'campaign_id' => $promotion2->news_id, 'gender_value' => 'M']);

        $data = array('mall_id' => $this->mallB->merchant_id);

        $response = $this->makeRequest($data, $apikey);

        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/Request OK/i', $response->message);

        $this->assertSame(1, $response->data->returned_records);
        $this->assertSame(1, $response->data->total_records);
        $this->assertSame(1, count($response->data->records));

        // the result should be promotion2
        $this->assertSame($promotion2->news_id, $response->data->records[0]->news_id);
        $this->assertSame($promotion2->news_name, $response->data->records[0]->news_name);
    }
}