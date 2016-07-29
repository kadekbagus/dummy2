<?php
/**
 * PHP Unit Test for Account API Controller postNewAccount
 *
 * @author: Irianto Pratama <irianto@dominopos.com>
 */

use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;
use Faker\Factory as Faker;

class postNewAccountTest extends TestCase
{
    private $apiUrl = 'api/v1/account/new';

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
        $this->pmp_dominoposs_user = Factory::create('User', [
                'user_role_id' => $role_campaign_owner->role_id
            ]);

        $this->pmp_dominoposs_user_detail = Factory::create('UserDetail',[
                'user_id' => $this->pmp_dominoposs_user->user_id
            ]);
        $this->pmp_dominoposs_campaign_account = Factory::create('CampaignAccount', [
                'user_id' => $this->pmp_dominoposs_user->user_id,
                'parent_user_id' => NULL,
                'account_type_id' => $this->account_type_dominopos->account_type_id
            ]);

        $this->pmp_dominoposs_employee = Factory::create('Employee', [
                'user_id' => $this->pmp_dominoposs_user->user_id
            ]);

        $this->pmp_dominoposs_user_merchant = Factory::create('UserMerchant', [
                'user_id'     => $this->pmp_dominoposs_user->user_id,
                'merchant_id' => $this->tenant_b1->merchant_id,
                'object_type' => 'tenant'
            ]);

