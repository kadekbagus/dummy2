<?php
/**
 * Test for API /api/v1/cust/news
 * @author kadek <kadek@dominopos.com>
 */
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;
use Carbon\Carbon as Carbon;

class getNewsListMobileCITest extends TestCase
{
    private $baseUrl = '/api/v1/cust/news?';

    public function setUp()
    {
        parent::setUp();

        DB::beginTransaction();

        $this->authData = Factory::create('Apikey');

        $this->timezone = Factory::create('timezone_jakarta');
        $this->campaign_status_ongoing = Factory::create('CampaignStatus', ['campaign_status_name' => 'ongoing']);

        $this->mallA = Factory::create('Mall', ['timezone_id' => $this->timezone->timezone_id]);
        $this->mallB = Factory::create('Mall', ['timezone_id' => $this->timezone->timezone_id]);

        $this->news1 = Factory::create('News', ['mall_id' => $this->mallA->merchant_id, 
                                                     'object_type' => 'news',
                                                     'link_object_type' => 'tenant',
                                                     'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                                     'is_all_gender' => 'Y',
                                                     'is_all_age' => 'Y'
                                                    ]
                                            );

        $this->news2 = Factory::create('News', ['mall_id' => $this->mallA->merchant_id, 
                                                     'object_type' => 'news',
                                                     'link_object_type' => 'tenant', 
                                                     'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                                     'is_all_gender' => 'Y',
                                                     'is_all_age' => 'Y'
                                                    ]
                                            );

        $this->news_merchant1 = Factory::create('NewsMerchant', ['news_id' => $this->news1->news_id, 'merchant_id' => $this->mallA->merchant_id]);
        $this->news_merchant2 = Factory::create('NewsMerchant', ['news_id' => $this->news2->news_id, 'merchant_id' => $this->mallA->merchant_id]);  

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
        // mall id given should return news for that mall
        $data = array('mall_id' => $this->mallA->merchant_id, 'object_type' => 'news');
                
        $response = $this->makeRequest($data, $this->apikey);

        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/Request OK/i', $response->message);

        $this->assertSame(2, $response->data->returned_records);
        $this->assertSame(2, $response->data->total_records);
        $this->assertSame(2, count($response->data->records));
    }

    public function testNewNewsAdded()
    {
        // new news that is valid should appear on the list
        $new_news1 = Factory::create('News', ['mall_id' => $this->mallA->merchant_id, 
                                                             'object_type' => 'news',
                                                             'link_object_type' => 'tenant',
                                                             'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                                             'is_all_gender' => 'Y',
                                                             'is_all_age' => 'Y'
                                                            ]
                                                    );

        $new_news2 = Factory::create('News', ['mall_id' => $this->mallA->merchant_id, 
                                                     'object_type' => 'news',
                                                     'link_object_type' => 'tenant',
                                                     'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                                     'is_all_gender' => 'Y',
                                                     'is_all_age' => 'Y'
                                                    ]
                                            );

        $news_merchant1 = Factory::create('NewsMerchant', ['news_id' => $new_news1->news_id, 'merchant_id' => $this->mallA->merchant_id]);
        $news_merchant2 = Factory::create('NewsMerchant', ['news_id' => $new_news2->news_id, 'merchant_id' => $this->mallA->merchant_id]);

        $data = array('mall_id' => $this->mallA->merchant_id, 'object_type' => 'news');
                
        $response = $this->makeRequest($data, $this->apikey);

        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/Request OK/i', $response->message);

        $this->assertSame(4, $response->data->returned_records);
        $this->assertSame(4, $response->data->total_records);
        $this->assertSame(4, count($response->data->records));
    }

    public function testExpiredNews()
    {
        // expired news should not appear on the list
        $this->news_expired = Factory::create('News', ['mall_id' => $this->mallA->merchant_id, 
                                                             'object_type' => 'news',
                                                             'link_object_type' => 'tenant',
                                                             'end_date' => Carbon::yesterday(),
                                                             'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                                             'is_all_gender' => 'Y',
                                                             'is_all_age' => 'Y'
                                                            ]
                                                    );

        $this->news_merchant = Factory::create('NewsMerchant', ['news_id' => $this->news_expired->news_id, 'merchant_id' => $this->mallA->merchant_id]);

        $data = array('mall_id' => $this->mallA->merchant_id, 'object_type' => 'news');
                
        $response = $this->makeRequest($data, $this->apikey);

        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/Request OK/i', $response->message);

        $this->assertSame(2, $response->data->returned_records);
        $this->assertSame(2, $response->data->total_records);
        $this->assertSame(2, count($response->data->records));
    }

