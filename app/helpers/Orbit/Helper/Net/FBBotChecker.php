<?php namespace Orbit\Helper\Net;

use \Config;

class FBBotChecker
{
    /**
     * Server info.
     *
     * @var array
     */
    protected $server = [];

    /**
     * Facebook user agent.
     *
     * @var array
     */
    protected $fb_user_agents = [];

    /**
     * Constructor
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
    public function __construct($server = NULL, $agent = NULL)
    {
        $this->server = is_null($server) ? $_SERVER : $server;
        $this->fb_user_agents = is_null($agent) ? Config::get('orbit.social_crawler.facebook', ['Facebot']) : $agent;
    }

    /**
     * Set the server
     * @param array $_SERVER
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
    public function setServer($server)
    {
        $this->server = $server;
    }

    /**
     * Set the server
     * @param string user agent
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
    public function setFBUserAgent($agent)
    {
        $this->fb_user_agents = $agent;
    }

    /**
     * Get the server
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
    public function getServer()
    {
        return $this->server;
    }

    /**
     * Get the request user agent
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
    public function getUserAgent()
    {
        return $this->server['HTTP_USER_AGENT'];
    }

    /**
     * Get the FB user agent
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
    public function getFBUserAgent()
    {
        return $this->fb_user_agents;
    }

    /**
     * Check if the request come from FB crawler
     * @return boolean
     */
    public function isFBCrawler()
    {
        $isFB = FALSE;
        foreach ($this->fb_user_agents as $fb_user_agent) {
            if (strpos($this->getUserAgent(), $fb_user_agent) !== FALSE) {
                $isFB = $isFB || TRUE;
            }
        }

        return $isFB;
    }
}
