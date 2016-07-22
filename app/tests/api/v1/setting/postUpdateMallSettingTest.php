<?php
/**
 * PHP Unit Test for Setting API Controller postUpdateMallSetting
 *
 * @author: Irianto Pratama <irianto@dominopos.com>
 */

use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;
use Faker\Factory as Faker;

class postUpdateMallSettingTest extends TestCase
{
    private $apiUrlUpdate = '/api/v1/mall-setting/update';

    public function setUp()
    {
        parent::setUp();

        $this->user_mall_owner = Factory::create('user_mall_owner');
        $this->apiKey = Factory::create('apikey_mall_owner', ['user_id' => $this->user_mall_owner->user_id]);

        $this->mall = Factory::create('Mall', [
                            'user_id' => $this->user_mall_owner->user_id,
                            'mobile_default_language' => 'id'
                    ]);

        $this->enLang = Factory::create('Language', ['name' => 'en']);
        $this->idLang = Factory::create('Language', ['name' => 'id']);
        $this->zhLang = Factory::create('Language', ['name' => 'zh']);
        $this->jpLang = Factory::create('Language', ['name' => 'jp']);

        // create mall language
        $this->idMallLang = Factory::create('MerchantLanguage', ['language_id' => $this->idLang->language_id, 'merchant_id' => $this->mall->merchant_id]);
        $this->zhMallLang = Factory::create('MerchantLanguage', ['language_id' => $this->zhLang->language_id, 'merchant_id' => $this->mall->merchant_id]);
        $this->jpMallLang = Factory::create('MerchantLanguage', ['language_id' => $this->jpLang->language_id, 'merchant_id' => $this->mall->merchant_id]);
    }

    public function setRequestPostUpdateMallSetting($api_key, $api_secret_key, $update)
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

