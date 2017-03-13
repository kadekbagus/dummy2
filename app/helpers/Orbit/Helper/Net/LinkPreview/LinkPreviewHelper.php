<?php namespace Orbit\Helper\Net\LinkPreview;
/**
 * Helper Class to get preview data that will be passed to dedicated view for crawler bot
 * @author Ahmad <ahmad@dominopos.com>
 */
use Config;
use Language;
use Orbit\Helper\Util\OrbitUrlSegmentParser;

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

        switch (count($url->getSegments())) {
            case 3:
                // detail page
                switch ($url->getSegmentAt(0)) {
                    case 'stores':
                        // store detail page
                        $input['objectType'] = 'store';
                                $input['linkType'] = 'detail';
                                $input['objectId'] = $url->getSegmentAt(1);
                                $input['lang'] = $langObject;
                                $input['url'] = $url->getUrl();
                        break;

                    default:
                        # code...
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
}
