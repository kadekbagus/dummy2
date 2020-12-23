<?php

namespace Orbit\Controller\API\v1\BrandProduct\Repository;

use App;
use BaseMerchant;
use BrandProduct;
use DB;
use Language;
use Orbit\Controller\API\v1\BrandProduct\Repository\Handlers\HandleUpdate;
use Orbit\Controller\API\v1\Pub\DigitalProduct\Helper\MediaQuery;
use ProductLinkToObject;
use Request;
use Variant;
use Category;

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
                'brand_product_main_photo',
                'brand_product_photos',
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
}
