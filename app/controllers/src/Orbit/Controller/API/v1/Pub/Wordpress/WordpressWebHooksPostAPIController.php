<?php namespace Orbit\Controller\API\v1\Pub\Wordpress;
/**
 * Controller for listing posts from Wordpress. This controller
 * uses Wordpress API that produced by WP Rest API v2.
 *
 * @author Rio Astamal <rio@dominopos.com>
 * @todo Make this as generic controller because its job only reading json
 *       file from a file.
 */
use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\ResponseProvider;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Config;
use Exception;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Activity;
use MallCountry;
use stdClass;
use Orbit\Helper\Net\Wordpress\PostFetcher;
use Log;
use Net\Security\RequestAccess;

class WordpressWebHooksPostAPIController extends PubControllerAPI
{
    public function postWebHooks()
    {
        $httpCode = 200;

        try {
            $country = strtolower(trim(OrbitInput::post('country')));

            $mallCountry = MallCountry::where('country', $country)->first();

            if (empty($mallCountry)) {
                $this->thrownButOK = TRUE;
                throw new Exception('country not exist, no data returned');
            }

            $config = Config::get("orbit.external_calls.wordpress.{$country}");

            if (empty($config)) {
                $this->thrownButOK = TRUE;
                throw new Exception('Config wordpress for country ' . ucfirst($country) . ' is not found, no data returned');
            }
            $jsonFile = isset($config['cache_file']) ? $config['cache_file'] : '/dev/shm/gtm-wordpress-post.json';
            $dirname = dirname($jsonFile);

            $accessSecurity = RequestAccess::create()->setAllowedIps($config['web_hooks_allowed_ips'], '*');
            $userIp = $_SERVER['REMOTE_ADDR'];
            if (! $accessSecurity->checkIpAddress($userIp)) {
                $message = sprintf('Access denied from IP %s', $userIp);
                Log::info(sprintf('[WORDPRESS] %s', $message));
                ACL::throwAccessForbidden($message);
            }

            if (! file_exists($dirname)) {
                mkdir($dirname, 0755, TRUE);
            }

            Log::info(sprintf('[WORDPRESS] Web Hooks: Fetching posts from %s', $config['base_blog_url']));
            $response = (new PostFetcher($config))->getPosts('json');
            file_put_contents($config['cache_file'], $response);
            Log::info(sprintf('[WORDPRESS] Web Hooks: Successfully fetched and saved to %s', $config['cache_file']));

            $this->response->data = json_decode($response);
            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Posts has been successfully written to the cache';

        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $httpCode = 400;
        } catch (Exception $e) {
            $this->response->message = $e->getMessage();
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $httpCode = 500;
            $this->data = $e->getFile() . ' -- ' . $e->getLine();
        }

        return $this->render($httpCode);
    }
}