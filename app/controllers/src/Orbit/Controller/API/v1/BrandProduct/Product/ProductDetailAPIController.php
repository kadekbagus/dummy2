<?php

namespace Orbit\Controller\API\v1\BrandProduct\Product;

use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Validator;
use Helper\EloquentRecordCounter as RecordCounter;
use Orbit\Helper\Util\PaginationNumber;
use stdclass;
use Lang;
use Config;
use Event;
use BrandProduct;
use DB;
use Exception;
use App;
use Request;

class ProductDetailAPIController extends ControllerAPI
{

    /**
     * Product list on brand product portal.
     *
     * @author ahmad <ahmad@dominopos.com>
     */
    public function getProductDetail()
    {
        try {
            $httpCode = 200;

            $user = App::make('currentUser');
            $userId = $user->bpp_user_id;
            $brandId = $user->base_merchant_id;
            $merchantId = $user->merchant_id;
            $productId = OrbitInput::get('brand_product_id', null);

            $validator = Validator::make(
                array(
                    'product_id'    => $productId,
                ),
                array(
                    'product_id'    => 'required',
                )
            );

            $prefix = DB::getTablePrefix();

            $product = BrandProduct::select(DB::raw("
                    {$prefix}brand_products.brand_product_id,
                    {$prefix}brand_products.product_name,
                    {$prefix}brand_products.description,
                    {$prefix}brand_products.tnc,
                    {$prefix}brand_products.max_reservation_time,
                    {$prefix}brand_products.status
                "))
                ->with([
                    'brand_product_main_photo' => function($q) {
                        $q->select('media_id', 'path', 'cdn_url');
                    },
                    'brand_product_photos' => function($q) {
                        $q->select('media_id', 'path', 'cdn_url');
                    },
                    'videos' => function($q) {
                        $q->select('media_id', 'path', 'cdn_url');
                    },
                    'categories',
                    'brand_product_variants.variant_options.option.variant'
                ]);

            if (! empty($merchantId)) {
                $product->leftJoin('brand_product_variant_options', 'brand_product_variant_options.brand_product_variant_id', '=', 'brand_product_variants.brand_product_variant_id')
                    ->where('brand_product_variant_options.option_type', 'merchant')
                    ->where('brand_product_reservation_details.option_id', $merchantId);
            }

            $product->groupBy('brand_products.brand_product_id');
            $product->firstOrFail();

            $this->response->data = $product;

        } catch (Exception $e) {
            return $this->handleException($e);
        }

        return $this->render();
    }

}
