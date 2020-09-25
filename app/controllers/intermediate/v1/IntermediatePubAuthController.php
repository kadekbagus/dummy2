<?php

use DominoPOS\OrbitACL\ACL;
use Orbit\Helper\Net\SessionPreparer;
use Orbit\Helper\Net\UrlChecker;
use Orbit\Helper\Session\UserGetter;
use Orbit\Helper\Exception\OrbitCustomException;
use Orbit\Helper\Util\UserAgent;

/**
 * Intermediate Controller for all pub controller which need authentication.
 *
 * @author Ahmad <ahmad@dominopos.com>
 */
class IntermediatePubAuthController extends IntermediateBaseController
{
    const APPLICATION_ID = 1;

    /**
     * Check the authenticated user on constructor
     *
     * @author Ahmad <ahnad@dominopos.com>
     */
    public function __construct()
    {
        parent::__construct();

        $this->beforeFilter(function()
        {
            try
            {
                $userAgent = $_SERVER['HTTP_USER_AGENT'];

                \Log::info('***%%%*** USER AGENT: ' . $userAgent);

                $fallbackUARules = ['browser' => [], 'platform' => [], 'device_model' => [], 'bot_crawler' => []];
                $detectUA = new UserAgent();
                $detectUA->setRules(Config::get('orbit.user_agent_rules', $fallbackUARules));
                $detectUA->setUserAgent($this->getUserAgent());

                \Log::info('***%%%*** IS BOT: ' . serialize($detectUA->isBotCrawler()));

                if (! $detectUA->isBotCrawler()) {
                    $this->session = SessionPreparer::prepareSession();

                    // Get user, or generate guest user for new session
                    $user = UserGetter::getLoggedInUserOrGuest($this->session);
                } else {
                    // load previous bot's session

                    // load bot's user
                    $user = User::leftJoin('roles', 'users.user_role_id', '=', 'roles.role_id')
                        ->where('roles.role_name', 'bot')
                        ->firstOrFail();

                    $this->session = DB::table('sessions')
                        ->where('session_id', 'bot_session_id_haha')
                        ->firstOrFail();
                }

                // Register User instance in the container,
                // so it will be accessible from anywhere
                // without doing any re-check/DB call.
                App::instance('currentUser', $user);

                // Check for blocked url
                UrlChecker::checkBlockedUrl($user);
            } catch (OrbitCustomException $e) {
                return $this->handleException($e);
            } catch (Exception $e) {
                return $this->handleException($e);
            }
        });
    }

    /**
     * Detect the user agent of the request.
     *
     * @return string
     */
    protected function getUserAgent()
    {
        return isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Unknown-UA/?';
    }
}
