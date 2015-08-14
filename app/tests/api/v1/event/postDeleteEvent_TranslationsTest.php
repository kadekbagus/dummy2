<?php
use Laracasts\TestDummy\Factory;
use OrbitShop\API\v1\Helper\Generator;

/**
 * Tests translations are deleted when events are.
 *
 * @property MerchantLanguage[] $merchantLanguages
 * @property Merchant $group
 * @property Retailer $mall
 * @property string $masterPassword
 */
class postDeleteEvent_TranslationsTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();
        $english = Factory::create('Language', ['name' => 'English']);

        $this->group = $merchant = Factory::create('Merchant');

        $this->mall = Factory::create('Retailer', ['is_mall' => 'yes']);
        $role = $this->mall->user->role;
        $role->role_name = 'mall owner';
        $role->save();

        $setting = new Setting();
        $setting->setting_name = 'current_event';
        $setting->setting_value = $this->mall->merchant_id;
        $setting->save();

        $password_setting = new Setting();
        $password_setting->setting_name = 'master_password';
        $password_setting->object_type = 'merchant';
        $password_setting->object_id = $merchant->object_id;
        $password_setting->setting_value = Hash::make($this->masterPassword = '12345');
        $password_setting->save();

        $permission = Factory::create('Permission', ['permission_name' => 'delete_event']);

        Factory::create('PermissionRole',
            ['role_id' => $this->mall->user->user_role_id, 'permission_id' => $permission->permission_id]);
        $this->authData = Factory::create('Apikey', ['user_id' => $this->mall->user->user_id]);

        $english_merchant_language = new MerchantLanguage();
        $english_merchant_language->language_id = $english;
        $english_merchant_language->merchant_id = $merchant->merchant_id;
        $english_merchant_language->save();

        $this->merchantLanguages = [
            'english' => $english_merchant_language
        ];
    }

    private function makeRequest($event)
    {
        $_GET = [
            'apikey' => $this->authData->api_key,
            'apitimestamp' => time(),
        ];

        $_POST = [
            'event_id' => $event->event_id,
            'password' => $this->masterPassword
        ];

        $url = '/api/v1/event/delete?' . http_build_query($_GET);

        $secretKey = $this->authData->api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('POST', $url, $_POST)->getContent();
        $response = json_decode($response);

        return $response;
    }

    private function createEventWithTranslation($merchant_language_name)
    {
        $event = Factory::create('EventModel', [
            'merchant_id' => $this->mall->merchant_id,
        ]);
        $translation = new EventTranslation();
        $translation->event_id = $event->event_id;
        $translation->merchant_language_id = $this->merchantLanguages[$merchant_language_name]->merchant_language_id;
        $translation->event_name = 'Translated name';
        $translation->description = 'Translated description';
        $translation->save();

        return [$event, $translation];
    }

    /**
     * @param object $response
     */
    private function assertJsonResponseOk($response)
    {
        $this->assertSame('Event has been successfully deleted.', $response->message);
        $this->assertSame('success', $response->status);
        $this->assertSame(0, (int)$response->code);
    }

    function testDeletingEventDeletesTranslations() {
        list($event, $translation) = $this->createEventWithTranslation('english');
        $count_event_before = EventModel::excludeDeleted()->count();
        $count_translation_before = EventTranslation::excludeDeleted()->count();

        $response = $this->makeRequest($event);
        $this->assertJsonResponseOk($response);

        $count_event_after = EventModel::excludeDeleted()->count();
        $count_translation_after = EventTranslation::excludeDeleted()->count();
        $this->assertSame($count_event_before - 1, $count_event_after);
        $this->assertSame($count_translation_before - 1, $count_translation_after);
    }

}
