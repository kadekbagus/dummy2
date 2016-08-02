<?php
/**
 * PHP Unit Test for test list link to tenant - getCampaignLocation
 *
 * @author: Irianto Pratama <irianto@dominopos.com>
 */

use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;
use Faker\Factory as Faker;

class getCampaignLocationTest extends TestCase
{
    private $apiUrl = 'api/v1/tenant/campaignlocation';

    public function setUp()
    {
        parent::setUp();

        $this->apiKey = Factory::create('apikey_super_admin');

        Factory::create('role_mall_owner');
        $role_campaign_owner = Factory::create('role_campaign_owner');

        // country
        $this->country = Factory::create('Country');

        // account_types
        $this->account_type_mall      = Factory::create('account_type_mall');
        $this->account_type_merchant  = Factory::create('account_type_merchant');
        $this->account_type_agency    = Factory::create('account_type_agency');
        $this->account_type_3rd       = Factory::create('account_type_3rd');
        $this->account_type_dominopos = Factory::create('account_type_dominopos');

        // mall and tenant for list link to tenant
        $this->mall_a = $mall_a = Factory::create('Mall', ['name' => 'mall A']);
        $this->tenant_a = $tenant_a = Factory::create('Tenant', ['name' => 'tenant A', 'parent_id' => $mall_a->merchant_id]);

        $this->mall_b = $mall_b = Factory::create('Mall', ['name' => 'mall B']);
        $this->tenant_b1 = $tenant_b1 = Factory::create('Tenant', ['name' => 'tenant B1', 'parent_id' => $mall_b->merchant_id]);
        $this->tenant_b2 = $tenant_b2 = Factory::create('Tenant', ['name' => 'tenant B2', 'parent_id' => $mall_b->merchant_id]);

        $this->mall_c = $mall_c = Factory::create('Mall', ['name' => 'mall C']);
        $this->tenant_c = $tenant_c = Factory::create('Tenant', ['name' => 'tenant C', 'parent_id' => $mall_c->merchant_id]);

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

        $_GET = [];
        $_POST = [];
    }

    public function setRequestGetCampaignLocation($api_key, $api_secret_key, $filter)
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

    public function testGetCampaignLocationSuccess()
    {
        /*
        * 
        */
        $filter = [];

        $response = $this->setRequestGetCampaignLocation($this->apiKey->api_key, $this->apiKey->api_secret_key, $filter);
        $this->assertSame("Request OK", $response->message);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
    }

    public function testGetCampaignLocationFilterAccountTypeMall()
    {
        /*
        * 
        */
        $filter = [
            'account_type_id' => $this->account_type_mall->account_type_id,
        ];

        $response = $this->setRequestGetCampaignLocation($this->apiKey->api_key, $this->apiKey->api_secret_key, $filter);
        $this->assertSame("Request OK", $response->message);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);

        $list_mall = [];
        foreach ($response->data->records as $key => $mall) {
            array_push($list_mall, $mall->merchant_id);
        }

        // test get link to tenant object type mall
        $mall = CampaignLocation::where('status', '!=', 'deleted')
                        ->where('object_type', 'mall')
                        ->whereIn('merchant_id', $list_mall)
                        ->first();

        $this->assertSame(! empty($mall), true);

        // test unique link to tenant
        $list_avaliable_mall = [
            $this->mall_b->merchant_id,
        ];

        $list_not_avaliable_mall = [
            $this->mall_a->merchant_id,
            $this->mall_c->merchant_id,
        ];

        // avaliable mall
        foreach ($list_avaliable_mall as $key => $mall_id) {
            $this->assertSame(in_array($mall_id, $list_mall), true);
        }

