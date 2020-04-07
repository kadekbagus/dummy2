<?php

namespace Orbit\Controller\API\v1\BrandProduct;

use App;
use BaseMerchant;
use BrandProduct;
use Language;
use Orbit\Controller\API\v1\BrandProduct\Product\DataBuilder\UpdateBrandProductBuilder;
use Orbit\Controller\API\v1\BrandProduct\Repository\Handlers\HandleReservation;
use Orbit\Controller\API\v1\BrandProduct\Repository\Handlers\HandleUpdate;
use Orbit\Controller\API\v1\Pub\DigitalProduct\Helper\MediaQuery;
use Request;
use Variant;

/**
 * Brand Product Repository. An abstraction which unify various Brand Product
 * functionalities (single source of truth).
 *
 * @author Budi <budi@gotomalls.com>
 */
class BrandProductRepository
{
    use MediaQuery,
        HandleReservation,
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

        return BrandProduct::with([
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
