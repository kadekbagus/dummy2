<?php
/**
 * PHP Unit Test for User API Controller getConsumerListing
 *
 * @author: Irianto Pratama <irianto@dominopos.com>
 */

use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;
use Faker\Factory as Faker;

class getConsumerListingTestArtemisVersion extends TestCase
{
    private $apiUrlList = 'api/v1/consumer/search';

    public function setUp()
    {
        parent::setUp();

        // create mall a
        $this->mall_a = Factory::Create('Mall');

        // create cs
        $this->user_cs = Factory::Create('user_mall_customer_service');
        $this->apiKeyCs = Factory::Create('apikey_mall_customer_service', ['user_id' => $this->user_cs->user_id]);

        // cs to employee of mall_a
        $this->employee_cs = Factory::Create('Employee', ['user_id' => $this->user_cs->user_id]);

        $dbPrefix = DB::getTablePrefix();

        // Insert dummy data on employee retailer
        DB::statement("INSERT INTO `{$dbPrefix}employee_retailer`
                (`employee_retailer_id`,`employee_id`, `retailer_id`,`created_at`, `updated_at`)
                VALUES
                ('1', '{$this->employee_cs->employee_id}', '{$this->mall_a->merchant_id}', NOW(), NOW())"
        );

        // set get and post
        $_GET = [];
        $_POST = [];
    }

    public function setRequestgetConsumerListing($api_key, $api_secret_key, $filter)
    {
        // Set the client API Keys
        $_GET['apikey'] = $api_key;
        $_GET['apitimestamp'] = time();

        foreach ($filter as $field => $value) {
            $_GET[$field] = $value;
        }
        $url = $this->apiUrlList . '?' . http_build_query($_GET);

        $secretKey = $api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $json = $this->call('GET', $url)->getContent();
        $response = json_decode($json);

        return $response;
    }

    public function testGetConsumerListingWithCategories()
    {
        // create user_a
        $user_a = Factory::Create('user_consumer');
        $user_detail_a = Factory::Create('UserDetail', ['user_id' => $user_a->user_id, 'retailer_id' => $this->mall_a->merchant_id, 'merchant_id' => $this->mall_a->merchant_id]);

        Factory::Create('UserAcquisition', ['user_id' => $user_a->user_id, 'acquirer_id' => $this->mall_a->merchant_id]);

        // create category
        $categorty_a = Factory::Create('Category', ['category_name' => 'home']);
        $categorty_b = Factory::Create('Category', ['category_name' => 'ball']);
        $categorty_c = Factory::Create('Category', ['category_name' => 'phone']);
        $categorty_d = Factory::Create('Category', ['category_name' => 'computer']);

        // link category to user
        Factory::Create('user_category_interest', ['user_id' => $user_a->user_id, 'personal_interest_id' => $categorty_a->category_id]);
        Factory::Create('user_category_interest', ['user_id' => $user_a->user_id, 'personal_interest_id' => $categorty_b->category_id]);
        Factory::Create('user_category_interest', ['user_id' => $user_a->user_id, 'personal_interest_id' => $categorty_c->category_id]);

        // search user with link category
        $apiKeyCs = $this->apiKeyCs;

        $filter = [
            'from_cs' => 'yes',
            'merchant_id' => [$this->mall_a->merchant_id],
            'with' => ['categories']
        ];

        $response_search = $this->setRequestgetConsumerListing($apiKeyCs->api_key, $apiKeyCs->api_secret_key, $filter);

        $this->assertSame(0, $response_search->code);
        $this->assertSame("success", $response_search->status);
        $this->assertSame(3, count($response_search->data->records[0]->categories));

        $list_category_link_to_user = ['home', 'ball', 'phone'];
        foreach ($response_search->data->records[0]->categories as $idx => $category) {
            foreach ($list_category_link_to_user as $_idx => $_category) {
                if ($idx === $_idx) {
                    $this->assertSame($category->category_name, $_category);
                }
            }
        }
    }

    public function testGetConsumerListingWithExcludeDeletedCategories()
    {
        // create user_a
        $user_a = Factory::Create('user_consumer');
        $user_detail_a = Factory::Create('UserDetail', ['user_id' => $user_a->user_id, 'retailer_id' => $this->mall_a->merchant_id, 'merchant_id' => $this->mall_a->merchant_id]);

        Factory::Create('UserAcquisition', ['user_id' => $user_a->user_id, 'acquirer_id' => $this->mall_a->merchant_id]);

        // create category
        $categorty_a = Factory::Create('Category', ['category_name' => 'home']);
        $categorty_b = Factory::Create('Category', ['category_name' => 'ball']);
        $categorty_c = Factory::Create('Category', ['category_name' => 'phone', 'status' => 'deleted']);
        $categorty_d = Factory::Create('Category', ['category_name' => 'computer']);

        // link category to user
        Factory::Create('user_category_interest', ['user_id' => $user_a->user_id, 'personal_interest_id' => $categorty_a->category_id]);
        Factory::Create('user_category_interest', ['user_id' => $user_a->user_id, 'personal_interest_id' => $categorty_b->category_id]);
        Factory::Create('user_category_interest', ['user_id' => $user_a->user_id, 'personal_interest_id' => $categorty_c->category_id]);

        // search user with link category
        $apiKeyCs = $this->apiKeyCs;

        $filter = [
            'from_cs' => 'yes',
            'merchant_id' => [$this->mall_a->merchant_id],
            'with' => ['categories']
        ];

        $response_search = $this->setRequestgetConsumerListing($apiKeyCs->api_key, $apiKeyCs->api_secret_key, $filter);

        $this->assertSame(0, $response_search->code);
        $this->assertSame("success", $response_search->status);
        $this->assertSame(2, count($response_search->data->records[0]->categories));

        $list_category_link_to_user = ['home', 'ball'];
        foreach ($response_search->data->records[0]->categories as $idx => $category) {
            foreach ($list_category_link_to_user as $_idx => $_category) {
                if ($idx === $_idx) {
                    $this->assertSame($category->category_name, $_category);
                }
            }
        }
    }
}