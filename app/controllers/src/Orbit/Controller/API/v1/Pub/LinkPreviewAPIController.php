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

        $rawQueryString = OrbitInput::get('_escaped_fragment_');
        if (Config::get('app.debug')) {
            // --- debugging url sample ---
            // store detail inside mall
            // $rawQueryString = '/malls/KsQZo8XoxR45pEEx/stores/KsQZo8XoxR45pEEY?country=Indonesia&cities=Depok&sortby=created_date&sortmode=desc&order=latest&lang=en';
            // store detail
            $rawQueryString = '/stores/KsQZo8XoxR45pEEY/bale-nyonya?country=Indonesia&cities=Depok&sortby=created_date&sortmode=desc&order=latest&lang=en';
            // store list
            // $rawQueryString = '/stores?country=Indonesia&cities=Depok&sortby=created_date&sortmode=desc&order=latest&lang=en';
        }
        $queryString = urldecode(urldecode($rawQueryString));

        $data = LinkPreviewHelper::create($queryString)->getData();

        return View::make('mobile-ci.templates.sharer', compact('data'));
    }
}
