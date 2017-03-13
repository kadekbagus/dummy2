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
        $input['url'] = $url->getUrl();

        switch (count($url->getSegments())) {
            case 0:
                // home page
                $input['objectType'] = 'home';
                $input['linkType'] = null;
                $input['objectId'] = null;
                break;
            case 1:
                // list and other page
                switch ($url->getSegmentAt(0)) {
                    case 'stores':
                        $input['objectType'] = 'store';
                        $input['linkType'] = 'list';
                        $input['objectId'] = null;
                        break;

                    default:
                        # code...
                        break;
                }
                break;
            case 3:
                // detail page
                switch ($url->getSegmentAt(0)) {
                    case 'stores':
                        // store detail page
                        $input['objectType'] = 'store';
                        $input['linkType'] = 'detail';
                        $input['objectId'] = $url->getSegmentAt(1);
                        break;

                    default:
                        # code...
                        break;
                }
                break;
            case 4:
                // mall list page
                switch ($url->getSegmentAt(3)) {
                    case 'stores':
                        // store detail page
                        $input['objectType'] = 'store';
                        $input['linkType'] = 'list';
                        $input['mallName'] = $this->getMallName($url->getSegmentAt(1));
                        $input['objectId'] = null;
                        break;

                    default:
                        # code...
                        break;
                }
                break;
            case 6:
                // mall level detail page
                switch ($url->getSegmentAt(3)) {
                    case 'stores':
                        // store detail page
                        $input['objectType'] = 'store';
                        $input['linkType'] = 'detail';
                        $input['objectId'] = $url->getSegmentAt(4);
                    break;

                }
                break;

            default:
                # code...
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
}
