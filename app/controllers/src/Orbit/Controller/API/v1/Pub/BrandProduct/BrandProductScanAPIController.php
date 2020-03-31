<?php

namespace Orbit\Controller\API\v1\Pub\BrandProduct;

use Exception;
use BrandProductVariant;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Validator;


/**
 * Brand product scan controller.
 *
 * @author Ahmad <ahmad@gotomalls.com>
 */
class BrandProductScanAPIController extends PubControllerAPI
{
    public function handle()
    {
        try {
            $barcode = OrbitInput::post('barcode');
            $storeId = OrbitInput::post('store_id');

            $validator = Validator::make(
                array(
                    'barcode'      => $barcode,
                    'store_id'      => $storeId,
                ),
                array(
                    'barcode'      => 'required',
                    'store_id'      => 'required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $product = BrandProductVariant::select('product_name', 'brand_products.brand_product_id', 'path', 'cdn_url')
                ->leftJoin('brand_products', 'brand_products.brand_product_id', '=', 'brand_product_variants.brand_product_id')
                ->leftJoin('brand_product_variant_options', 'brand_product_variant_options.brand_product_variant_id', '=', 'brand_product_variants.brand_product_variant_id')
                ->leftJoin('media', 'media.object_id', '=', 'brand_products.brand_product_id')
                ->where('media_name_long', 'brand_product_main_photo_mobile_thumb')
                ->where('product_code', $barcode)
                ->where('option_type', 'merchant')
                ->where('option_id', $storeId)
                ->groupBy('brand_product_variants.brand_product_variant_id')
                ->firstOrFail();

            $this->response->data = $product;
        } catch (Exception $e) {
            return $this->handleException($e, false);
        }

        return $this->render();
    }
}
