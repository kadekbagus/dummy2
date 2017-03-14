<?php namespace Orbit\Helper\Net\LinkPreview;
/**
 * Helper Class to get preview data that will be passed to dedicated view for crawler bot
 * @author Ahmad <ahmad@dominopos.com>
 */
use Config;
use Language;
use Orbit\Helper\Util\OrbitUrlSegmentParser;
use Mall;

class LinkPreviewHelper
{
    protected $queryString = '';

    protected $lang = 'en';

    /**
     * @param string $queryString
     * @return void
     */
    public function __construct($queryString)
    {
        $this->queryString = $queryString;
    }

    /**
     * @param string $queryString
     * @return LinkPreviewHelper
     */
    public static function create($queryString)
    {
        return new static($queryString);
    }

    /**
     * @return Orbit\Helper\Net\LinkPreview\LinkPreviewData
     */
    public function getData()
    {
        $url = OrbitUrlSegmentParser::create($this->queryString);

        $input = [];

        $langFromQueryString = $url->getQueryStringValueFor('lang');

        $this->lang = ! is_null($langFromQueryString) ? $langFromQueryString : $this->lang;

        $langObject = Language::where('status', '=', 'active')
            ->where('name', $this->lang)
            ->first();

        $input['lang'] = $langObject;
        $hashbang = Config::get('orbit.sitemap.hashbang', TRUE) ? '/#!/' : '/';
        $input['url'] = rtrim(Config::get('app.url'), '/') . $hashbang . ltrim($url->getUrl(), '/');

        switch (count($url->getSegments())) {
            case 0:
            default:
                // home page
                $input['objectType'] = 'home';
                $input['linkType'] = null;
                $input['objectId'] = null;
                break;
            case 1:
                // list and other page
                $input['linkType'] = 'list';
                $input['objectId'] = null;
                $input['objectType'] = $this->setInputObjectType($url->getSegmentAt(0));
                break;
            case 3:
                // detail page
                $input['linkType'] = 'detail';
                $input['objectId'] = $url->getSegmentAt(1);
                $input['objectType'] = $this->setInputObjectType($url->getSegmentAt(0));
                break;
            case 4:
                // mall list page
                $input['linkType'] = 'list';
                $input['mallName'] = $this->getMallName($url->getSegmentAt(1));
                $input['objectId'] = null;
                $input['objectType'] = $this->setInputObjectType($url->getSegmentAt(3));
                break;
            case 6:
                // mall level detail page
                $input['linkType'] = 'detail';
                $input['objectId'] = $url->getSegmentAt(4);
                $input['objectType'] = $this->setInputObjectType($url->getSegmentAt(3));
                break;
        }

        $data = ObjectLinkPreviewFactory::create($input, $input['linkType'])->getData();

        return $data;
    }

    protected function getMallName($mallId)
    {
        $mall = Mall::where('merchant_id', $mallId)
            ->first();

        $mallName = is_object($mall) ? $mall->name : '';

        return $mallName;
    }

    protected function setInputObjectType($identifier)
    {
        $objectType = null;
        switch ($identifier) {
            case 'stores':
                $objectType = 'store';
                break;

            case 'promotions':
                $objectType = 'promotion';
                break;

            case 'coupons':
                $objectType = 'coupon';
                break;

            case 'events':
                $objectType = 'event';
                break;

            case 'malls':
                $objectType = 'mall';
                break;

            case 'partner':
                $objectType = 'partner';
                break;
        }

        return $objectType;
    }
}
