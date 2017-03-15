<?php namespace Orbit\Controller\API\v1\Pub;
/**
 * API for getting preview data for shared URL that crawled by bots
 * @author Ahmad <ahmad@dominopos.com>
 */
use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Orbit\Helper\Net\LinkPreview\LinkPreviewHelper;
use Config;
use View;

class LinkPreviewAPIController extends PubControllerAPI
{
    public function getDataPreview()
    {
        $rawQueryString = $_SERVER['REQUEST_URI'];
        $uriArray = explode('/pub/sharer?_escaped_fragment_=', $rawQueryString);
        $uriArray = array_filter($uriArray, function($value) {
            return $value !== '';
        });
        $uriArray = array_values($uriArray);
        $uri = $uriArray[0];

        if (Config::get('app.debug')) {
            if (! empty(Config::get('orbit.link_preview.uri', ''))) {
                $uri = Config::get('orbit.link_preview.uri');
            }
        }
        $queryString = urldecode(urldecode($uri));
        $data = LinkPreviewHelper::create($queryString)->getData();

        return View::make('mobile-ci.templates.sharer', compact('data'));
    }
}
