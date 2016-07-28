<?php
/**
 * PHP Unit Test for Account API Controller getAccount
 *
 * @author: Irianto Pratama <irianto@dominopos.com>
 */

use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;
use Faker\Factory as Faker;

class getAccountTest extends TestCase
{
    private $apiUrl = 'api/v1/account/list';

    public function setUp()
    {
        parent::setUp();

        $this->apiKey = Factory::create('apikey_super_admin');

        Factory::create('role_mall_owner');
        $role_campaign_owner = Factory::create('role_campaign_owner');
        $role_campaign_employee = Factory::create('role_campaign_employee');

        // country
        $this->country = Factory::create('Country');

        // account_types
        $this->account_type_mall      = Factory::create('account_type_mall');
        $this->account_type_merchant  = Factory::create('account_type_merchant');
        $this->account_type_agency    = Factory::create('account_type_agency');
        $this->account_type_3rd       = Factory::create('account_type_3rd');
        $this->account_type_dominopos = Factory::create('account_type_dominopos');

        // mall and tenant for list link to tenant
        $this->mall_a = $mall_a = Factory::create('Mall', ['name' => 'Mall A']);
        $this->tenant_a = $tenant_a = Factory::create('Tenant', ['name' => 'Tenant A', 'parent_id' => $mall_a->merchant_id]);

        $this->mall_b = $mall_b = Factory::create('Mall', ['name' => 'Mall B']);
        $this->tenant_b1 = $tenant_b1 = Factory::create('Tenant', ['name' => 'Tenant B1', 'parent_id' => $mall_b->merchant_id]);
        $this->tenant_b2 = $tenant_b2 = Factory::create('Tenant', ['name' => 'Tenant B2', 'parent_id' => $mall_b->merchant_id]);

        $this->mall_c = $mall_c = Factory::create('Mall', ['name' => 'Mall C']);
        $this->tenant_c = $tenant_c = Factory::create('Tenant', ['name' => 'Tenant C', 'parent_id' => $mall_c->merchant_id]);

        // pmp_mall link to mall a
        $this->pmp_mall_user = Factory::create('User', [
                'user_role_id' => $role_campaign_owner->role_id
            ]);

        $this->pmp_mall_user_detail = Factory::create('UserDetail',[
                'user_id' => $this->pmp_mall_user->user_id
            ]);
        $this->pmp_mall_campaign_account = Factory::create('CampaignAccount', [
                'user_id' => $this->pmp_mall_user->user_id,
                'parent_user_id' => NULL,
                'is_link_to_all' => 'N',
                'account_type_id' => $this->account_type_mall->account_type_id
            ]);

        $this->pmp_mall_employee = Factory::create('Employee', [
                'user_id' => $this->pmp_mall_user->user_id
            ]);

        $this->pmp_mall_user_merchant = Factory::create('UserMerchant', [
                'user_id'     => $this->pmp_mall_user->user_id,
                'merchant_id' => $this->mall_a->merchant_id,
                'object_type' => 'mall'
            ]);

        // pmp_employee_mall link to mall a
        $this->pmp_employee_mall_user = Factory::create('User', [
                'user_role_id' => $role_campaign_employee->role_id
            ]);

        $this->pmp_employee_mall_user_detail = Factory::create('UserDetail',[
                'user_id' => $this->pmp_employee_mall_user->user_id
            ]);
        $this->pmp_employee_mall_campaign_account = Factory::create('CampaignAccount', [
                'user_id' => $this->pmp_employee_mall_user->user_id,
                'parent_user_id' => $this->pmp_mall_user->user_id,
                'is_link_to_all' => 'N',
                'account_type_id' => $this->pmp_mall_campaign_account->account_type_id
            ]);

        $this->pmp_employee_mall_employee = Factory::create('Employee', [
                'user_id' => $this->pmp_employee_mall_user->user_id
            ]);

        $this->pmp_employee_mall_user_merchant = Factory::create('UserMerchant', [
                'user_id'     => $this->pmp_employee_mall_user->user_id,
                'merchant_id' => $this->mall_a->merchant_id,
                'object_type' => 'mall'
            ]);

        // pmp_merchant link to tenant_a
        $this->pmp_merchant_user = Factory::create('User', [
                'user_role_id' => $role_campaign_owner->role_id
            ]);

        $this->pmp_merchant_user_detail = Factory::create('UserDetail',[
                'user_id' => $this->pmp_merchant_user->user_id
            ]);
        $this->pmp_merchant_campaign_account = Factory::create('CampaignAccount', [
                'user_id' => $this->pmp_merchant_user->user_id,
                'parent_user_id' => NULL,
                'is_link_to_all' => 'N',
                'account_type_id' => $this->account_type_merchant->account_type_id
            ]);

        $this->pmp_merchant_employee = Factory::create('Employee', [
                'user_id' => $this->pmp_merchant_user->user_id
            ]);

        $this->pmp_merchant_user_merchant = Factory::create('UserMerchant', [
                'user_id'     => $this->pmp_merchant_user->user_id,
                'merchant_id' => $this->tenant_a->merchant_id,
                'object_type' => 'tenant'
            ]);

        // pmp_agency link to mall and tenant c
        $this->pmp_agency_user = Factory::create('User', [
                'user_role_id' => $role_campaign_owner->role_id
            ]);

        $this->pmp_agency_user_detail = Factory::create('UserDetail',[
                'user_id' => $this->pmp_agency_user->user_id
            ]);
        $this->pmp_agency_campaign_account = Factory::create('CampaignAccount', [
                'user_id' => $this->pmp_agency_user->user_id,
                'parent_user_id' => NULL,
                'is_link_to_all' => 'N',
                'account_type_id' => $this->account_type_agency->account_type_id
            ]);

        $this->pmp_agency_employee = Factory::create('Employee', [
                'user_id' => $this->pmp_agency_user->user_id
            ]);

        $this->pmp_agency_user_merchant = Factory::create('UserMerchant', [
                'user_id'     => $this->pmp_agency_user->user_id,
                'merchant_id' => $this->mall_c->merchant_id,
                'object_type' => 'mall'
            ]);

        $this->pmp_agency_user_merchant = Factory::create('UserMerchant', [
                'user_id'     => $this->pmp_agency_user->user_id,
                'merchant_id' => $this->tenant_c->merchant_id,
                'object_type' => 'tenant'
            ]);

        // pmp_3rd link to mall b
        $this->pmp_3rd_user = Factory::create('User', [
                'user_role_id' => $role_campaign_owner->role_id
            ]);

        $this->pmp_3rd_user_detail = Factory::create('UserDetail',[
                'user_id' => $this->pmp_3rd_user->user_id
            ]);
        $this->pmp_3rd_campaign_account = Factory::create('CampaignAccount', [
                'user_id' => $this->pmp_3rd_user->user_id,
                'parent_user_id' => NULL,
                'is_link_to_all' => 'N',
                'account_type_id' => $this->account_type_3rd->account_type_id
            ]);

        $this->pmp_3rd_employee = Factory::create('Employee', [
                'user_id' => $this->pmp_3rd_user->user_id
            ]);

        $this->pmp_3rd_user_merchant = Factory::create('UserMerchant', [
                'user_id'     => $this->pmp_3rd_user->user_id,
                'merchant_id' => $this->mall_b->merchant_id,
                'object_type' => 'mall'
            ]);

        // pmp_dominopos link to tenant_b
        $this->pmp_dominopos_user = Factory::create('User', [
                'user_role_id' => $role_campaign_owner->role_id
            ]);

        $this->pmp_dominopos_user_detail = Factory::create('UserDetail',[
                'user_id' => $this->pmp_dominopos_user->user_id
            ]);
        $this->pmp_dominopos_campaign_account = Factory::create('CampaignAccount', [
                'user_id' => $this->pmp_dominopos_user->user_id,
                'parent_user_id' => NULL,
                'is_link_to_all' => 'N',
                'account_type_id' => $this->account_type_dominopos->account_type_id
            ]);

        $this->pmp_dominopos_employee = Factory::create('Employee', [
                'user_id' => $this->pmp_dominopos_user->user_id
            ]);

        $this->pmp_dominopos_user_merchant = Factory::create('UserMerchant', [
                'user_id'     => $this->pmp_dominopos_user->user_id,
                'merchant_id' => $this->tenant_b1->merchant_id,
                'object_type' => 'tenant'
            ]);

        // pmp_dominopos select all link to tenants
        $this->pmp_dominopos_all_link_user = Factory::create('User', [
                'user_role_id' => $role_campaign_owner->role_id
            ]);

        $this->pmp_dominopos_all_link_user_detail = Factory::create('UserDetail',[
                'user_id' => $this->pmp_dominopos_all_link_user->user_id
            ]);
        $this->pmp_dominopos_all_link_campaign_account = Factory::create('CampaignAccount', [
                'user_id' => $this->pmp_dominopos_all_link_user->user_id,
                'parent_user_id' => NULL,
                'is_link_to_all' => 'Y',
                'account_type_id' => $this->account_type_dominopos->account_type_id
            ]);

        $this->pmp_dominopos_all_link_employee = Factory::create('Employee', [
                'user_id' => $this->pmp_dominopos_all_link_user->user_id
            ]);

        // pmp_3rd select all link to tenants
        $this->pmp_3rd_all_link_user = Factory::create('User', [
                'user_role_id' => $role_campaign_owner->role_id
            ]);

        $this->pmp_3rd_all_link_user_detail = Factory::create('UserDetail',[
                'user_id' => $this->pmp_3rd_all_link_user->user_id
            ]);
        $this->pmp_3rd_all_link_campaign_account = Factory::create('CampaignAccount', [
                'user_id' => $this->pmp_3rd_all_link_user->user_id,
                'parent_user_id' => NULL,
                'is_link_to_all' => 'Y',
                'account_type_id' => $this->account_type_3rd->account_type_id
            ]);

        $this->pmp_3rd_all_link_employee = Factory::create('Employee', [
                'user_id' => $this->pmp_3rd_all_link_user->user_id
            ]);

        $_GET = [];
        $_POST = [];
    }

