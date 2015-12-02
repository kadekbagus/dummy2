<?php
/**
 * PHP Unit Test for Category Controller getSearchCategory
 *
 * @author: Yudi Rahono <yudi.rahono@dominopos.com>
 * @author: Irianto Pratama <irianto@dominopos.com>
 */
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;
use Faker\Factory as Faker;

class postNewNewsTest extends TestCase {

    private $baseUrl = '/api/v1/news/new';

    public function setUp()
    {
        parent::setUp();

        $this->authData = Factory::create('apikey_super_admin');
        $this->news   = Factory::times(3)->create("News");
        $news = $this->news;
    }

    public function testOK_post_new_news_with_more_than_one_link_id()
    {
        $merchant = Factory::create('retailer_mall');

        $_GET['apikey']       = $this->authData->api_key;
        $_GET['apitimestamp'] = time();

        $_POST['mall_id']                   = $merchant->merchant_id;
        $_POST['news_name']                 = Faker::create()->sentence(3);
        $_POST['object_type']               = 'news';
        $_POST['status']                    = 'active';
        $_POST['link_object_type']          = 'tenant';
        $_POST['id_language_default']       = 1;

        $url = $this->baseUrl . '?' . http_build_query($_GET);

        $secretKey = $this->authData->api_secret_key;
        $_SERVER['REQUEST_METHOD']         = 'POST';
        $_SERVER['REQUEST_URI']            = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('POST', $url, $_POST)->getContent();
        $response = json_decode($response);

        // Should be OK
        $this->assertResponseOk();
        // should say OK
        $this->assertSame(Status::OK, $response->code);
        $this->assertSame(Status::OK_MSG, $response->message);
    }
}
