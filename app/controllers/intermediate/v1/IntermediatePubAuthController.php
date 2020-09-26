<?php

use DominoPOS\OrbitACL\ACL;
use Orbit\Helper\Net\SessionPreparer;
use Orbit\Helper\Net\UrlChecker;
use Orbit\Helper\Session\UserGetter;
use Orbit\Helper\Exception\OrbitCustomException;
use Orbit\Helper\Util\UserAgent;
use DominoPOS\OrbitSession\Session as OrbitSession;
use DominoPOS\OrbitSession\SessionConfig;
use Orbit\Helper\Session\AppOriginProcessor;

/**
 * Intermediate Controller for all pub controller which need authentication.
 *
 * @author Ahmad <ahmad@dominopos.com>
 */
class IntermediatePubAuthController extends IntermediateBaseController
{
    const APPLICATION_ID = 1;

    protected $botEmail = 'bot@yourmailbot.com';

    protected $botSessionId = 'bot_session_id_haha';

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
                    $user = User::where('user_email', $this->botEmail)
                        ->firstOrFail();

                    $botSession = DB::table('sessions')
                        ->where('session_id', $this->botSessionId)
                        ->first();

                    if (! is_object($botSession)) {
                        throw new Exception('Bot User session is not available', 1);
                    }

                    $sessionData = unserialize($botSession->session_data);

                    if (empty($sessionData->value)) {
                        throw new Exception('Bot User session data is empty', 1);
                    }

                    // set the session strict to FALSE
                    Config::set('orbit.session.strict', FALSE);

                    // Return mall_portal, cs_portal, pmp_portal etc
                    $appOrigin = AppOriginProcessor::create(Config::get('orbit.session.app_list'))
                                                   ->getAppName();

                    // Session Config
                    $orbitSessionConfig = Config::get('orbit.session.origin.' . $appOrigin);
                    $applicationId = Config::get('orbit.session.app_id.' . $appOrigin);

                    // Instantiate the OrbitSession object
                    $config = new SessionConfig(Config::get('orbit.session'));
                    $config->setConfig('session_origin', $orbitSessionConfig);
                    $config->setConfig('expire', $orbitSessionConfig['expire']);
                    $config->setConfig('application_id', $applicationId);

                    $this->session = new OrbitSession($config);
                    $this->session->setSessionId($botSession->session_id);
                    $this->session->disableForceNew();
                    $this->session->start($sessionData->value, 'no-session-creation');
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