        // not avaliable mall
        foreach ($list_not_avaliable_mall as $key => $mall_id) {
            $this->assertSame(! in_array($mall_id, $list_mall), true);
        }
    }

    public function testGetCampaignLocationFilterAccountTypeMerchant()
    {
        /*
        * 
        */
        $filter = [
            'account_type_id' => $this->account_type_merchant->account_type_id,
        ];

        $response = $this->setRequestGetCampaignLocation($this->apiKey->api_key, $this->apiKey->api_secret_key, $filter);
        $this->assertSame("Request OK", $response->message);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);

        $list_tenant = [];
        foreach ($response->data->records as $key => $tenant) {
            array_push($list_tenant, $tenant->merchant_id);
        }

        // test get link to tenant object type tenant
        $tenant = CampaignLocation::where('status', '!=', 'deleted')
                        ->where('object_type', 'tenant')
                        ->whereIn('merchant_id', $list_tenant)
                        ->first();

        $this->assertSame(! empty($tenant), true);

        // test unique link to tenant
        $list_avaliable_tenant = [
            $this->tenant_b1->merchant_id,
            $this->tenant_b2->merchant_id,
        ];

        $list_not_avaliable_tenant = [
            $this->tenant_a->merchant_id,
            $this->tenant_c->merchant_id,
        ];

        // avaliable tenant
        foreach ($list_avaliable_tenant as $key => $tenant_id) {
            $this->assertSame(in_array($tenant_id, $list_tenant), true);
        }

        // not avaliable tenant
        foreach ($list_not_avaliable_tenant as $key => $tenant_id) {
            $this->assertSame(! in_array($tenant_id, $list_tenant), true);
        }
    }

    public function testGetCampaignLocationFilterAccountTypeAgency()
    {
        /*
        * 
        */
        $filter = [
            'account_type_id' => $this->account_type_agency->account_type_id,
        ];

        $response = $this->setRequestGetCampaignLocation($this->apiKey->api_key, $this->apiKey->api_secret_key, $filter);
        $this->assertSame("Request OK", $response->message);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);

        $list_mall_tenant = [];
        foreach ($response->data->records as $key => $mall_tenant) {
            array_push($list_mall_tenant, $mall_tenant->merchant_id);
        }

        // test get link to tenant object type mall
        $mall_tenant = CampaignLocation::where('status', '!=', 'deleted')
                        ->where('object_type', 'mall')
                        ->whereIn('merchant_id', $list_mall_tenant)
                        ->first();

        $this->assertSame(! empty($mall_tenant), true);

        // test unique link to tenant
        $list_avaliable_mall_tenant = [
            $this->mall_b->merchant_id,
            $this->tenant_b1->merchant_id,
            $this->tenant_b2->merchant_id,
        ];

        $list_not_avaliable_mall_tenant = [
            $this->mall_a->merchant_id,
            $this->mall_c->merchant_id,
            $this->tenant_a->merchant_id,
            $this->tenant_c->merchant_id,
        ];

        // avaliable mall and tenant
        foreach ($list_avaliable_mall_tenant as $key => $mall_tenant_id) {
            $this->assertSame(in_array($mall_tenant_id, $list_mall_tenant), true);
        }

        // not avaliable mall and tenant
        foreach ($list_not_avaliable_mall_tenant as $key => $mall_tenant_id) {
            $this->assertSame(! in_array($mall_tenant_id, $list_mall_tenant), true);
        }
    }

    public function testGetCampaignLocationFilterAccountType3rd()
    {
        /*
        * 
        */
        $filter = [
            'account_type_id' => $this->account_type_3rd->account_type_id,
        ];

        $response = $this->setRequestGetCampaignLocation($this->apiKey->api_key, $this->apiKey->api_secret_key, $filter);
        $this->assertSame("Request OK", $response->message);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);

        $list_mall = [];
        foreach ($response->data->records as $key => $mall) {
            array_push($list_mall, $mall->merchant_id);
        }

        // test get link to tenant object type mall
        $mall = CampaignLocation::where('status', '!=', 'deleted')
                        ->where('object_type', 'mall')
                        ->whereIn('merchant_id', $list_mall)
                        ->first();

        $this->assertSame(! empty($mall), true);

        // test unique link to tenant
        $list_avaliable_mall = [
            $this->mall_a->merchant_id,
            $this->mall_b->merchant_id,
            $this->mall_c->merchant_id,
        ];

        $list_not_avaliable_mall = [
            $this->tenant_a->merchant_id,
            $this->tenant_b1->merchant_id,
            $this->tenant_b2->merchant_id,
            $this->tenant_c->merchant_id,
        ];

        // avaliable mall
        foreach ($list_avaliable_mall as $key => $mall_id) {
            $this->assertSame(in_array($mall_id, $list_mall), true);
        }

        // not avaliable mall
        foreach ($list_not_avaliable_mall as $key => $mall_id) {
            $this->assertSame(! in_array($mall_id, $list_mall), true);
        }
    }

    public function testGetCampaignLocationFilterAccountTypeDominopos()
    {
        /*
        * 
        */
        $filter = [
            'account_type_id' => $this->account_type_dominopos->account_type_id,
        ];

        $response = $this->setRequestGetCampaignLocation($this->apiKey->api_key, $this->apiKey->api_secret_key, $filter);
        $this->assertSame("Request OK", $response->message);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);

        $list_mall_tenant = [];
        foreach ($response->data->records as $key => $mall_tenant) {
            array_push($list_mall_tenant, $mall_tenant->merchant_id);
        }

        // test get link to tenant object type mall
        $mall_tenant = CampaignLocation::where('status', '!=', 'deleted')
                        ->where('object_type', 'mall')
                        ->whereIn('merchant_id', $list_mall_tenant)
                        ->first();

        $this->assertSame(! empty($mall_tenant), true);

        // test unique link to tenant
        $list_avaliable_mall_tenant = [
            $this->mall_b->merchant_id,
            $this->tenant_b1->merchant_id,
            $this->tenant_b2->merchant_id,
            $this->mall_a->merchant_id,
            $this->mall_c->merchant_id,
            $this->tenant_a->merchant_id,
            $this->tenant_c->merchant_id,
        ];

        $list_not_avaliable_mall_tenant = [
        ];

        // avaliable mall and tenant
        foreach ($list_avaliable_mall_tenant as $key => $mall_tenant_id) {
            $this->assertSame(in_array($mall_tenant_id, $list_mall_tenant), true);
        }

        // not avaliable mall and tenant
        foreach ($list_not_avaliable_mall_tenant as $key => $mall_tenant_id) {
            $this->assertSame(! in_array($mall_tenant_id, $list_mall_tenant), true);
        }
    }
}