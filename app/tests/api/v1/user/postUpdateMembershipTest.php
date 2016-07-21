<?php
/**
 * PHP Unit Test for Mall API Controller postUpdateMembership
 *
 * @author: Irianto Pratama <irianto@dominopos.com>
 */

use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;
use Faker\Factory as Faker;

class postUpdateMembershipTest extends TestCase
{
    private $apiUrlUpdate = 'api/v1/membership/update';

    public function setUp()
    {
        parent::setUp();

        // create mall a
        $this->mall_a = Factory::Create('Mall');
        Factory::Create('enable_membership_card', ['object_id' => $this->mall_a->merchant_id]);
        Factory::Create('Membership', ['merchant_id' => $this->mall_a->merchant_id]);

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

    public function setRequestPostUpdateMembership($api_key, $api_secret_key, $update)
    {
        // Set the client API Keys
        $_GET['apikey'] = $api_key;
        $_GET['apitimestamp'] = time();

        foreach ($update as $field => $value) {
            $_POST[$field] = $value;
        }
        $url = $this->apiUrlUpdate . '?' . http_build_query($_GET);

        $secretKey = $api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $json = $this->call('POST', $url)->getContent();
        $response = json_decode($json);

        return $response;
    }

    public function testUpdateListCategories()
    {
        // create user_a
        $user_a = Factory::Create('user_consumer');
        $user_detail_a = Factory::Create('UserDetail', ['user_id' => $user_a->user_id, 'retailer_id' => $this->mall_a->merchant_id, 'merchant_id' => $this->mall_a->merchant_id]);

        Factory::Create('UserAcquisition', ['user_id' => $user_a->user_id, 'acquirer_id' => $this->mall_a->merchant_id]);

        // create category
        $category_a = Factory::Create('Category', ['category_name' => 'home']);
        $category_b = Factory::Create('Category', ['category_name' => 'ball']);
        $category_c = Factory::Create('Category', ['category_name' => 'phone']);
        $category_d = Factory::Create('Category', ['category_name' => 'computer']);

        // link category to user
        Factory::Create('user_category_interest', ['user_id' => $user_a->user_id, 'personal_interest_id' => $category_a->category_id]);
        Factory::Create('user_category_interest', ['user_id' => $user_a->user_id, 'personal_interest_id' => $category_b->category_id]);
        Factory::Create('user_category_interest', ['user_id' => $user_a->user_id, 'personal_interest_id' => $category_c->category_id]);

        // update name categories
        $apiKeyCs = $this->apiKeyCs;

        $data = [
            'user_id' => $user_a->user_id,
            'category_ids' => [
                $category_a->category_id,
                $category_c->category_id
            ]
        ];

        $response = $this->setRequestPostUpdateMembership($apiKeyCs->api_key, $apiKeyCs->api_secret_key, $data);

        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
        $this->assertSame(2, count($response->data->categories));

        $list_category_link_to_user = ['home', 'phone'];
        foreach ($response->data->categories as $idx => $category) {
            foreach ($list_category_link_to_user as $_idx => $_category) {
                if ($idx === $_idx) {
                    $this->assertSame($category->category_name, $_category);
                }
            }
        }
    }
}