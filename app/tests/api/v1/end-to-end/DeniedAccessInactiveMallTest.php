<?php
/**
 * Unit test for checking mall access. Mall is inaccessible if the
 * status is not active and in production environment.
 *
 * @author Rio Astamal <rio@dominopos.com>
 */
use Laracasts\TestDummy\Factory;
use Orbit\Setting as OrbitSetting;

class DeniedAccessInactiveMallTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->disableCatchAllRoute();
        Route::enableFilters();

        // Fool the filters by setting temporary current_retailer which can be
        // overriden later on
        App::make('orbitSetting')->setSetting('current_retailer', '1');

        // Dummy routes for testing 'orbit-settings' filter
        Route::match('GET', '/unit-test/mall-access', ['before' => 'orbit-settings', function()
        {
            return 'I am OK';
        }]);
    }

    public function tearDown()
    {
        parent::tearDown();

        Route::disableFilters();
    }

    public function testOK_MallActive_ProdEnv()
    {
        $mall = Factory::create('Mall', ['status' => 'active']);
        $lang = Factory::create('Language', ['name' => $mall->mobile_default_language]);

        Config::set('orbit.is_demo', FALSE);
        Config::set('orbit.shop.id', $mall->merchant_id);

        $response = $this->call('GET', '/unit-test/mall-access');
        $this->assertResponseStatus(200);
    }

    public function testOK_MallInactive_DemoEnv()
    {
        $mall = Factory::create('Mall', ['status' => 'inactive']);
        $lang = Factory::create('Language', ['name' => $mall->mobile_default_language]);

        Config::set('orbit.is_demo', TRUE);
        Config::set('orbit.shop.id', $mall->merchant_id);

        $response = $this->call('GET', '/unit-test/mall-access');
        $this->assertResponseStatus(200);
    }

    public function testFail_MallInactive_ProdEnv()
    {
        $mall = Factory::create('Mall', ['status' => 'inactive']);
        $lang = Factory::create('Language', ['name' => $mall->mobile_default_language]);

        Config::set('orbit.is_demo', FALSE);
        Config::set('orbit.shop.id', $mall->merchant_id);

        $errorMessage = sprintf('%s is inaccessible at the moment.', $mall->name);
        $this->setExpectedException('Symfony\Component\HttpKernel\Exception\HttpException', $errorMessage);
        $response = $this->call('GET', '/unit-test/mall-access');
    }
}