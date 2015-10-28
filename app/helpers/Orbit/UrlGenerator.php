<?php
namespace Orbit;


use DominoPOS\OrbitSession\Session;
use DominoPOS\OrbitSession\SessionConfig;

/**
 * Extends url generator to insert session id in url.
 *
 * @package Orbit
 */
class UrlGenerator extends \Illuminate\Routing\UrlGenerator
{
    /**
     * @return string
     */
    protected function getSessionIdParameterName()
    {
        return Config::get('orbit.session.session_origin.query_string.name', '');
    }

    /**
     * @return null|string
     * @throws \Exception
     */
    protected function getSessionIdValue()
    {
        $config = new SessionConfig(Config::get('orbit.session'));
        $session = new Session($config);
        try {
            $session->start([], 'no-session-creation');
            return $session->getSessionId();
        } catch (\Exception $e) {
            if ($e->getCode() === Session::ERR_SESS_NOT_FOUND) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * @return bool
     */
    protected function getSessionInUrlEnabled()
    {
        $enabled = Config::get('orbit.session.availability.query_string', false);
        $name_set = Config::get('orbit.session.session_origin.query_string.name', null) !== null;
        return ($enabled && $name_set);
    }

    protected function getRouteQueryString(array $parameters)
    {
        if ($this->getSessionInUrlEnabled()) {
            $id = $this->getSessionIdValue();
            if ($id !== null) {
                $parameters[$this->getSessionIdParameterName()] = $id;
            }
        }
        return parent::getRouteQueryString($parameters);
    }

    public function to($path, $extra = array(), $secure = null)
    {
        $original = parent::to($path, $extra, $secure);

        if (!$this->getSessionInUrlEnabled()) {
            return $original;
        }
        $id = $this->getSessionIdValue();
        if ($id === null) {
            return $original;
        }

        $additional_query = http_build_query([$this->getSessionIdParameterName() => $id]);

        $insert_qmark = false;
        $insert_ampersand = false;
        $insert_at = -1;

        $frag = strpos($original, '#');
        $qmark = strpos($original, '?');
        if ($frag !== false) {
            if ($qmark !== false) {
                if ($qmark > $frag) {
                    // ....#...?...
                    $insert_at = $frag;
                    $insert_qmark = true;
                } else {
                    // ....?...#...
                    $insert_at = $qmark + 1;
                    $insert_ampersand = true;
                }
            } else {
                // ....#...
                $insert_at = $frag;
                $insert_qmark = true;
            }
        } else {
            if ($qmark !== false) {
                // ....?...
                $insert_at = $qmark + 1;
                $insert_ampersand = true;
            } else {
                // ....
                $insert_at = strlen($original);
                $insert_qmark = true;
            }
        }

        if ($insert_at !== -1) {
            $pre = substr($original, 0, $insert_at);
            $post = substr($original, $insert_at);
            $result = $pre . ($insert_qmark ? '?' : '') . $additional_query . ($insert_ampersand ? '&' : '') . $post;
        } else {
            $result = $original;
        }

        return $result;
    }


}
