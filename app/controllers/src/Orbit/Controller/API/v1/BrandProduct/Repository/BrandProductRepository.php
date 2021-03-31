<?php

namespace Orbit\Controller\API\v1\BrandProduct\Repository;

use App;
use BaseMerchant;
use BrandProduct;
use BrandProductReservation;
use DB;
use Language;
use Orbit\Controller\API\v1\BrandProduct\Repository\Handlers\HandleUpdate;
use Orbit\Controller\API\v1\Pub\DigitalProduct\Helper\MediaQuery;
use ProductLinkToObject;
use Request;
use Variant;
use Category;
use Config;

/**
 * Brand Product Repository. An abstraction which unify various Brand Product
 * functionalities (single source of truth).
 *
 * @author Budi <budi@gotomalls.com>
 */
class BrandProductRepository
{
    use MediaQuery,
        HandleUpdate;

    protected $imagePrefix = 'brand_product_photos_';

    public function __construct()
    {
        $this->setupImageUrlQuery();
    }

    public function getList($request)
    {
        return BrandProduct::search($request);
    }

    public function get($brandProductId)
    {
        $lang = Request::input('language', 'id');
        $lang = Language::where('status', '=', 'active')
                            ->where('name', $lang)
                            ->first();

        $prefix = DB::getTablePrefix();
        $usingCdn = Config::get('orbit.cdn.enable_cdn', FALSE);
        $defaultUrlPrefix = Config::get('orbit.cdn.providers.default.url_prefix', '');
        $urlPrefix = ($defaultUrlPrefix != '') ? $defaultUrlPrefix . '/' : '';

        $image = "CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path) as cdn_url";
        if ($usingCdn) {
            $image = "CASE WHEN ({$prefix}media.cdn_url is null or {$prefix}media.cdn_url = '') THEN CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path) ELSE {$prefix}media.cdn_url END as cdn_url";
        }

        $brandProduct = BrandProduct::with([
                'categories' => function($query) use ($lang) {
                    $query->select(
                            'categories.category_id',
                            'category_translations.category_name'
                        )
                        ->join('category_translations',
                            'categories.category_id',
                            '=',
                            'category_translations.category_id'
                        )
                        ->where('category_translations.merchant_language_id',
                            $lang->language_id
                        )
                        ->where('categories.merchant_id', 0);
                },
                'brand',
                'videos',
                'brand_product_variants.variant_options',
                'brand_product_variants.reservations' => function($query) {
                    $query->select(
                        'brand_product_variant_id',
                        'brand_product_reservation_id',
                        'quantity'
                    )->whereIn('status', [
                        BrandProductReservation::STATUS_PENDING,
                        BrandProductReservation::STATUS_ACCEPTED,
                        BrandProductReservation::STATUS_DONE,
                    ]);
                },
                'brand_product_main_photo',
                'brand_product_photos',
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
                }
            ])
            ->findOrFail($brandProductId);

        // get category name list on default lang (english)
        $productCategories = Category::select('categories.category_id', 'categories.category_name')
                       ->leftJoin('brand_product_categories', 'categories.category_id', '=', 'brand_product_categories.category_id')
                       ->leftJoin('brand_products', 'brand_products.brand_product_id', '=', 'brand_product_categories.brand_product_id')
                       ->where('categories.status', 'active')
                       ->where('brand_products.brand_product_id', $brandProductId)
                       ->groupBy('categories.category_id')
                       ->get();

        $categoryNames = [];
        foreach ($productCategories as $productCategory) {
            $categoryNames[] = $productCategory->category_name;
        }

        $brandProduct->category_names = $categoryNames;

        return $brandProduct;
    }

    /**
     * Get list of brands which has products.
     *
     * @param  [type] $request [description]
     * @return [type]          [description]
     */
    public function brands($request)
    {
        return BaseMerchant::select(
                'base_merchants.name',
                'base_merchants.base_merchant_id'
            )
            ->with(['mediaLogoOrig', 'products'])
            ->whereHas('products', function($query) {
                $query->where('brand_products.status', 'active');
            })
            ->where('base_merchants.status', 'active')
            ->orderBy('base_merchants.name', 'asc')
            ->whenHas('keyword', function($query, $keyword) {
                return $query->where(
                    'base_merchants.name', 'like', "%{$keyword}%"
                );
            })
            ->skip($request->skip ?: 0)
            ->take($request->take ?: 20)
            ->get();
    }

    /**
     * Get list of brands which has products.
     *
     * @param  [type] $request [description]
     * @return [type]          [description]
     */
    public function brandsWithProductAffiliation($request)
    {
        return ProductLinkToObject::select(
                'product_link_to_object.product_link_to_object_id',
                'product_link_to_object.product_id',
                'product_link_to_object.object_id',
                'product_link_to_object.object_type',
                'products.name'
            )
            ->with(['brand.mediaLogoOrig'])
            ->join('products', 'product_link_to_object.product_id', '=',
                'products.product_id'
            )
            ->where('object_type', 'brand')
            ->where('products.status', 'active')
            ->groupBy('object_id')
            ->get();
    }

    /**
     * Get list of Variant.
     *
     * @param  [type] $request [description]
     * @return [type]          [description]
     */
    public function variants($request)
    {
        $sortBy = $request->sortby ?: 'created_at';
        $sortMode = $request->sortmode ?: 'asc';

        $records = Variant::with(['options']);
        $total = clone $records;
        $total = $total->count();
        $records = $records->orderBy($sortBy, $sortMode)
            ->skip($request->skip)->take($request->take)->get();

        return compact('records', 'total');
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }
}
