<?php namespace Orbit\Controller\API\v1\Pub;

use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Orbit\Helper\Net\LinkPreview\LinkPreviewHelper;
use Config;
use View;

class LinkPreviewAPIController extends PubControllerAPI
{
    public function getDataPreview()
    {
        # /stores/KsQZo8XoxR45pEEY/bale-nyonya?country=Indonesia&cities=Depok&sortby=created_date&sortmode=desc&order=latest
        if (Config::get('orbit.link_preview.hashbang_enable', TRUE)) {
            $rawQueryString = OrbitInput::get('_escaped_fragment_');
            if (Config::get('app.debug')) {
                $rawQueryString = '/stores/KsQZo8XoxR45pEEY/bale-nyonya?country=Indonesia&cities=Depok&sortby=created_date&sortmode=desc&order=latest&lang=en';
            }
            $queryString = urldecode(urldecode($rawQueryString));
        } else {
            $rawQueryString = $_SERVER['REQUEST_URI'];
            dd($rawQueryString);
        }

        $data = LinkPreviewHelper::create($queryString)->getData();

        // $pretext = isset($object_name) ? $object_name : '';
        // $data = new stdClass();
        // $data->title = sprintf('%sInfo diskon dan outlet terlengkap - Gotomalls.com', $pretext);
        // $data->url =

        return View::make('mobile-ci.templates.sharer', compact('data'));
    }
}
