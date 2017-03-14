<?php namespace Orbit\Helper\Net\LinkPreview;
/**
 * Class to get Store Detail type LinkPreviewData
 *
 * @author Ahmad <ahmad@dominopos.com>
 */
use Tenant;
use DB;
use Config;
use Lang;
use App;

class StoreLinkPreview implements ObjectLinkPreviewInterface
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

            $image = "CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path) as path";
            if ($usingCdn) {
                $image = "CASE WHEN ({$prefix}media.cdn_url is null or {$prefix}media.cdn_url = '') THEN CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path) ELSE {$prefix}media.cdn_url END as path";
            }

            $data = Tenant::select(
                                    'merchants.merchant_id',
                                    'merchants.name',
                                    DB::Raw("CASE WHEN (
                                                    select mt.description
                                                    from {$prefix}merchant_translations mt
                                                    where mt.merchant_id = {$prefix}merchants.merchant_id
                                                        and mt.merchant_language_id = {$this->quote($lang->language_id)}
                                                ) = ''
                                                THEN (
                                                    select mt.description
                                                    from {$prefix}merchant_translations mt
                                                    where mt.merchant_id = {$prefix}merchants.merchant_id
                                                        and mt.merchant_language_id = {$prefix}languages.language_id
                                                )
                                                ELSE (
                                                    select mt.description
                                                    from {$prefix}merchant_translations mt
                                                    where mt.merchant_id = {$prefix}merchants.merchant_id
                                                        and mt.merchant_language_id = {$this->quote($lang->language_id)}
                                                )
                                            END as description
                                        ")
                                )
                    ->with(['mediaLogo' => function ($q) use ($image) {
                            $q->select(
                                    DB::raw("{$image}"),
                                    'media.object_id'
                                );
                        }])
                    ->join(DB::raw("(select merchant_id, status, parent_id from {$prefix}merchants where object_type = 'mall') as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                    ->join('languages', 'languages.name', '=', 'merchants.mobile_default_language')
                    ->where('merchants.status', 'active')
                    ->where('merchants.merchant_id', $this->input['objectId'])
                    ->orderBy('merchants.created_at', 'asc')
                    ->first();

            if (is_object($data)) {
                $title = $data->name;
                $description = $data->description;
                $imageUrl = $data->mediaLogo[0]->path;
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
            $title = isset($this->input['mallName']) ? sprintf(Lang::get('metatags.store_list.mall.title'), $this->input['mallName']) : Lang::get('metatags.store_list.gtm.title');
            $description = isset($this->input['mallName']) ? sprintf(Lang::get('metatags.store_list.mall.description'), $this->input['mallName']) : Lang::get('metatags.store_list.gtm.description');
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