    public function testUpdateAddLanguage()
    {
        // add english
        $languages               = [
                $this->jpLang->language_id,
                $this->zhLang->language_id,
                $this->idLang->language_id,
                $this->enLang->language_id
            ];
        $mobile_default_language = 'id';

        $data = [
            'current_mall'                => $this->mall->merchant_id,
            'merchant_id'                 => $this->mall->merchant_id,
            'id_language_default'         => Language::excludeDeleted()->where('name', $mobile_default_language)->first()->language_id,
            'language'                    => $mobile_default_language,
            'mall_supported_language_ids' => $languages,
            'landing_page'                => 'service'
        ];

        $response = $this->setRequestPostUpdateMallSetting($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame('Request OK', $response->message);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
        $this->assertSame($mobile_default_language, $response->data->mall->mobile_default_language);
        // check mall languages on database
        $lang_db = MerchantLanguage::excludeDeleted('merchant_languages')
                    ->leftJoin('languages', 'languages.language_id', '=', 'merchant_languages.language_id')
                    ->where('merchant_id', $this->mall->merchant_id)
                    ->get();
        foreach ($lang_db as $idx => $lang) {
            $this->assertContains($lang->language_id, $languages);
        }
    }

    public function testUpdateRemoveLanguage()
    {
        // remove zh
        $languages               = [
                $this->jpLang->language_id,
                $this->idLang->language_id,
            ];
        $mobile_default_language = 'id';


        $data = [
            'current_mall'                => $this->mall->merchant_id,
            'merchant_id'                 => $this->mall->merchant_id,
            'id_language_default'         => Language::excludeDeleted()->where('name', $mobile_default_language)->first()->language_id,
            'language'                    => $mobile_default_language,
            'mall_supported_language_ids' => $languages,
            'landing_page'                => 'service'
        ];

        $response = $this->setRequestPostUpdateMallSetting($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame('Request OK', $response->message);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
        $this->assertSame($mobile_default_language, $response->data->mall->mobile_default_language);
        // check mall languages on database
        $lang_db = MerchantLanguage::excludeDeleted('merchant_languages')
                    ->leftJoin('languages', 'languages.language_id', '=', 'merchant_languages.language_id')
                    ->where('merchant_id', $this->mall->merchant_id)
                    ->get();
        foreach ($lang_db as $idx => $lang) {
            $this->assertContains($lang->language_id, $languages);
        }
    }

    public function testUpdateRemoveLanguageWhenUseOnMobileLanguage()
    {
        /*
        * test remove language when use on mobile default language
        */
        $languages               = [
                $this->jpLang->language_id,
                $this->zhLang->language_id,
            ];
        $mobile_default_language = 'id';

        $data = [
            'current_mall'                => $this->mall->merchant_id,
            'merchant_id'                 => $this->mall->merchant_id,
            'id_language_default'         => Language::excludeDeleted()->where('name', $mobile_default_language)->first()->language_id,
            'language'                    => $mobile_default_language,
            'mall_supported_language_ids' => $languages,
            'landing_page'                => 'service'
        ];

        $response = $this->setRequestPostUpdateMallSetting($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("Mobile default language must on list languages", $response->message);
    }

    public function testRemoveLanguagesHasLink()
    {
        /*
        * when language has link its allowed to delete
        */
        // link to campaign
        // create campaign translation with mobile default language
        $news = Factory::create('News');
        $news_translation = Factory::create('NewsTranslation', [
                'news_id' => $news->news_id,
                'merchant_language_id' => $this->zhLang->language_id
            ]);
        $tenant = Factory::create('Tenant', ['parent_id' => $this->mall->merchant_id]);
        $new_merchant = Factory::create('NewsMerchant', [
                    'news_id' => $news->news_id,
                    'merchant_id' => $tenant->merchant_id,
                    'object_type' => 'tenant'
                ]);

        $languages               = [
                $this->jpLang->language_id,
                $this->idLang->language_id,
            ];
        $mobile_default_language = 'id';

        $data = [
            'current_mall'                => $this->mall->merchant_id,
            'merchant_id'                 => $this->mall->merchant_id,
            'id_language_default'         => Language::excludeDeleted()->where('name', $mobile_default_language)->first()->language_id,
            'language'                    => $mobile_default_language,
            'mall_supported_language_ids' => $languages,
            'landing_page'                => 'service'
        ];

        $response = $this->setRequestPostUpdateMallSetting($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame('Request OK', $response->message);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
        // check merchant language is deleted
        $del_mall_lang = MerchantLanguage::where('language_id', $this->zhLang->language_id)
                            ->where('merchant_id', $this->mall->merchant_id)
                            ->first();

        $this->assertSame("deleted", $del_mall_lang->status);

        // check link campaign translation is still active
        $_news_translation = NewsTranslation::where('news_id', $news->news_id)
                                ->first();

        $this->assertSame("active", $_news_translation->status);
    }

    public function testRemoveLanguagesHasLinkThenAddThatLanguage()
    {
        /*
        * when language has link its allowed to delete
        */
        // link to campaign
        $news = Factory::create('News');
        $news_translation = Factory::create('NewsTranslation', [
                'news_id' => $news->news_id,
                'merchant_language_id' => $this->zhLang->language_id
            ]);
        $tenant = Factory::create('Tenant', ['parent_id' => $this->mall->merchant_id]);
        $new_merchant = Factory::create('NewsMerchant', [
                    'news_id' => $news->news_id,
                    'merchant_id' => $tenant->merchant_id,
                    'object_type' => 'tenant'
                ]);

        $languages               = [
                $this->jpLang->language_id,
                $this->idLang->language_id,
            ];
        $mobile_default_language = 'id';

        $data = [
            'current_mall'                => $this->mall->merchant_id,
            'merchant_id'                 => $this->mall->merchant_id,
            'id_language_default'         => Language::excludeDeleted()->where('name', $mobile_default_language)->first()->language_id,
            'language'                    => $mobile_default_language,
            'mall_supported_language_ids' => $languages,
            'landing_page'                => 'service'
        ];

        $response = $this->setRequestPostUpdateMallSetting($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame('Request OK', $response->message);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
        // check merchant language is deleted
        $del_mall_lang = MerchantLanguage::where('language_id', $this->zhLang->language_id)
                            ->where('merchant_id', $this->mall->merchant_id)
                            ->first();
        $this->assertSame("deleted", $del_mall_lang->status);

        // check link campaign translation is still active
        $_news_translation = NewsTranslation::where('news_id', $news->news_id)
                                ->first();
        $this->assertSame("active", $_news_translation->status);

        // add mall language zh
        $new_zhLang = Factory::Create('MerchantLanguage', ['language_id' => $this->zhLang->language_id, 'merchant_id' => $this->mall->merchant_id]);

        // check database has two zh and one of data is active
        $zh_on_db = MerchantLanguage::where('language_id', $this->zhLang->language_id)
                            ->where('merchant_id', $this->mall->merchant_id)
                            ->get();
        $this->assertSame(2, count($zh_on_db));
        foreach ($zh_on_db as $_zhLang) {
            if ($_zhLang->merchant_language_id === $this->zhMallLang->merchant_language_id)
                $this->assertSame('deleted', $_zhLang->status);
            if ($_zhLang->merchant_language_id === $new_zhLang->merchant_language_id)
                $this->assertSame('active', $_zhLang->status);
        }

        // check language is still link to old campaign zh translation
        $check_news_translation = NewsTranslation::join('news_merchant', 'news_merchant.news_id', '=', 'news_translations.news_id')
                                    ->where('merchant_language_id', $new_zhLang->language_id)
                                    ->where('news_merchant.merchant_id', $tenant->merchant_id)
                                    ->first();
        $this->assertSame($news_translation->news_name, $check_news_translation->news_name);
    }

    public function testDeleteLanguageThenAddThatLanguage()
    {
        /*
        * test remove language when use on mobile default language
        */
        $remove_zhLang = MerchantLanguage::excludeDeleted()
                        ->where('language_id', $this->zhLang->language_id)
                        ->where('merchant_id', $this->mall->merchant_id)
                        ->first();

        $remove_zhLang->status = 'deleted';
        $remove_zhLang->save();

        $languages               = [
                $this->jpLang->language_id,
                $this->idLang->language_id,
                $this->zhLang->language_id,
            ];
        $mobile_default_language = 'id';

        $data = [
            'current_mall'                => $this->mall->merchant_id,
            'merchant_id'                 => $this->mall->merchant_id,
            'id_language_default'         => Language::excludeDeleted()->where('name', $mobile_default_language)->first()->language_id,
            'language'                    => $mobile_default_language,
            'mall_supported_language_ids' => $languages,
            'landing_page'                => 'service'
        ];

        $response = $this->setRequestPostUpdateMallSetting($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame("Request OK", $response->message);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);

        // check doesn't happen duplicate old data, except zh
        $mall_language_db = MerchantLanguage::where('merchant_id', $this->mall->merchant_id)
                            ->get();

        $this->assertSame(4, count($mall_language_db));

        // check zh lang status
        $zh_on_db = MerchantLanguage::where('language_id', $this->zhLang->language_id)
                            ->where('merchant_id', $this->mall->merchant_id)
                            ->get();
        $this->assertSame(2, count($zh_on_db));
        foreach ($zh_on_db as $_zhLang) {
            if ($_zhLang->merchant_language_id === $this->zhMallLang->merchant_language_id)
                $this->assertSame('deleted', $_zhLang->status);
            if ($_zhLang->merchant_language_id !== $this->zhMallLang->merchant_language_id)
                $this->assertSame('active', $_zhLang->status);
        }
    }

    public function testUpdateMobileLanguages()
    {
        /*
        * when update mobile default language doesnt has link is allowed to change
        */
        $languages               = [
                $this->jpLang->language_id,
                $this->zhLang->language_id,
                $this->idLang->language_id,
            ];
        $mobile_default_language = 'zh';

        $data = [
            'current_mall'                => $this->mall->merchant_id,
            'merchant_id'                 => $this->mall->merchant_id,
            'id_language_default'         => Language::excludeDeleted()->where('name', $mobile_default_language)->first()->language_id,
            'language'                    => $mobile_default_language,
            'mall_supported_language_ids' => $languages,
            'landing_page'                => 'service'
        ];

        $response = $this->setRequestPostUpdateMallSetting($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
        $this->assertSame($mobile_default_language, $response->data->mall->mobile_default_language);
    }

    public function testUpdateMobileLanguagesHasLink()
    {
        /*
        * when update mobile default language has link is doesnt allowed to change
        */
        // create campaign translation with mobile default language
        $news = Factory::create('News');
        $news_translation = Factory::create('NewsTranslation', [
                'news_id' => $news->news_id,
                'merchant_language_id' => $this->idLang->language_id
            ]);
        $tenant = Factory::create('Tenant', ['parent_id' => $this->mall->merchant_id]);
        $new_merchant = Factory::create('NewsMerchant', [
                    'news_id' => $news->news_id,
                    'merchant_id' => $tenant->merchant_id,
                    'object_type' => 'tenant'
                ]);

        $languages               = [
                $this->jpLang->language_id,
                $this->zhLang->language_id,
                $this->idLang->language_id,
            ];
        $mobile_default_language = 'zh';

        $data = [
            'current_mall'                => $this->mall->merchant_id,
            'merchant_id'                 => $this->mall->merchant_id,
            'id_language_default'         => Language::excludeDeleted()->where('name', $mobile_default_language)->first()->language_id,
            'language'                    => $mobile_default_language,
            'mall_supported_language_ids' => $languages,
            'landing_page'                => 'service'
        ];

        $response = $this->setRequestPostUpdateMallSetting($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("Cannot change default supported language has campaign translation", $response->message);
    }

    public function testUpdateMobileLanguagesNotOnListLanguages()
    {
        /*
        * test update mobile default language not on list language
        */
        $languages               = [
                $this->jpLang->language_id,
                $this->zhLang->language_id,
                $this->idLang->language_id,
            ];
        $mobile_default_language = 'en';

        $data = [
            'current_mall'                => $this->mall->merchant_id,
            'merchant_id'                 => $this->mall->merchant_id,
            'id_language_default'         => Language::excludeDeleted()->where('name', 'id')->first()->language_id,
            'language'                    => $mobile_default_language,
            'mall_supported_language_ids' => $languages,
            'landing_page'                => 'service'
        ];

        $response = $this->setRequestPostUpdateMallSetting($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("Mobile default language must on list languages", $response->message);
    }
}