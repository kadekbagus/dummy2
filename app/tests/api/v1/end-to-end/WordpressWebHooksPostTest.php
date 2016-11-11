<?php
/**
 * Unit test for testing Web Hooks that coming from Wordpress.
 *
 * @author Rio Astamal <rio@dominopos.com>
 */
class WordpressWebHooksPostTest extends TestCase
{
    protected $cacheFile = '/dev/shm/gtm-unit-test-wp-blog-post';

    public function setUp()
    {
        parent::setUp();

        if (file_exists($this->cacheFile)) {
            unlink($this->cacheFile);
        }

        Config::set('orbit.external_calls.wordpress', [
            'cache_file' => $this->cacheFile,
            'web_hooks_allowed_ips' => '*'
        ]);

        $_SERVER['REMOTE_ADDR'] = '192.123.123.123';
    }

    public function tearDown()
    {
        parent::tearDown();

        Config::set('orbit.external_calls.wordpress', NULL);
        unset($_SERVER['REMOTE_ADDR']);
    }

    public function test_client_ips_is_not_in_list_so_it_should_be_rejected()
    {
        Config::set('orbit.external_calls.wordpress.web_hooks_allowed_ips', ['10.10.5.0']);
        $response = $this->call('POST', '/api/v1/pub/blog/web-hooks/post');

        $decoded = json_decode($response->getContent());
        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('Your IP is not allowed to access this resource', $decoded->message);
    }
}