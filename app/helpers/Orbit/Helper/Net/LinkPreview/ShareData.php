<?php namespace Orbit\Helper\Net\LinkPreview;

use Lang;
use Config;

class ShareData
{
    public $lang = 'en';
    public $title = '';
    public $description = '';
    public $url = 'https://www.gotomalls.com';
    public $imageUrl = 'https://s3-ap-southeast-1.amazonaws.com/static1.gotomalls.com/social-media/general/social_media_banner.jpg';
    public $imageDimension = [1468, 768];

    /**
     * ShareData constructor.
     */
    public function __construct($title = '', $description = '', $url = '', $imageUrl = '', array $imageDimension = [], $lang = '')
    {
        Config::set('app.locale', $lang);
        $this->title = empty($title) ? Lang::get('metatags.title') : $title . ' - ' . Lang::get('metatags.title');
        $this->description = empty($description) ? Lang::get('metatags.description') : $description;
        $this->url = ! empty($url) ? $url : $this->url;
        $this->imageUrl = ! empty($imageUrl) ? $imageUrl : $this->imageUrl;
        $this->imageDimension = ! empty($imageDimension) ? $imageDimension : $this->imageDimension;
    }
}