    public function setRequestGetAccount($api_key, $api_secret_key, $filter)
    {
        // Set the client API Keys
        $_GET['apikey'] = $api_key;
        $_GET['apitimestamp'] = time();

        foreach ($filter as $field => $value) {
            $_GET[$field] = $value;
        }
        $url = $this->apiUrl . '?' . http_build_query($_GET);

        $secretKey = $api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $json = $this->call('GET', $url)->getContent();
        $response = json_decode($json);

        return $response;
    }

    public function testGetAccountSuccess()
    {
        /*
        * 
        */
        $filter = [
            'take' => 15,
        ];

        $response = $this->setRequestGetAccount($this->apiKey->api_key, $this->apiKey->api_secret_key, $filter);
        $this->assertSame("Request OK", $response->message);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
    }

    public function testGetAccountDominoposSelectAllTenant()
    {
        /*
        * 
        */
        $filter = [
            'take' => 15,
        ];

        $response = $this->setRequestGetAccount($this->apiKey->api_key, $this->apiKey->api_secret_key, $filter);
        $this->assertSame("Request OK", $response->message);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);

        foreach ($response->data->records as $key => $pmp_account) {
            if ($pmp_account->select_all_tenants === 'Y' && $pmp_account->type_name === 'Dominopos') {
                $mall_tenant = CampaignLocation::where('status', '!=', 'deleted')
                                            ->whereIn('object_type', ['mall', 'tenant'])
                                            ->get();
                $this->assertSame($pmp_account->tenant_count, count($mall_tenant));
            }
        }
    }


    public function testGetAccount3rdSelectAllTenant()
    {
        /*
        * 
        */
        $filter = [
            'take' => 15,
        ];

        $response = $this->setRequestGetAccount($this->apiKey->api_key, $this->apiKey->api_secret_key, $filter);
        $this->assertSame("Request OK", $response->message);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);

        foreach ($response->data->records as $key => $pmp_account) {
            if ($pmp_account->select_all_tenants === 'Y' && $pmp_account->type_name === '3rd Party') {
                $mall = CampaignLocation::where('status', '!=', 'deleted')
                                            ->where('object_type', 'mall')
                                            ->get();
                $this->assertSame($pmp_account->tenant_count, count($mall));
            }
        }
    }
}