        $_GET = [];
        $_POST = [];
    }

    public function setRequestPostNewAccount($api_key, $api_secret_key, $new_data)
    {
        // Set the client API Keys
        $_GET['apikey'] = $api_key;
        $_GET['apitimestamp'] = time();

        foreach ($new_data as $field => $value) {
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

    public function testRequiredAccountTypeId()
    {
        /*
        * test account type id is required
        */
        $data = [];

        $response = $this->setRequestPostNewAccount($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("The account type id field is required", $response->message);
        $this->assertSame(NULL, $response->data);
    }

    public function testCreatePMPAccountSuccess()
    {
        /*
        * test create pmp account success
        */
        $data = [
            'account_type_id' => $this->account_type_mall->account_type_id,
            'user_firstname'  => 'irianto',
            'user_lastname'   => 'pratama',
            'user_email'      => 'pmpsatu@campaignowner.com',
            'account_name'    => 'PMP Satu',
            'status'          => 'active',
            'company_name'    => 'Domino Mall',
            'address_line1'   => 'Jl. Gunung Salak 31 A',
            'city'            => 'Badung',
            'country_id'      => $this->country->country_id,
            'merchant_ids'    => [$this->mall_b->merchant_id],
            'user_password'   => '123456',
            'role_name'       => 'Campaign Owner',
        ];

        $response = $this->setRequestPostNewAccount($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame("Request OK", $response->message);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
        $this->assertSame('irianto', $response->data->user_firstname);

        $account_type = CampaignAccount::where('user_id', $response->data->user_id)
                                ->first();

        $this->assertSame($this->account_type_mall->account_type_id, $account_type->account_type_id);
    }

    public function testAccountTypeMallFailedLinkHasExists()
    {
        /*
        * test pmp account with account type mall failed cause mall has link to other account type
        */
        $data = [
            'account_type_id' => $this->account_type_mall->account_type_id,
            'user_firstname'  => 'irianto',
            'user_lastname'   => 'pratama',
            'user_email'      => 'pmpsatu@campaignowner.com',
            'account_name'    => 'PMP Satu',
            'status'          => 'active',
            'company_name'    => 'Domino Mall',
            'address_line1'   => 'Jl. Gunung Salak 31 A',
            'city'            => 'Badung',
            'country_id'      => $this->country->country_id,
            'merchant_ids'    => [$this->mall_a->merchant_id, $this->mall_b->merchant_id],
            'user_password'   => '123456',
            'role_name'       => 'Campaign Owner',
        ];

        $response = $this->setRequestPostNewAccount($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("Link to tenant is does not allowed", $response->message);
    }

    public function testAccountTypeMallFailedLinkObjectTypeIsTenant()
    {
        /*
        * test pmp account with account type mall failed cause mall has link to other account type
        */
        $data = [
            'account_type_id' => $this->account_type_mall->account_type_id,
            'user_firstname'  => 'irianto',
            'user_lastname'   => 'pratama',
            'user_email'      => 'pmpsatu@campaignowner.com',
            'account_name'    => 'PMP Satu',
            'status'          => 'active',
            'company_name'    => 'Domino Mall',
            'address_line1'   => 'Jl. Gunung Salak 31 A',
            'city'            => 'Badung',
            'country_id'      => $this->country->country_id,
            'merchant_ids'    => [$this->tenant_b1->merchant_id],
            'user_password'   => '123456',
            'role_name'       => 'Campaign Owner',
        ];

        $response = $this->setRequestPostNewAccount($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("Link to tenant is does not allowed", $response->message);
    }

    public function testAccountTypeMerchantSuccess()
    {
        /*
        * test pmp account with account type mall failed cause mall has link to other account type
        */
        $data = [
            'account_type_id' => $this->account_type_merchant->account_type_id,
            'user_firstname'  => 'irianto',
            'user_lastname'   => 'pratama',
            'user_email'      => 'pmpsatu@campaignowner.com',
            'account_name'    => 'PMP Satu',
            'status'          => 'active',
            'company_name'    => 'Domino Mall',
            'address_line1'   => 'Jl. Gunung Salak 31 A',
            'city'            => 'Badung',
            'country_id'      => $this->country->country_id,
            'merchant_ids'    => [$this->tenant_b1->merchant_id],
            'user_password'   => '123456',
            'role_name'       => 'Campaign Owner',
        ];

        $response = $this->setRequestPostNewAccount($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame("Request OK", $response->message);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
        $this->assertSame('irianto', $response->data->user_firstname);

        $account_type = CampaignAccount::where('user_id', $response->data->user_id)
                                ->first();

        $this->assertSame($this->account_type_merchant->account_type_id, $account_type->account_type_id);
    }

    public function testAccountTypeMerchantFailedLinkHasExists()
    {
        /*
        * test pmp account with account type mall failed cause mall has link to other account type
        */
        $data = [
            'account_type_id' => $this->account_type_merchant->account_type_id,
            'user_firstname'  => 'irianto',
            'user_lastname'   => 'pratama',
            'user_email'      => 'pmpsatu@campaignowner.com',
            'account_name'    => 'PMP Satu',
            'status'          => 'active',
            'company_name'    => 'Domino Mall',
            'address_line1'   => 'Jl. Gunung Salak 31 A',
            'city'            => 'Badung',
            'country_id'      => $this->country->country_id,
            'merchant_ids'    => [$this->tenant_a->merchant_id],
            'user_password'   => '123456',
            'role_name'       => 'Campaign Owner',
        ];

        $response = $this->setRequestPostNewAccount($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("Link to tenant is does not allowed", $response->message);
    }

    public function testAccountTypeMerchantFailedLinkObjectTypeIsMall()
    {
        /*
        * test pmp account with account type mall failed cause mall has link to other account type
        */
        $data = [
            'account_type_id' => $this->account_type_merchant->account_type_id,
            'user_firstname'  => 'irianto',
            'user_lastname'   => 'pratama',
            'user_email'      => 'pmpsatu@campaignowner.com',
            'account_name'    => 'PMP Satu',
            'status'          => 'active',
            'company_name'    => 'Domino Mall',
            'address_line1'   => 'Jl. Gunung Salak 31 A',
            'city'            => 'Badung',
            'country_id'      => $this->country->country_id,
            'merchant_ids'    => [$this->mall_b->merchant_id],
            'user_password'   => '123456',
            'role_name'       => 'Campaign Owner',
        ];

        $response = $this->setRequestPostNewAccount($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("Link to tenant is does not allowed", $response->message);
    }

    public function testAccountTypeAgencySuccess()
    {
        /*
        * test pmp account with account type mall failed cause mall has link to other account type
        */
        $data = [
            'account_type_id' => $this->account_type_agency->account_type_id,
            'user_firstname'  => 'irianto',
            'user_lastname'   => 'pratama',
            'user_email'      => 'pmpsatu@campaignowner.com',
            'account_name'    => 'PMP Satu',
            'status'          => 'active',
            'company_name'    => 'Domino Mall',
            'address_line1'   => 'Jl. Gunung Salak 31 A',
            'city'            => 'Badung',
            'country_id'      => $this->country->country_id,
            'merchant_ids'    => [$this->mall_b->merchant_id, $this->tenant_b1->merchant_id],
            'user_password'   => '123456',
            'role_name'       => 'Campaign Owner',
        ];

        $response = $this->setRequestPostNewAccount($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame("Request OK", $response->message);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
        $this->assertSame('irianto', $response->data->user_firstname);

        $account_type = CampaignAccount::where('user_id', $response->data->user_id)
                                ->first();

        $this->assertSame($this->account_type_agency->account_type_id, $account_type->account_type_id);
    }

    public function testAccountTypeAgencyFailedLinkHasExists()
    {
        /*
        * test pmp account with account type mall failed cause mall has link to other account type
        */
        $data = [
            'account_type_id' => $this->account_type_agency->account_type_id,
            'user_firstname'  => 'irianto',
            'user_lastname'   => 'pratama',
            'user_email'      => 'pmpsatu@campaignowner.com',
            'account_name'    => 'PMP Satu',
            'status'          => 'active',
            'company_name'    => 'Domino Mall',
            'address_line1'   => 'Jl. Gunung Salak 31 A',
            'city'            => 'Badung',
            'country_id'      => $this->country->country_id,
            'merchant_ids'    => [$this->mall_a->merchant_id, $this->tenant_a->merchant_id],
            'user_password'   => '123456',
            'role_name'       => 'Campaign Owner',
        ];

        $response = $this->setRequestPostNewAccount($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("Link to tenant is does not allowed", $response->message);
    }

    public function testAccountType3rdSuccess()
    {
        /*
        * test pmp account with account type mall failed cause mall has link to other account type
        */
        $data = [
            'account_type_id' => $this->account_type_3rd->account_type_id,
            'user_firstname'  => 'irianto',
            'user_lastname'   => 'pratama',
            'user_email'      => 'pmpsatu@campaignowner.com',
            'account_name'    => 'PMP Satu',
            'status'          => 'active',
            'company_name'    => 'Domino Mall',
            'address_line1'   => 'Jl. Gunung Salak 31 A',
            'city'            => 'Badung',
            'country_id'      => $this->country->country_id,
            'merchant_ids'    => [$this->mall_b->merchant_id, $this->mall_a->merchant_id],
            'user_password'   => '123456',
            'role_name'       => 'Campaign Owner',
        ];

        $response = $this->setRequestPostNewAccount($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame("Request OK", $response->message);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
        $this->assertSame('irianto', $response->data->user_firstname);

        $account_type = CampaignAccount::where('user_id', $response->data->user_id)
                                ->first();

        $this->assertSame($this->account_type_3rd->account_type_id, $account_type->account_type_id);
    }

    public function testAccountType3rdFailedLinkObjectTypeIsTenant()
    {
        /*
        * test pmp account with account type mall failed cause mall has link to other account type
        */
        $data = [
            'account_type_id' => $this->account_type_3rd->account_type_id,
            'user_firstname'  => 'irianto',
            'user_lastname'   => 'pratama',
            'user_email'      => 'pmpsatu@campaignowner.com',
            'account_name'    => 'PMP Satu',
            'status'          => 'active',
            'company_name'    => 'Domino Mall',
            'address_line1'   => 'Jl. Gunung Salak 31 A',
            'city'            => 'Badung',
            'country_id'      => $this->country->country_id,
            'merchant_ids'    => [$this->tenant_b1->merchant_id],
            'user_password'   => '123456',
            'role_name'       => 'Campaign Owner',
        ];

        $response = $this->setRequestPostNewAccount($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("Link to tenant is does not allowed", $response->message);
    }

    public function testAccountTypeDominoposSuccess()
    {
        /*
        * test pmp account with account type mall failed cause mall has link to other account type
        */
        $data = [
            'account_type_id' => $this->account_type_dominopos->account_type_id,
            'user_firstname'  => 'irianto',
            'user_lastname'   => 'pratama',
            'user_email'      => 'pmpsatu@campaignowner.com',
            'account_name'    => 'PMP Satu',
            'status'          => 'active',
            'company_name'    => 'Domino Mall',
            'address_line1'   => 'Jl. Gunung Salak 31 A',
            'city'            => 'Badung',
            'country_id'      => $this->country->country_id,
            'merchant_ids'    => [
                    $this->mall_b->merchant_id,
                    $this->mall_a->merchant_id,
                    $this->tenant_a->merchant_id,
                    $this->tenant_b1->merchant_id
                ],
            'user_password'   => '123456',
            'role_name'       => 'Campaign Owner',
        ];

        $response = $this->setRequestPostNewAccount($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame("Request OK", $response->message);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
        $this->assertSame('irianto', $response->data->user_firstname);

        $account_type = CampaignAccount::where('user_id', $response->data->user_id)
                                ->first();

        $this->assertSame($this->account_type_dominopos->account_type_id, $account_type->account_type_id);
    }

    public function testAccountType3rdSelectAllTenantsSuccess()
    {
        /*
        * test pmp account with account type mall failed cause mall has link to other account type
        */
        $data = [
            'account_type_id'    => $this->account_type_3rd->account_type_id,
            'user_firstname'     => 'irianto',
            'user_lastname'      => 'pratama',
            'user_email'         => 'pmpsatu@campaignowner.com',
            'account_name'       => 'PMP Satu',
            'status'             => 'active',
            'company_name'       => 'Domino Mall',
            'address_line1'      => 'Jl. Gunung Salak 31 A',
            'city'               => 'Badung',
            'country_id'         => $this->country->country_id,
            'select_all_tenants' => 'Y',
            'user_password'      => '123456',
            'role_name'          => 'Campaign Owner',
        ];

        $response = $this->setRequestPostNewAccount($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame("Request OK", $response->message);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
        $this->assertSame('irianto', $response->data->user_firstname);

        $account_type = CampaignAccount::where('user_id', $response->data->user_id)
                                ->first();

        $this->assertSame($this->account_type_3rd->account_type_id, $account_type->account_type_id);
        $this->assertSame('Y', $account_type->is_link_to_all);

        $user_merchant = UserMerchant::where('user_id', $response->data->user_id)
                                ->first();

        $this->assertSame(empty($user_merchant), true);
    }

    public function testAccountTypeDominoposSelectAllTenantsSuccess()
    {
        /*
        * test pmp account with account type mall failed cause mall has link to other account type
        */
        $data = [
            'account_type_id'    => $this->account_type_dominopos->account_type_id,
            'user_firstname'     => 'irianto',
            'user_lastname'      => 'pratama',
            'user_email'         => 'pmpsatu@campaignowner.com',
            'account_name'       => 'PMP Satu',
            'status'             => 'active',
            'company_name'       => 'Domino Mall',
            'address_line1'      => 'Jl. Gunung Salak 31 A',
            'city'               => 'Badung',
            'country_id'         => $this->country->country_id,
            'select_all_tenants' => 'Y',
            'user_password'      => '123456',
            'role_name'          => 'Campaign Owner',
        ];

        $response = $this->setRequestPostNewAccount($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame("Request OK", $response->message);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
        $this->assertSame('irianto', $response->data->user_firstname);

        $account_type = CampaignAccount::where('user_id', $response->data->user_id)
                                ->first();

        $this->assertSame($this->account_type_dominopos->account_type_id, $account_type->account_type_id);
        $this->assertSame('Y', $account_type->is_link_to_all);

        $user_merchant = UserMerchant::where('user_id', $response->data->user_id)
                                ->first();

        $this->assertSame(empty($user_merchant), true);
    }

    public function testSendEmptyStringSelectAllTenantSuccess()
    {
        /*
        * test send empty string select all tenant to default 'N'
        */
        $data = [
            'select_all_tenants' => '',
            'account_type_id'    => $this->account_type_agency->account_type_id,
            'user_firstname'     => 'irianto',
            'user_lastname'      => 'pratama',
            'user_email'         => 'pmpsatu@campaignowner.com',
            'account_name'       => 'PMP Satu',
            'status'             => 'active',
            'company_name'       => 'Domino Mall',
            'address_line1'      => 'Jl. Gunung Salak 31 A',
            'city'               => 'Badung',
            'country_id'         => $this->country->country_id,
            'merchant_ids'       => [$this->mall_b->merchant_id, $this->tenant_b1->merchant_id],
            'user_password'      => '123456',
            'role_name'          => 'Campaign Owner',
        ];

        $response = $this->setRequestPostNewAccount($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame("Request OK", $response->message);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
        $this->assertSame('irianto', $response->data->user_firstname);

        $account_type = CampaignAccount::where('user_id', $response->data->user_id)
                                ->first();

        $this->assertSame($this->account_type_agency->account_type_id, $account_type->account_type_id);
        $this->assertSame('N', $account_type->is_link_to_all);
    }
}