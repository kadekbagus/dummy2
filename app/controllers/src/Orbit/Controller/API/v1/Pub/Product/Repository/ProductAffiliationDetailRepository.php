<?php

namespace Orbit\Controller\API\v1\Pub\Product\Repository;

use DB;
use Config;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Validator;
use Language;
use Product;

/**
 * Product Affiliation Repository.
 *
 * @author kadek <kadek@gotomalls.com>
 */
class ProductAffiliationDetailRepository
{
    protected $validLanguage = NULL;

    public function __construct()
    {

    }

    /**
     * Get collection based on requested filter.
     *
     * @return Illuminate\Database\Eloquent\Builder
     */
    public function getProduct($id, $request)
    {
        $productId = OrbitInput::get('product_id');
        $language = OrbitInput::get('language', 'id');

        $this->registerCustomValidation();
        $validator = Validator::make(
            array(
                'language' => $language,
            ),
            array(
                'language' => 'orbit.empty.language_default',
            )
        );

        // Run the validation
        if ($validator->fails()) {
            $errorMessage = $validator->messages()->first();
            OrbitShopAPI::throwInvalidArgument($errorMessage);
        }

        $prefix = DB::getTablePrefix();
        $usingCdn = Config::get('orbit.cdn.enable_cdn', FALSE);
        $defaultUrlPrefix = Config::get('orbit.cdn.providers.default.url_prefix', '');
        $urlPrefix = ($defaultUrlPrefix != '') ? $defaultUrlPrefix . '/' : '';

        $image = "CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path) as cdn_url";
        if ($usingCdn) {
            $image = "CASE WHEN ({$prefix}media.cdn_url is null or {$prefix}media.cdn_url = '') THEN CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path) ELSE {$prefix}media.cdn_url END as cdn_url";
        }

        $validLanguage = $this->validLanguage;

        $product = Product::with([
            'media' => function ($q) use ($image) {
                                $q->select(
                                        DB::raw("{$image}"),
                                        'media.media_id',
                                        'media.media_name_id',
                                        'media.media_name_long',
                                        'media.object_id',
                                        'media.object_name',
                                        'media.file_name',
                                        'media.file_extension',
                                        'media.file_size',
                                        'media.mime_type',
                                        'media.path',
                                        'media.cdn_bucket_name',
                                        'media.metadata'
                                    );
            },
            'marketplaces' => function ($q) use ($image) {
                $q->with(['media' => function ($q) use ($image) {
                                $q->select(
                                        DB::raw("{$image}"),
                                        'media.media_id',
                                        'media.media_name_id',
                                        'media.media_name_long',
                                        'media.object_id',
                                        'media.object_name',
                                        'media.file_name',
                                        'media.file_extension',
                                        'media.file_size',
                                        'media.mime_type',
                                        'media.path',
                                        'media.cdn_bucket_name',
                                        'media.metadata'
                                    );
                          }]);
                $q->where('marketplaces.status', 'active');
            },
            'country',
            'categories' => function ($q) use ($validLanguage, $prefix) {
                $q->select(
                        DB::Raw("
                                CASE WHEN (
                                            SELECT ct.category_name
                                            FROM {$prefix}category_translations ct
                                                WHERE ct.status = 'active'
                                                    and ct.merchant_language_id = {$this->quote($validLanguage->language_id)}
                                                    and ct.category_id = {$prefix}categories.category_id
                                            ) != ''
                                    THEN (
                                            SELECT ct.category_name
                                            FROM {$prefix}category_translations ct
                                            WHERE ct.status = 'active'
                                                and ct.merchant_language_id = {$this->quote($validLanguage->language_id)}
                                                and category_id = {$prefix}categories.category_id
                                            )
                                    ELSE {$prefix}categories.category_name
                                END AS category_name
                            ")
                    )
                    ->groupBy('categories.category_id')
                    ->orderBy('category_name');
            },
            'videos',
            'product_photos' => function ($q) use ($image) {
                                $q->select(
                                        DB::raw("{$image}"),
                                        'media.media_id',
                                        'media.media_name_id',
                                        'media.media_name_long',
                                        'media.object_id',
                                        'media.object_name',
                                        'media.file_name',
                                        'media.file_extension',
                                        'media.file_size',
                                        'media.mime_type',
                                        'media.path',
                                        'media.cdn_bucket_name',
                                        'media.metadata'
                                    )->where('media_name_long', 'product_photos_orig');
                          },
            'merchants' => function ($q) use ($prefix) {
                        $q->select(
                            DB::raw("
                                    {$prefix}base_merchants.name,
                                    {$prefix}base_merchants.base_merchant_id,
                                    {$prefix}countries.name as country_name,
                                    {$prefix}base_stores.base_store_id as merchant_id
                                    "
                                )
                        )
                        ->leftJoin('countries', 'base_merchants.country_id', '=', 'countries.country_id')
                        ->leftJoin('base_stores', 'base_stores.base_merchant_id', '=', 'base_merchants.base_merchant_id')
                        ->where('base_merchants.status', 'active')
                        ->where('base_stores.status', 'active')
                        ->groupBy('base_merchants.base_merchant_id');
                    }
        ])
        ->where('product_id', $productId)
        ->firstOrFail();

        return $product;
    }

    protected function registerCustomValidation() {
        // Check language is exists
        Validator::extend('orbit.empty.language_default', function ($attribute, $value, $parameters) {
            $lang_name = $value;

            $language = Language::where('status', '=', 'active')
                            ->where('name', $lang_name)
                            ->first();

            if (empty($language)) {
                return FALSE;
            }

            $this->validLanguage = $language;
            return TRUE;
        });
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }
}
