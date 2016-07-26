<?php
/**
 * PHP Unit Test for Account API Controller postUpdateAccount
 *
 * @author: Irianto Pratama <irianto@dominopos.com>
 */

use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;
use Faker\Factory as Faker;

class postUpdateAccountTest extends TestCase
{
    private $apiUrl = 'api/v1/account/update';

    public function setUp()
    {
        parent::setUp();

        $this->apiKey = Factory::create('apikey_super_admin');

        Factory::create('role_mall_owner');

        // country
        $this->country = Factory::create('Country');

        // account_types
        $this->account_type_mall      = Factory::create('account_type_mall');
        $this->account_type_merchant  = Factory::create('account_type_merchant');
        $this->account_type_agency    = Factory::create('account_type_agency');
        $this->account_type_3rd       = Factory::create('account_type_3rd');
        $this->account_type_dominopos = Factory::create('account_type_dominopos');

        // mall and tenant for list link to tenant
        $this->mall_a = $mall_a = Factory::create('Mall');
        $this->tenant_a = $tenant_a = Factory::create('Tenant', ['parent_id' => $mall_a->merchant_id]);

        $this->mall_b = $mall_b = Factory::create('Mall');
        $this->tenant_b1 = $tenant_b1 = Factory::create('Tenant', ['parent_id' => $mall_b->merchant_id]);
        $this->tenant_b2 = $tenant_b2 = Factory::create('Tenant', ['parent_id' => $mall_b->merchant_id]);

        // base user
        $this->pmp_user = Factory::create('campaign_owner');
        $this->pmp_user_detail = Factory::create('UserDetail', ['user_id' => $this->pmp_user->user_id]);
        $this->pmp_campaign_account = Factory::create('CampaignAccount', ['user_id' => $this->pmp_user->user_id, 'parent_user_id' => NULL]);
        $this->pmp_employee = Factory::create('Employee', ['user_id' => $this->pmp_user->user_id]);

        $_GET = [];
        $_POST = [];
    }

    public function setRequestPostUpdateAccount($api_key, $api_secret_key, $update_data)
    {
        // Set the client API Keys
        $_GET['apikey'] = $api_key;
        $_GET['apitimestamp'] = time();

        foreach ($update_data as $field => $value) {
            $_POST[$field] = $value;
        }
        $url = $this->apiUrl . '?' . http_build_query($_GET);

        $secretKey = $api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $json = $this->call('POST', $url)->getContent();
        $response = json_decode($json);

        return $response;
    }

    public function testRequiredUserId()
    {
        /*
        * test user_id is required
        */
        $data = [];

        $response = $this->setRequestPostUpdateAccount($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("The id field is required", $response->message);
        $this->assertSame(NULL, $response->data);
    }

    public function testUpdatePMPAccountSuccess()
    {
        $data = [
            'id'              => $this->pmp_user->user_id,
            'user_firstname'  => 'irianto',
            'user_lastname'   => 'pratama',
            'user_email'      => 'pmpsatu@campaignowner.com',
            'account_name'    => 'PMP Satu',
            'company_name'    => 'Domino Mall',
            'address_line1'   => 'Jl. Gunung Salak 31 A',
            'city'            => 'Badung',
            'country_id'      => $this->country->country_id,
            'merchant_ids'    => [$this->mall_a->merchant_id, $this->mall_b->merchant_id],
            'account_type_id' => $this->account_type_mall->account_type_id,
            'role_name'       => 'Campaign Owner',
        ];

        $response = $this->setRequestPostUpdateAccount($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame("Request OK", $response->message);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
        $this->assertSame('irianto', $response->data->user_firstname);

        $account_type = CampaignAccount::where('user_id', $response->data->user_id)
                                ->first();

        $this->assertSame('', $account_type->account_type_id);
    }
}