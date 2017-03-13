<?php namespace Orbit\Helper\Net\LinkPreview;

use News;
use DB;
use Config;

class NewsLinkPreview implements ObjectLinkPreviewInterface
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

        $image = "CONCAT({$this->quote($urlPrefix)}, m.path)";
        if ($usingCdn) {
            $image = "CASE WHEN m.cdn_url IS NULL THEN CONCAT({$this->quote($urlPrefix)}, m.path) ELSE m.cdn_url END";
        }

        $data = News::select(
                        'news.news_id as news_id',
                        DB::Raw("
                            CASE WHEN ({$prefix}news_translations.news_name = '' or {$prefix}news_translations.news_name is null) THEN default_translation.news_name ELSE {$prefix}news_translations.news_name END as news_name,
                            CASE WHEN ({$prefix}news_translations.description = '' or {$prefix}news_translations.description is null) THEN default_translation.description ELSE {$prefix}news_translations.description END as description,
                            CASE WHEN (SELECT {$image}
                                FROM orb_media m
                                WHERE m.media_name_long = 'news_translation_image_orig'
                                AND m.object_id = {$prefix}news_translations.news_translation_id) is null
                            THEN
                                (SELECT {$image}
                                FROM orb_media m
                                WHERE m.media_name_long = 'news_translation_image_orig'
                                AND m.object_id = default_translation.news_translation_id)
                            ELSE
                                (SELECT {$image}
                                FROM orb_media m
                                WHERE m.media_name_long = 'news_translation_image_orig'
                                AND m.object_id = {$prefix}news_translations.news_translation_id)
                            END AS original_media_path
                        ")
                    )
                    ->join('campaign_account', 'campaign_account.user_id', '=', 'news.created_by')
                    ->join('languages', 'languages.name', '=', 'campaign_account.mobile_default_language')
                    ->leftJoin('news_translations', function ($q) use ($lang) {
                        $q->on('news_translations.news_id', '=', 'news.news_id')
                          ->on('news_translations.merchant_language_id', '=', DB::raw("{$this->quote($lang->language_id)}"));
                    })
                    ->leftJoin('news_translations as default_translation', function ($q) {
                        $q->on(DB::raw("default_translation.news_id"), '=', 'news.news_id')
                          ->on(DB::raw("default_translation.merchant_language_id"), '=', 'languages.language_id');
                    })
                    ->where('news.news_id', $this->input['objectId'])
                    ->where('news.object_type', '=', 'news')
                    ->first();

        if (is_object($data)) {
            $title = $data->news_name;
            $description = $data->description;
            $imageUrl = $data->original_media_path;
            list($width, $height) = getimagesize($imageUrl);
            $imageDimension = [$width, $height];
            $lang = $this->input['lang'];
            $url = $this->input['url'];
            $shareData = new ShareData($title, $description, $url, $imageUrl, $imageDimension, $lang->name);
        } else {
            $shareData = new ShareData();
        }

        return $shareData;
    }

    private function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }
}
