<?php namespace Orbit\Helper\Net\LinkPreview;

use Config;
use Language;

class LinkPreviewHelper
{
    protected $queryString = '';

    protected $lang = 'en';

    public function __construct($queryString)
    {
        $this->queryString = $queryString;
    }

    public static function create($queryString)
    {
        return new static($queryString);
    }

    public function getData()
    {
        $input = $this->analyzeQueryString();
        $data = null;

        switch ($input['objectType']) {
            case 'store':
                // $data = StoreLinkPreview::create()->setInput($input)->getShareData();
            $data = ObjectLinkPreviewFactory::create($input, $input['linkType'])->getInstance();
                break;

            case 'store':
                $data = StoreLinkPreview::create()->setInput($input)->getShareData();
                break;

            default:
                # code...
                break;
        }
        return $data;
    }
/*
    # store detail
    ## with hashbang
    /pub/sharer?_escaped_fragment_=/stores/KsQZo8XoxR45pEEY/bale-nyonya?country=Indonesia&cities=Depok&sortby=created_date&sortmode=desc&order=latest
    ## without hashbang
    /stores/KsQZo8XoxR45pEEY/bale-nyonya?country=Indonesia&cities=Depok&sortby=created_date&sortmode=desc&order=latest

    # store list
    /pub/sharer?_escaped_fragment_=%2Fstores%3Fcountry%3DIndonesia%26cities%3DDepok%26cities%3DSurabaya%26sortby%3Dcreated_date%26sortmode%3Ddesc%26order%3Dlatest

    #home
    /pub/sharer?_escaped_fragment_=%2
    /
*/

    private function analyzeQueryString()
    {
        $segments = explode('/', $this->queryString);
        $url = rtrim(Config::get('app.url'), '/') . $this->queryString;
        $input = [];
        $langPos = strpos($this->queryString, 'lang=');
        if ($langPos !== false) {
            $this->lang = substr($this->queryString, ($langPos + 5), 2);
        }

        $langObject = Language::where('status', '=', 'active')
            ->where('name', $this->lang)
            ->first();

        switch (count($segments)) {
            case 4:
                switch ($segments[1]) {
                    case 'stores':
                        $input['objectType'] = 'store';
                        $input['linkType'] = 'detail';
                        $input['objectId'] = $segments[2];
                        $input['lang'] = $langObject;
                        $input['url'] = $url;
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

        return $input;
    }


}
