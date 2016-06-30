<?php
/**
 * Unit test for AppOriginGetter which determine the application
 * name that send by the consumer (frontend application)
 *
 * @author Rio Astamal <rio@dominopos.com>
 */
use Orbit\Helper\Session\AppOriginProcessor;

class AppOriginProcessorTest extends OrbitTestCase
{
    protected $appConfigName = 'X-Unit-Test-Origin-App';
    protected $appListConfig = [
            'mobile_ci' => 'test_mobile_ci_session',
            'desktop_ci' => 'test_desktop_ci_session',
            'landing_page' => 'test_landing_page_session',

            // Non Customer
            'mall_portal' => 'test_mall_portal_session',
            'cs_portal' => 'test_cs_portal_session',
            'pmp_portal' => 'test_pmp_portal_session',
            'admin_portal' => 'test_admin_portal_session'
    ];

    public function test_instance_should_ok()
    {
        $getter = AppOriginProcessor::create();
        $this->assertInstanceOf('Orbit\Helper\Session\AppOriginProcessor', $getter);
    }

    public function test_getting_app_name_from_query_string_only()
    {
        $queryString = [
            'foo' => 'bar',
            $this->appConfigName => 'mall_portal'
        ];
        $getter = AppOriginProcessor::create($this->appListConfig)
                        ->setOriginConfigName($this->appConfigName)
                        ->setQueryStrings($queryString);

        $this->assertSame($getter->getAppName(), 'mall_portal');
        $this->assertSame($getter->getSessionName(), 'test_mall_portal_session');
    }

    public function test_getting_app_name_from_http_header_only()
    {
        $headers = [
            'HTTP_USER_AGENT' => 'Orbit/1.0',
            $this->appConfigName => 'pmp_portal'
        ];
        $getter = AppOriginProcessor::create($this->appListConfig)
                        ->setOriginConfigName($this->appConfigName)
                        ->setHttpHeaders($headers);

        $this->assertSame($getter->getAppName(), 'pmp_portal');
        $this->assertSame($getter->getSessionName(), 'test_pmp_portal_session');
    }

    public function test_getting_app_name_from_query_string_http_header_then_query_string_used()
    {
        $queryString = [
            'foo' => 'bar',
            $this->appConfigName => 'mall_portal'
        ];
        $headers = [
            'HTTP_USER_AGENT' => 'Orbit/1.0',
            $this->appConfigName => 'pmp_portal'
        ];
        $getter = AppOriginProcessor::create($this->appListConfig)
                        ->setOriginConfigName($this->appConfigName)
                        ->setQueryStrings($queryString)
                        ->setHttpHeaders($headers);

        $this->assertSame($getter->getAppName(), 'mall_portal');
        $this->assertSame($getter->getSessionName(), 'test_mall_portal_session');
    }

    public function test_getting_app_name_should_return_default_value_if_both_query_string_and_headers_not_exists()
    {
        $queryString = [];
        $headers = [];
        $getter = AppOriginProcessor::create($this->appListConfig)
                        ->setOriginConfigName($this->appConfigName)
                        ->setQueryStrings($queryString)
                        ->setDefaultAppName('landing_page')
                        ->setHttpHeaders($headers);

        $this->assertSame($getter->getAppName(), 'landing_page');
        $this->assertSame($getter->getSessionName(), 'test_landing_page_session');
    }
}