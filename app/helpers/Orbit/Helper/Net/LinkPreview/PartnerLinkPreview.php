<?php namespace Orbit\Helper\Net\LinkPreview;

use Partner;
use DB;
use Config;

class PartnerLinkPreview implements ObjectLinkPreviewInterface
{
    protected $input;

    public static function create()
    {
        return new static();
    }

    public function setInput(array $input)
    {
        $this->input = $input;
        return $this;
    }

    public function getInput()
    {
        return $this->input;
    }

    public function getPreviewData()
    {
        $prefix = DB::getTablePrefix();
        $usingCdn = Config::get('orbit.cdn.enable_cdn', FALSE);
        $defaultUrlPrefix = Config::get('orbit.cdn.providers.default.url_prefix', '');
        $urlPrefix = ($defaultUrlPrefix != '') ? $defaultUrlPrefix . '/' : '';
        $lang = $this->input['lang'];

        $logo = "CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path) as logo_url";
        if ($usingCdn) {
            $logo = "CASE WHEN ({$prefix}media.cdn_url is null or {$prefix}media.cdn_url = '') THEN CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path) ELSE {$prefix}media.cdn_url END as logo_url";
        }

        $data = Partner::select(
                'partners.partner_name',
                'partners.partner_id',
                DB::raw("{$logo}"),
                DB::Raw("
                    CASE WHEN ({$prefix}partner_translations.description = '' or {$prefix}partner_translations.description is null) THEN default_translation.description ELSE {$prefix}partner_translations.description END as description
                ")
            )
            ->leftJoin('media', function ($q) {
                $q->on('media.object_id', '=', 'partners.partner_id');
                $q->on('media.object_name', '=', DB::raw("'partner'"));
                $q->on('media.media_name_long', '=', DB::raw("'partner_logo_orig'"));
            })
            ->leftJoin('media as image_media', function ($q) {
                $q->on(DB::raw("image_media.object_id"), '=', 'partners.partner_id');
                $q->on(DB::raw("image_media.object_name"), '=', DB::raw("'partner'"));
                $q->on(DB::raw("image_media.media_name_long"), '=', DB::raw("'partner_image_orig'"));
            })
            ->leftJoin('partner_translations', function ($q) use ($lang) {
                $q->on('partner_translations.partner_id', '=', 'partners.partner_id')
                  ->on('partner_translations.language_id', '=', DB::raw("{$this->quote($lang->language_id)}"));
            })
            ->leftJoin('languages', 'languages.name' , '=', 'partners.mobile_default_language')
            ->leftJoin('partner_translations as default_translation', function ($q) use ($prefix){
                $q->on(DB::raw("default_translation.partner_id"), '=', 'partners.partner_id')
                  ->on(DB::raw("default_translation.language_id"), '=', 'languages.language_id');
            })
            ->where('partners.status', 'active')
            ->where('partners.partner_id', $this->input['objectId'])
            ->groupBy('partners.partner_id')
            ->first();

        if (is_object($data)) {
            $title = $data->partner_name;
            $description = $data->description;
            $imageUrl = $data->logo_url;
            list($width, $height) = getimagesize($imageUrl);
            $imageDimension = [$width, $height];
            $lang = $this->input['lang'];
            $url = $this->input['url'];
            $previewData = new LinkPreviewData($title, $description, $url, $imageUrl, $imageDimension, $lang->name);
        } else {
            $previewData = new LinkPreviewData();
        }

        return $previewData;
    }

    private function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }
}
