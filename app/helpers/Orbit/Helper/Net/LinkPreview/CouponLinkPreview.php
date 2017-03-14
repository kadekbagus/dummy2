<?php namespace Orbit\Helper\Net\LinkPreview;

use Coupon;
use DB;
use Config;
use Lang;
use App;

class CouponLinkPreview implements ObjectLinkPreviewInterface
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
        // detail page
        if ($this->input['linkType'] === 'detail') {
            $prefix = DB::getTablePrefix();
            $usingCdn = Config::get('orbit.cdn.enable_cdn', FALSE);
            $defaultUrlPrefix = Config::get('orbit.cdn.providers.default.url_prefix', '');
            $urlPrefix = ($defaultUrlPrefix != '') ? $defaultUrlPrefix . '/' : '';
            $lang = $this->input['lang'];

            $image = "CONCAT({$this->quote($urlPrefix)}, m.path)";
            if ($usingCdn) {
                $image = "CASE WHEN m.cdn_url IS NULL THEN CONCAT({$this->quote($urlPrefix)}, m.path) ELSE m.cdn_url END";
            }

            $data = Coupon::select(
                        'promotions.promotion_id as promotion_id',
                        DB::Raw("
                                CASE WHEN ({$prefix}coupon_translations.promotion_name = '' or {$prefix}coupon_translations.promotion_name is null) THEN default_translation.promotion_name ELSE {$prefix}coupon_translations.promotion_name END as promotion_name,
                                CASE WHEN ({$prefix}coupon_translations.description = '' or {$prefix}coupon_translations.description is null) THEN default_translation.description ELSE {$prefix}coupon_translations.description END as description,
                                CASE WHEN (SELECT {$image}
                                    FROM orb_media m
                                    WHERE m.media_name_long = 'coupon_translation_image_orig'
                                    AND m.object_id = {$prefix}coupon_translations.coupon_translation_id) is null
                                THEN
                                    (SELECT {$image}
                                    FROM orb_media m
                                    WHERE m.media_name_long = 'coupon_translation_image_orig'
                                    AND m.object_id = default_translation.coupon_translation_id)
                                ELSE
                                    (SELECT {$image}
                                    FROM orb_media m
                                    WHERE m.media_name_long = 'coupon_translation_image_orig'
                                    AND m.object_id = {$prefix}coupon_translations.coupon_translation_id)
                                END AS original_media_path
                            ")
                    )
                    ->join('campaign_account', 'campaign_account.user_id', '=', 'promotions.created_by')
                    ->join('languages', 'languages.name', '=', 'campaign_account.mobile_default_language')
                    ->leftJoin('coupon_translations', function ($q) use ($lang) {
                        $q->on('coupon_translations.promotion_id', '=', 'promotions.promotion_id')
                          ->on('coupon_translations.merchant_language_id', '=', DB::raw("{$this->quote($lang->language_id)}"));
                    })
                    ->leftJoin('coupon_translations as default_translation', function ($q) {
                        $q->on(DB::raw('default_translation.promotion_id'), '=', 'promotions.promotion_id')
                          ->on(DB::raw('default_translation.merchant_language_id'), '=', 'languages.language_id');
                    })
                    ->where('promotions.promotion_id', $this->input['objectId'])
                    ->first();

            if (is_object($data)) {
                $title = $data->promotion_name;
                $description = $data->description;
                $imageUrl = $data->original_media_path;
                list($width, $height) = getimagesize($imageUrl);
                $imageDimension = [$width, $height];
                $lang = $this->input['lang'];
                $url = $this->input['url'];
                $previewData = new LinkPreviewData($title, $description, $url, $imageUrl, $imageDimension, $lang->name);
            } else {
                $previewData = new LinkPreviewData();
            }
        } else {
            // list page
            $lang = $this->input['lang'];
            App::setLocale($lang->name);
            $title = isset($this->input['mallName']) ? sprintf(Lang::get('metatags.coupon_list.mall.title'), $this->input['mallName']) : Lang::get('metatags.coupon_list.gtm.title');
            $description = isset($this->input['mallName']) ? sprintf(Lang::get('metatags.coupon_list.mall.description'), $this->input['mallName']) : Lang::get('metatags.coupon_list.gtm.description');
            $url = $this->input['url'];
            $previewData = new LinkPreviewData($title, $description, $url, '', [], $lang->name);
        }

        return $previewData;
    }

    private function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }
}