    public function testGetNewsFromAnotherMall()
    {
        // news from another mall should not appear on the list
        $this->news_mallB = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                                         'object_type' => 'news',
                                                         'link_object_type' => 'tenant',
                                                         'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                                         'is_all_gender' => 'Y',
                                                         'is_all_age' => 'Y'
                                                        ]
                                                );
        
        $this->news_merchant = Factory::create('NewsMerchant', ['news_id' => $this->news_mallB->news_id, 'merchant_id' => $this->mallB->merchant_id]); 

        $data = array('mall_id' => $this->mallA->merchant_id, 'object_type' => 'news');

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

        $news1 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                                     'object_type' => 'news',
                                                     'link_object_type' => 'tenant', 
                                                     'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                                     'is_all_gender' => 'N',
                                                     'is_all_age' => 'Y'
                                                    ]
                                            );

        $news2 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                             'object_type' => 'news',
                                             'link_object_type' => 'tenant', 
                                             'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                             'is_all_gender' => 'N',
                                             'is_all_age' => 'Y'
                                            ]
                                    );

        // news1 is for female and news2 is for male
        $campaign_gender1 = Factory::create('CampaignGender', ['campaign_type' => 'news', 'campaign_id' => $news1->news_id, 'gender_value' => 'F']);
        $campaign_gender2 = Factory::create('CampaignGender', ['campaign_type' => 'news', 'campaign_id' => $news2->news_id, 'gender_value' => 'M']);
        
        $news_merchant1 = Factory::create('NewsMerchant', ['news_id' => $news1->news_id, 'merchant_id' => $this->mallB->merchant_id]);
        $news_merchant2 = Factory::create('NewsMerchant', ['news_id' => $news2->news_id, 'merchant_id' => $this->mallB->merchant_id]);

        $data = array('mall_id' => $this->mallB->merchant_id, 'object_type' => 'news');

        // user with gender male
        $response = $this->makeRequest($data, $apikey);

        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/Request OK/i', $response->message);

        $this->assertSame(1, $response->data->returned_records);
        $this->assertSame(1, $response->data->total_records);
        $this->assertSame(1, count($response->data->records));

        // the result should be news2 because it match with user gender profile
        $this->assertSame($news2->news_id, $response->data->records[0]->news_id);
        $this->assertSame($news2->news_name, $response->data->records[0]->news_name);
    }

    public function testGenderProfilingFemale()
    {
        $user = Factory::create('user_consumer');
        $user_detail = Factory::create('UserDetail', ['user_id' => $user->user_id, 'gender' => 'f']);
        $apikey = Factory::create('Apikey', ['user_id' => $user->user_id]);

        $news1 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                                     'object_type' => 'news',
                                                     'link_object_type' => 'tenant', 
                                                     'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                                     'is_all_gender' => 'N',
                                                     'is_all_age' => 'Y'
                                                    ]
                                            );

        $news2 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                             'object_type' => 'news',
                                             'link_object_type' => 'tenant', 
                                             'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                             'is_all_gender' => 'N',
                                             'is_all_age' => 'Y'
                                            ]
                                    );

        // news1 is for female and news2 is for male
        $campaign_gender1 = Factory::create('CampaignGender', ['campaign_type' => 'news', 'campaign_id' => $news1->news_id, 'gender_value' => 'F']);
        $campaign_gender2 = Factory::create('CampaignGender', ['campaign_type' => 'news', 'campaign_id' => $news2->news_id, 'gender_value' => 'M']);
        
        $news_merchant1 = Factory::create('NewsMerchant', ['news_id' => $news1->news_id, 'merchant_id' => $this->mallB->merchant_id]);
        $news_merchant2 = Factory::create('NewsMerchant', ['news_id' => $news2->news_id, 'merchant_id' => $this->mallB->merchant_id]);

        $data = array('mall_id' => $this->mallB->merchant_id, 'object_type' => 'news');

        // user with gender male
        $response = $this->makeRequest($data, $apikey);

        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/Request OK/i', $response->message);

        $this->assertSame(1, $response->data->returned_records);
        $this->assertSame(1, $response->data->total_records);
        $this->assertSame(1, count($response->data->records));

        // the result should be news1 because it match with user gender profile
        $this->assertSame($news1->news_id, $response->data->records[0]->news_id);
        $this->assertSame($news1->news_name, $response->data->records[0]->news_name);
    }

    public function testGenderProfilingUnknown()
    {
        $user = Factory::create('user_consumer');
        $user_detail = Factory::create('UserDetail', ['user_id' => $user->user_id, 'gender' => null]);
        $apikey = Factory::create('Apikey', ['user_id' => $user->user_id]);

        $news1 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                                     'object_type' => 'news',
                                                     'link_object_type' => 'tenant', 
                                                     'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                                     'is_all_gender' => 'N',
                                                     'is_all_age' => 'Y'
                                                    ]
                                            );

        $news2 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                             'object_type' => 'news',
                                             'link_object_type' => 'tenant', 
                                             'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                             'is_all_gender' => 'N',
                                             'is_all_age' => 'Y'
                                            ]
                                    );

        $news3 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                             'object_type' => 'news',
                                             'link_object_type' => 'tenant', 
                                             'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                             'is_all_gender' => 'Y',
                                             'is_all_age' => 'Y'
                                            ]
                                    );

        // news1 is for female and news2 is for male
        $campaign_gender1 = Factory::create('CampaignGender', ['campaign_type' => 'news', 'campaign_id' => $news1->news_id, 'gender_value' => 'F']);
        $campaign_gender2 = Factory::create('CampaignGender', ['campaign_type' => 'news', 'campaign_id' => $news2->news_id, 'gender_value' => 'M']);
        
        $news_merchant1 = Factory::create('NewsMerchant', ['news_id' => $news1->news_id, 'merchant_id' => $this->mallB->merchant_id]);
        $news_merchant2 = Factory::create('NewsMerchant', ['news_id' => $news2->news_id, 'merchant_id' => $this->mallB->merchant_id]);
        $news_merchant3 = Factory::create('NewsMerchant', ['news_id' => $news3->news_id, 'merchant_id' => $this->mallB->merchant_id]);

        $data = array('mall_id' => $this->mallB->merchant_id, 'object_type' => 'news');

        // user with gender unknown
        $response = $this->makeRequest($data, $apikey);

        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/Request OK/i', $response->message);

        $this->assertSame(1, $response->data->returned_records);
        $this->assertSame(1, $response->data->total_records);
        $this->assertSame(1, count($response->data->records));

        // the result should be news3 because it's for all gender
        $this->assertSame($news3->news_id, $response->data->records[0]->news_id);
        $this->assertSame($news3->news_name, $response->data->records[0]->news_name);
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

        $news1 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                                     'object_type' => 'news',
                                                     'link_object_type' => 'tenant', 
                                                     'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                                     'is_all_gender' => 'Y',
                                                     'is_all_age' => 'N'
                                                    ]
                                            );

        $news2 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                             'object_type' => 'news',
                                             'link_object_type' => 'tenant', 
                                             'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                             'is_all_gender' => 'Y',
                                             'is_all_age' => 'N'
                                            ]
                                    );

        $news3 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                             'object_type' => 'news',
                                             'link_object_type' => 'tenant', 
                                             'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                             'is_all_gender' => 'Y',
                                             'is_all_age' => 'N'
                                            ]
                                    );

        $news_merchant1 = Factory::create('NewsMerchant', ['news_id' => $news1->news_id, 'merchant_id' => $this->mallB->merchant_id]);
        $news_merchant2 = Factory::create('NewsMerchant', ['news_id' => $news2->news_id, 'merchant_id' => $this->mallB->merchant_id]);
        $news_merchant3 = Factory::create('NewsMerchant', ['news_id' => $news3->news_id, 'merchant_id' => $this->mallB->merchant_id]);

        $age_range1 = Factory::create('AgeRange', ['merchant_id' => $this->mallB->merchant_id, 'min_value' => 0, 'max_value' => 14]);
        $age_range2 = Factory::create('AgeRange', ['merchant_id' => $this->mallB->merchant_id, 'min_value' => 15, 'max_value' => 24]);
        $age_range3 = Factory::create('AgeRange', ['merchant_id' => $this->mallB->merchant_id, 'min_value' => 25, 'max_value' => 34]);

        $campaign_age1 = Factory::create('CampaignAge', ['campaign_type' => 'news', 'campaign_id' => $news1->news_id, 'age_range_id' => $age_range1->age_range_id]);
        $campaign_age2 = Factory::create('CampaignAge', ['campaign_type' => 'news', 'campaign_id' => $news2->news_id, 'age_range_id' => $age_range2->age_range_id]);
        $campaign_age3 = Factory::create('CampaignAge', ['campaign_type' => 'news', 'campaign_id' => $news3->news_id, 'age_range_id' => $age_range3->age_range_id]);

        $data = array('mall_id' => $this->mallB->merchant_id, 'object_type' => 'news');

        $response = $this->makeRequest($data, $apikey);

        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/Request OK/i', $response->message);

        $this->assertSame(1, $response->data->returned_records);
        $this->assertSame(1, $response->data->total_records);
        $this->assertSame(1, count($response->data->records));

        // the result should be news2
        $this->assertSame($news2->news_id, $response->data->records[0]->news_id);
        $this->assertSame($news2->news_name, $response->data->records[0]->news_name);
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

        $news1 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                                     'object_type' => 'news',
                                                     'link_object_type' => 'tenant', 
                                                     'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                                     'is_all_gender' => 'Y',
                                                     'is_all_age' => 'N'
                                                    ]
                                            );

        $news2 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                             'object_type' => 'news',
                                             'link_object_type' => 'tenant', 
                                             'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                             'is_all_gender' => 'Y',
                                             'is_all_age' => 'N'
                                            ]
                                    );

        $news3 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                             'object_type' => 'news',
                                             'link_object_type' => 'tenant', 
                                             'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                             'is_all_gender' => 'Y',
                                             'is_all_age' => 'N'
                                            ]
                                    );

        $news_merchant1 = Factory::create('NewsMerchant', ['news_id' => $news1->news_id, 'merchant_id' => $this->mallB->merchant_id]);
        $news_merchant2 = Factory::create('NewsMerchant', ['news_id' => $news2->news_id, 'merchant_id' => $this->mallB->merchant_id]);
        $news_merchant3 = Factory::create('NewsMerchant', ['news_id' => $news3->news_id, 'merchant_id' => $this->mallB->merchant_id]);

        $age_range1 = Factory::create('AgeRange', ['merchant_id' => $this->mallB->merchant_id, 'min_value' => 0, 'max_value' => 14]);
        $age_range2 = Factory::create('AgeRange', ['merchant_id' => $this->mallB->merchant_id, 'min_value' => 15, 'max_value' => 24]);
        $age_range3 = Factory::create('AgeRange', ['merchant_id' => $this->mallB->merchant_id, 'min_value' => 25, 'max_value' => 34]);

        $campaign_age1 = Factory::create('CampaignAge', ['campaign_type' => 'news', 'campaign_id' => $news1->news_id, 'age_range_id' => $age_range1->age_range_id]);
        $campaign_age2 = Factory::create('CampaignAge', ['campaign_type' => 'news', 'campaign_id' => $news2->news_id, 'age_range_id' => $age_range2->age_range_id]);
        $campaign_age3 = Factory::create('CampaignAge', ['campaign_type' => 'news', 'campaign_id' => $news3->news_id, 'age_range_id' => $age_range3->age_range_id]);

        $data = array('mall_id' => $this->mallB->merchant_id, 'object_type' => 'news');

        $response = $this->makeRequest($data, $apikey);

        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/Request OK/i', $response->message);

        $this->assertSame(1, $response->data->returned_records);
        $this->assertSame(1, $response->data->total_records);
        $this->assertSame(1, count($response->data->records));

        // the result should be news1
        $this->assertSame($news1->news_id, $response->data->records[0]->news_id);
        $this->assertSame($news1->news_name, $response->data->records[0]->news_name);
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

        $news1 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                                     'object_type' => 'news',
                                                     'link_object_type' => 'tenant', 
                                                     'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                                     'is_all_gender' => 'Y',
                                                     'is_all_age' => 'N'
                                                    ]
                                            );

        $news2 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                             'object_type' => 'news',
                                             'link_object_type' => 'tenant', 
                                             'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                             'is_all_gender' => 'Y',
                                             'is_all_age' => 'N'
                                            ]
                                    );

        $news3 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                             'object_type' => 'news',
                                             'link_object_type' => 'tenant', 
                                             'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                             'is_all_gender' => 'Y',
                                             'is_all_age' => 'N'
                                            ]
                                    );

        $news_merchant1 = Factory::create('NewsMerchant', ['news_id' => $news1->news_id, 'merchant_id' => $this->mallB->merchant_id]);
        $news_merchant2 = Factory::create('NewsMerchant', ['news_id' => $news2->news_id, 'merchant_id' => $this->mallB->merchant_id]);
        $news_merchant3 = Factory::create('NewsMerchant', ['news_id' => $news3->news_id, 'merchant_id' => $this->mallB->merchant_id]);

        $age_range1 = Factory::create('AgeRange', ['merchant_id' => $this->mallB->merchant_id, 'min_value' => 0, 'max_value' => 14]);
        $age_range2 = Factory::create('AgeRange', ['merchant_id' => $this->mallB->merchant_id, 'min_value' => 15, 'max_value' => 24]);
        $age_range3 = Factory::create('AgeRange', ['merchant_id' => $this->mallB->merchant_id, 'min_value' => 25, 'max_value' => 34]);

        $campaign_age1 = Factory::create('CampaignAge', ['campaign_type' => 'news', 'campaign_id' => $news1->news_id, 'age_range_id' => $age_range1->age_range_id]);
        $campaign_age2 = Factory::create('CampaignAge', ['campaign_type' => 'news', 'campaign_id' => $news2->news_id, 'age_range_id' => $age_range2->age_range_id]);
        $campaign_age3 = Factory::create('CampaignAge', ['campaign_type' => 'news', 'campaign_id' => $news3->news_id, 'age_range_id' => $age_range3->age_range_id]);

        $data = array('mall_id' => $this->mallB->merchant_id, 'object_type' => 'news');

        $response = $this->makeRequest($data, $apikey);

        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/Request OK/i', $response->message);

        $this->assertSame(1, $response->data->returned_records);
        $this->assertSame(1, $response->data->total_records);
        $this->assertSame(1, count($response->data->records));

        // the result should be news3
        $this->assertSame($news3->news_id, $response->data->records[0]->news_id);
        $this->assertSame($news3->news_name, $response->data->records[0]->news_name);
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

        $news1 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                                     'object_type' => 'news',
                                                     'link_object_type' => 'tenant', 
                                                     'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                                     'is_all_gender' => 'N',
                                                     'is_all_age' => 'N'
                                                    ]
                                            );

        $news2 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                             'object_type' => 'news',
                                             'link_object_type' => 'tenant', 
                                             'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                             'is_all_gender' => 'N',
                                             'is_all_age' => 'N'
                                            ]
                                    );

        $news3 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                             'object_type' => 'news',
                                             'link_object_type' => 'tenant', 
                                             'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                             'is_all_gender' => 'Y',
                                             'is_all_age' => 'N'
                                            ]
                                    );

        $news_merchant1 = Factory::create('NewsMerchant', ['news_id' => $news1->news_id, 'merchant_id' => $this->mallB->merchant_id]);
        $news_merchant2 = Factory::create('NewsMerchant', ['news_id' => $news2->news_id, 'merchant_id' => $this->mallB->merchant_id]);
        $news_merchant3 = Factory::create('NewsMerchant', ['news_id' => $news3->news_id, 'merchant_id' => $this->mallB->merchant_id]);

        $age_range1 = Factory::create('AgeRange', ['merchant_id' => $this->mallB->merchant_id, 'min_value' => 0, 'max_value' => 14]);
        $age_range2 = Factory::create('AgeRange', ['merchant_id' => $this->mallB->merchant_id, 'min_value' => 15, 'max_value' => 24]);
        $age_range3 = Factory::create('AgeRange', ['merchant_id' => $this->mallB->merchant_id, 'min_value' => 25, 'max_value' => 34]);

        $campaign_age1 = Factory::create('CampaignAge', ['campaign_type' => 'news', 'campaign_id' => $news1->news_id, 'age_range_id' => $age_range1->age_range_id]);
        $campaign_age2 = Factory::create('CampaignAge', ['campaign_type' => 'news', 'campaign_id' => $news2->news_id, 'age_range_id' => $age_range2->age_range_id]);
        $campaign_age3 = Factory::create('CampaignAge', ['campaign_type' => 'news', 'campaign_id' => $news3->news_id, 'age_range_id' => $age_range3->age_range_id]);

        // news1 is for female and news2 is for male
        $campaign_gender1 = Factory::create('CampaignGender', ['campaign_type' => 'news', 'campaign_id' => $news1->news_id, 'gender_value' => 'F']);
        $campaign_gender2 = Factory::create('CampaignGender', ['campaign_type' => 'news', 'campaign_id' => $news2->news_id, 'gender_value' => 'M']);

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

        $news1 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                                     'object_type' => 'news',
                                                     'link_object_type' => 'tenant', 
                                                     'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                                     'is_all_gender' => 'N',
                                                     'is_all_age' => 'N'
                                                    ]
                                            );

        $news2 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                             'object_type' => 'news',
                                             'link_object_type' => 'tenant', 
                                             'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                             'is_all_gender' => 'N',
                                             'is_all_age' => 'N'
                                            ]
                                    );

        $news3 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                             'object_type' => 'news',
                                             'link_object_type' => 'tenant', 
                                             'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                             'is_all_gender' => 'Y',
                                             'is_all_age' => 'N'
                                            ]
                                    );

        $news_merchant1 = Factory::create('NewsMerchant', ['news_id' => $news1->news_id, 'merchant_id' => $this->mallB->merchant_id]);
        $news_merchant2 = Factory::create('NewsMerchant', ['news_id' => $news2->news_id, 'merchant_id' => $this->mallB->merchant_id]);
        $news_merchant3 = Factory::create('NewsMerchant', ['news_id' => $news3->news_id, 'merchant_id' => $this->mallB->merchant_id]);

        $age_range1 = Factory::create('AgeRange', ['merchant_id' => $this->mallB->merchant_id, 'min_value' => 0, 'max_value' => 14]);
        $age_range2 = Factory::create('AgeRange', ['merchant_id' => $this->mallB->merchant_id, 'min_value' => 15, 'max_value' => 24]);
        $age_range3 = Factory::create('AgeRange', ['merchant_id' => $this->mallB->merchant_id, 'min_value' => 25, 'max_value' => 34]);

        $campaign_age1 = Factory::create('CampaignAge', ['campaign_type' => 'news', 'campaign_id' => $news1->news_id, 'age_range_id' => $age_range1->age_range_id]);
        $campaign_age2 = Factory::create('CampaignAge', ['campaign_type' => 'news', 'campaign_id' => $news2->news_id, 'age_range_id' => $age_range2->age_range_id]);
        $campaign_age3 = Factory::create('CampaignAge', ['campaign_type' => 'news', 'campaign_id' => $news3->news_id, 'age_range_id' => $age_range3->age_range_id]);

        // news1 is for female and news2 is for male
        $campaign_gender1 = Factory::create('CampaignGender', ['campaign_type' => 'news', 'campaign_id' => $news1->news_id, 'gender_value' => 'F']);
        $campaign_gender2 = Factory::create('CampaignGender', ['campaign_type' => 'news', 'campaign_id' => $news2->news_id, 'gender_value' => 'M']);

        $data = array('mall_id' => $this->mallB->merchant_id, 'object_type' => 'news');

        $response = $this->makeRequest($data, $apikey);

        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/Request OK/i', $response->message);

        $this->assertSame(1, $response->data->returned_records);
        $this->assertSame(1, $response->data->total_records);
        $this->assertSame(1, count($response->data->records));

        // the result should be news2
        $this->assertSame($news2->news_id, $response->data->records[0]->news_id);
        $this->assertSame($news2->news_name, $response->data->records[0]->news_name);
    }

    public function testGetNewsBasedOnTenant()
    {
        $user = Factory::create('user_consumer');
        $user_detail = Factory::create('UserDetail', [
            'user_id' => $user->user_id,
            'gender' => 'm'
        ]);

        $apikey = Factory::create('Apikey', ['user_id' => $user->user_id]);

        $tenant1 = Factory::create('Tenant', ['parent_id' => $this->mallB->merchant_id]);
        $tenant2 = Factory::create('Tenant', ['parent_id' => $this->mallA->merchant_id]);

        $news1 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                             'object_type' => 'news',
                                             'link_object_type' => 'tenant', 
                                             'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                             'is_all_gender' => 'Y',
                                             'is_all_age' => 'Y'
                                            ]
                                    );

        $news2 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                             'object_type' => 'news',
                                             'link_object_type' => 'tenant', 
                                             'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                             'is_all_gender' => 'Y',
                                             'is_all_age' => 'Y'
                                            ]
                                    );

        $news3 = Factory::create('News', ['mall_id' => $this->mallA->merchant_id, 
                                             'object_type' => 'news',
                                             'link_object_type' => 'tenant', 
                                             'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                             'is_all_gender' => 'Y',
                                             'is_all_age' => 'Y'
                                            ]
                                    );

        $news_merchant1 = Factory::create('NewsMerchant', ['news_id' => $news1->news_id, 'merchant_id' => $tenant1->merchant_id, 'object_type' => 'retailer']);
        $news_merchant2 = Factory::create('NewsMerchant', ['news_id' => $news2->news_id, 'merchant_id' => $tenant1->merchant_id, 'object_type' => 'retailer']);
        $news_merchant3 = Factory::create('NewsMerchant', ['news_id' => $news3->news_id, 'merchant_id' => $tenant2->merchant_id, 'object_type' => 'retailer']);

        // tenant1
        $data = array('mall_id' => $this->mallA->merchant_id, 'tenant_id' => $tenant2->merchant_id, 'object_type' => 'news');

        $response = $this->makeRequest($data, $apikey);

        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/Request OK/i', $response->message);

        $this->assertSame(1, $response->data->returned_records);
        $this->assertSame(1, $response->data->total_records);
        $this->assertSame(1, count($response->data->records));

        // tenant2
        $data = array('mall_id' => $this->mallB->merchant_id, 'tenant_id' => $tenant1->merchant_id, 'object_type' => 'news');

        $response = $this->makeRequest($data, $apikey);

        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/Request OK/i', $response->message);

        $this->assertSame(2, $response->data->returned_records);
        $this->assertSame(2, $response->data->total_records);
        $this->assertSame(2, count($response->data->records));
    }

    public function testTranslationNewsList() 
    {
        $language_en = Factory::create('Language', ['name' => 'en']);
        $language_id = Factory::create('Language', ['name' => 'id']);
        $language_jp = Factory::create('Language', ['name' => 'jp']);

        $merchant_language1 = Factory::create('MerchantLanguage', ['language_id' => $language_en->language_id, 'merchant_id' => $this->mallB->merchant_id]);
        $merchant_language2 = Factory::create('MerchantLanguage', ['language_id' => $language_id->language_id, 'merchant_id' => $this->mallB->merchant_id]);
        $merchant_language3 = Factory::create('MerchantLanguage', ['language_id' => $language_jp->language_id, 'merchant_id' => $this->mallB->merchant_id]);

        $news1 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                     'object_type' => 'news',
                                     'link_object_type' => 'tenant', 
                                     'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                     'is_all_gender' => 'Y',
                                     'is_all_age' => 'Y'
                                    ]
                            );

        $news_translation_en = Factory::create('NewsTranslation', ['news_id' => $news1->news_id, 
                                                                        'merchant_id' => $this->mallB->merchant_id, 
                                                                        'merchant_language_id' => $language_en->language_id]
                                                    );      

        $news_translation_id = Factory::create('NewsTranslation', ['news_id' => $news1->news_id, 
                                                                'merchant_id' => $this->mallB->merchant_id, 
                                                                'merchant_language_id' => $language_id->language_id]
                                            );  

        $news_translation_jp = Factory::create('NewsTranslation', ['news_id' => $news1->news_id, 
                                                                'merchant_id' => $this->mallB->merchant_id, 
                                                                'merchant_language_id' => $language_jp->language_id]
                                            );  

        $news_merchant_news_1 = Factory::create('NewsMerchant', ['news_id' => $news1->news_id, 'merchant_id' => $this->mallB->merchant_id, 'object_type' => 'mall']);

        // test translation english
        $data = array('mall_id' => $this->mallB->merchant_id, 'language_id' => $language_en->language_id, 'object_type' => 'news');

        $response = $this->makeRequest($data, $this->apikey);

        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/Request OK/i', $response->message);

        $this->assertSame($news_translation_en->news_id, $response->data->records[0]->news_id);
        $this->assertSame($news_translation_en->news_name, $response->data->records[0]->news_name);

        // test translation indonesia
        $data = array('mall_id' => $this->mallB->merchant_id, 'language_id' => $language_id->language_id, 'object_type' => 'news');

        $response = $this->makeRequest($data, $this->apikey);

        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/Request OK/i', $response->message);

        $this->assertSame($news_translation_id->news_id, $response->data->records[0]->news_id);
        $this->assertSame($news_translation_id->news_name, $response->data->records[0]->news_name);

        // test translation japan
        $data = array('mall_id' => $this->mallB->merchant_id, 'language_id' => $language_jp->language_id, 'object_type' => 'news');

        $response = $this->makeRequest($data, $this->apikey);

        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/Request OK/i', $response->message);

        $this->assertSame($news_translation_jp->news_id, $response->data->records[0]->news_id);
        $this->assertSame($news_translation_jp->news_name, $response->data->records[0]->news_name);
    }

    public function testExcludeNews()
    {
        // test exclude news by sending array of news id (ids)
        $user = Factory::create('user_consumer');
        $user_detail = Factory::create('UserDetail', [
            'user_id' => $user->user_id,
            'gender' => 'm'
        ]);

        $apikey = Factory::create('Apikey', ['user_id' => $user->user_id]);

        $tenant = Factory::create('Tenant', ['parent_id' => $this->mallB->merchant_id]);

        $news1 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                             'object_type' => 'news',
                                             'link_object_type' => 'tenant', 
                                             'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                             'is_all_gender' => 'Y',
                                             'is_all_age' => 'Y'
                                            ]
                                    );

        $news2 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                             'object_type' => 'news',
                                             'link_object_type' => 'tenant', 
                                             'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                             'is_all_gender' => 'Y',
                                             'is_all_age' => 'Y'
                                            ]
                                    );

        $news3 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                             'object_type' => 'news',
                                             'link_object_type' => 'tenant', 
                                             'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                             'is_all_gender' => 'Y',
                                             'is_all_age' => 'Y'
                                            ]
                                    );

        $news4 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                             'object_type' => 'news',
                                             'link_object_type' => 'tenant', 
                                             'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                             'is_all_gender' => 'Y',
                                             'is_all_age' => 'Y'
                                            ]
                                    );

        $news5 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                             'object_type' => 'news',
                                             'link_object_type' => 'tenant', 
                                             'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                             'is_all_gender' => 'Y',
                                             'is_all_age' => 'Y'
                                            ]
                                    );

        $news_merchant1 = Factory::create('NewsMerchant', ['news_id' => $news1->news_id, 'merchant_id' => $tenant->merchant_id, 'object_type' => 'retailer']);
        $news_merchant2 = Factory::create('NewsMerchant', ['news_id' => $news2->news_id, 'merchant_id' => $tenant->merchant_id, 'object_type' => 'retailer']);
        $news_merchant3 = Factory::create('NewsMerchant', ['news_id' => $news3->news_id, 'merchant_id' => $tenant->merchant_id, 'object_type' => 'retailer']);
        $news_merchant4 = Factory::create('NewsMerchant', ['news_id' => $news4->news_id, 'merchant_id' => $tenant->merchant_id, 'object_type' => 'retailer']);
        $news_merchant5 = Factory::create('NewsMerchant', ['news_id' => $news5->news_id, 'merchant_id' => $tenant->merchant_id, 'object_type' => 'retailer']);

        // no exclude
        $data = array('mall_id' => $this->mallB->merchant_id, 'object_type' => 'news');
        $response = $this->makeRequest($data, $this->apikey);
        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/Request OK/i', $response->message);
        $this->assertSame(5, count($response->data->records));
        
        // exclude news1
        $data = array('mall_id' => $this->mallB->merchant_id, 'ids' => array($news1->news_id), 'object_type' => 'news');
        $response = $this->makeRequest($data, $this->apikey);
        $this->assertSame(4, count($response->data->records));

        // exclude news1, news2
        $data = array('mall_id' => $this->mallB->merchant_id, 'ids' => array($news1->news_id, $news2->news_id), 'object_type' => 'news');
        $response = $this->makeRequest($data, $this->apikey);
        $this->assertSame(3, count($response->data->records));

        // exclude news1, news2, news3
        $data = array('mall_id' => $this->mallB->merchant_id, 'ids' => array($news1->news_id, $news2->news_id, $news3->news_id), 'object_type' => 'news');
        $response = $this->makeRequest($data, $this->apikey);
        $this->assertSame(2, count($response->data->records));

        // exclude news1, news2, news3, news4
        $data = array('mall_id' => $this->mallB->merchant_id, 'ids' => array($news1->news_id, $news2->news_id, $news3->news_id, $news4->news_id), 'object_type' => 'news');
        $response = $this->makeRequest($data, $this->apikey);
        $this->assertSame(1, count($response->data->records));

        // exclude news1, news2, news3, news4, news5
        $data = array('mall_id' => $this->mallB->merchant_id, 'ids' => array($news1->news_id, $news2->news_id, $news3->news_id, $news4->news_id, $news5->news_id), 'object_type' => 'news');
        $response = $this->makeRequest($data, $this->apikey);
        $this->assertSame(0, count($response->data->records));
    }

    public function testRandomResult()
    {
        // test random result (can't really test random just checking the content)
        $user = Factory::create('user_consumer');
        $user_detail = Factory::create('UserDetail', [
            'user_id' => $user->user_id,
            'gender' => 'm'
        ]);

        $apikey = Factory::create('Apikey', ['user_id' => $user->user_id]);

        $tenant = Factory::create('Tenant', ['parent_id' => $this->mallB->merchant_id]);

        $news1 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                             'object_type' => 'news',
                                             'link_object_type' => 'tenant', 
                                             'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                             'is_all_gender' => 'Y',
                                             'is_all_age' => 'Y'
                                            ]
                                    );

        $news2 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                             'object_type' => 'news',
                                             'link_object_type' => 'tenant', 
                                             'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                             'is_all_gender' => 'Y',
                                             'is_all_age' => 'Y'
                                            ]
                                    );

        $news3 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                             'object_type' => 'news',
                                             'link_object_type' => 'tenant', 
                                             'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                             'is_all_gender' => 'Y',
                                             'is_all_age' => 'Y'
                                            ]
                                    );

        $news4 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                             'object_type' => 'news',
                                             'link_object_type' => 'tenant', 
                                             'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                             'is_all_gender' => 'Y',
                                             'is_all_age' => 'Y'
                                            ]
                                    );

        $news5 = Factory::create('News', ['mall_id' => $this->mallB->merchant_id, 
                                             'object_type' => 'news',
                                             'link_object_type' => 'tenant', 
                                             'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                                             'is_all_gender' => 'Y',
                                             'is_all_age' => 'Y'
                                            ]
                                    );

        $news_merchant1 = Factory::create('NewsMerchant', ['news_id' => $news1->news_id, 'merchant_id' => $tenant->merchant_id, 'object_type' => 'retailer']);
        $news_merchant2 = Factory::create('NewsMerchant', ['news_id' => $news2->news_id, 'merchant_id' => $tenant->merchant_id, 'object_type' => 'retailer']);
        $news_merchant3 = Factory::create('NewsMerchant', ['news_id' => $news3->news_id, 'merchant_id' => $tenant->merchant_id, 'object_type' => 'retailer']);
        $news_merchant4 = Factory::create('NewsMerchant', ['news_id' => $news4->news_id, 'merchant_id' => $tenant->merchant_id, 'object_type' => 'retailer']);
        $news_merchant5 = Factory::create('NewsMerchant', ['news_id' => $news5->news_id, 'merchant_id' => $tenant->merchant_id, 'object_type' => 'retailer']);

        $data = array('mall_id' => $this->mallB->merchant_id, 'object_type' => 'news');

        $response = $this->makeRequest($data, $this->apikey);

        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/Request OK/i', $response->message);
        $this->assertSame(5, count($response->data->records));

        $result = [];
        foreach($response->data->records as $value) {
            $result[] = $value->news_name;
        }

        $news1_name = (in_array($news1->news_name, $result)) ? true : false;
        $news2_name = (in_array($news2->news_name, $result)) ? true : false;
        $news3_name = (in_array($news3->news_name, $result)) ? true : false;
        $news4_name = (in_array($news4->news_name, $result)) ? true : false;
        $news5_name = (in_array($news5->news_name, $result)) ? true : false;

        $this->assertTrue($news1_name);
        $this->assertTrue($news2_name);
        $this->assertTrue($news3_name);
        $this->assertTrue($news4_name);
        $this->assertTrue($news5_name);
    }
}