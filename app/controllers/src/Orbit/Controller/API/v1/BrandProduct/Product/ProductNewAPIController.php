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

use Lang;
use Config;
use Event;
use BrandProduct;
use DB;
use Exception;
use App;
use BrandProductVideo;
use BrandProductCategory;
use Request;
use Variant;
use VariantOption;
use BrandProductVariant;
use BrandProductVariantOption;

class ProductNewAPIController extends ControllerAPI
{

    /**
     * Create new product on brand product portal.
     *
     * @author kadek <kadek@dominopos.com>
     */
    public function postNewProduct()
    {
        try {
            $httpCode = 200;

            $user = App::make('currentUser');
            $userId = $user->bpp_user_id;
            $brandId = $user->base_merchant_id;

            $productName = OrbitInput::post('product_name');
            $productDescription = OrbitInput::post('product_description');
            $tnc = OrbitInput::post('tnc');
            $status = OrbitInput::post('status', 'inactive');
            $maxReservationTime = OrbitInput::post('max_reservation_time', 48);
            $youtubeIds = OrbitInput::post('youtube_ids');
            $youtubeIds = (array) $youtubeIds;
            $categoryId = OrbitInput::post('category_id');
            $variants = OrbitInput::post('variants');
            $brandProductVariants = OrbitInput::post('brand_product_variants');
            $brandProductMainPhoto = Request::file('brand_product_main_photo');

            // Begin database transaction
            $this->beginTransaction();

            $validator = Validator::make(
                array(
                    'product_name'        => $productName,
                    'status'              => $status,
                    'variants'            => $variants,
                    'brand_product_variants' => $brandProductVariants,
                    'brand_product_main_photo' => $brandProductMainPhoto,
                ),
                array(
                    'product_name'        => 'required',
                    'status'              => 'in:active,inactive',
                    'variants'            => 'required',
                    'brand_product_variants' => 'required',
                    'brand_product_main_photo' => 'required|image|max:1024',
                ),
                array(
                    'product_name.required' => 'Product Name field is required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $newBrandProduct = new BrandProduct();
            $newBrandProduct->brand_id = $brandId;
            $newBrandProduct->product_name = $productName;
            $newBrandProduct->product_description = $productDescription;
            $newBrandProduct->tnc = $tnc;
            $newBrandProduct->status = $status;
            $newBrandProduct->max_reservation_time = $maxReservationTime;
            $newBrandProduct->created_by = $userId;
            $newBrandProduct->save();

            // save brand_product_categories
            $newBrandProductCategories = new BrandProductCategory();
            $newBrandProductCategories->brand_product_id
                = $newBrandProduct->brand_product_id;
            $newBrandProductCategories->category_id = $categoryId;
            $newBrandProductCategories->save();

            // save brand_product_videos
            $brandProductVideos = array();
            foreach ($youtubeIds as $youtube_id) {
                $newBrandProductVideo = new BrandProductVideo();
                $newBrandProductVideo->brand_product_id
                    = $newBrandProduct->brand_product_id;
                $newBrandProductVideo->youtube_id = $youtube_id;
                $newBrandProductVideo->save();
                $brandProductVideos[] = $newBrandProductVideo;
            }
            $newBrandProduct->brand_product_video = $brandProductVideos;

            // Save variants?
            $this->saveVariants(
                $newBrandProduct,
                $variants,
                $brandProductVariants
            );

            Event::fire(
                'orbit.brandproduct.postnewbrandproduct.after.save',
                [$newBrandProduct]
            );

            // Commit the changes
            $this->commit();

            Event::fire(
                'orbit.brandproduct.after.commit',
                [$newBrandProduct->brand_product_id]
            );

            // Update brand list suggestion
            // $brandList = BrandProduct::select('brand_id')->groupBy('brand_id')
            //     ->lists('brand_id');
            // if (! empty($brandList)) {
            //     Config::put('brand_list_suggestion', serialize($brandList), 60);
            // }

            $this->response->data = $newBrandProduct;

        } catch (Exception $e) {
            return $this->handleException($e);
        }

        return $this->render();
    }

    private function saveVariants(
        $newBrandProduct,
        $variants,
        $brandProductVariants
    ) {

        $variants = @json_decode($variants, true);
        $variants = $variants ?: [];
        $variantList = [];
        $variantOptionIds = [];

        $index = 0; // just to make sure the index starts at 0
        foreach($variants as $variant) {
            $variantOptionIds[$index] = [];
            $newVariant = Variant::where('variant_name', $variant['name'])
                ->first();

            if (empty($newVariant)) {
                $newVariant = Variant::create([
                    'variant_name' => $variant['name'],
                ]);

                // Save variant options?
                foreach($variant['options'] as $option) {
                    $newVariantOption = VariantOption::where(
                            'variant_id', $newVariant->variant_id
                        )->where('value', $option)->first();

                    if (empty($newVariantOption)) {
                        $newVariantOption = VariantOption::create([
                            'variant_id' => $newVariant->variant_id,
                            'value' => $option,
                        ]);
                    }

                    $variantOptionIds[$index][$newVariantOption->value]
                        = $newVariantOption->variant_option_id;
                }
            }

            // Load variant options...
            $newVariant->load('options');
            $variantList[] = $newVariant;
            $index++;
        }

        $newBrandProduct->variants = $variantList;

        $this->saveBrandProductVariants(
            $newBrandProduct,
            $variantOptionIds,
            $brandProductVariants
        );
    }

    private function saveBrandProductVariants(
        $newBrandProduct,
        $variantOptionIds,
        $brandProductVariants
    ) {
        $brandProductVariants = @json_decode($brandProductVariants, true);
        $brandProductVariants = $brandProductVariants ?: [];
        $brandProductVariantList = [];
        $user = App::make('currentUser');

        foreach($brandProductVariants as $bpVariant) {
            // Check for duplicate sku?
            $skuExists = BrandProductVariant::select(
                    'brand_product_variant_id'
                )
                ->join(
                    'brand_products',
                    'brand_product_variants.brand_product_id',
                    '=',
                    'brand_products.brand_product_id'
                )
                ->where('brand_products.brand_id', $newBrandProduct->brand_id)
                ->where('sku', $bpVariant['sku'])
                ->first();

            if (! empty($skuExists)) {
                OrbitShopAPI::throwInvalidArgument(
                    "SKU: {$bpVariant['sku']} already used."
                );
            }

            // Save main BrandProductVariant record.
            $newBrandProductVariant = BrandProductVariant::create([
                'brand_product_id' => $newBrandProduct->brand_product_id,
                'sku' => $bpVariant['sku'],
                'product_code' => $bpVariant['product_code'],
                'original_price' => $bpVariant['original_price'],
                'selling_price' => $bpVariant['selling_price'],
                'quantity' => $bpVariant['quantity'],
                'created_by' => $user->bpp_user_id,
            ]);

            if (isset($bpVariant['variant_options'])
                && empty($bpVariant['variant_options'])) {
                continue;
            }

            $bpVariantOptionList = [];
            foreach($bpVariant['variant_options'] as $variantOption) {
                $optionType = $variantOption['option_type'];
                $optionValue = $variantOption['value'];

                // $variantIndex should reflect the order of $variants
                $variantIndex = $variantOption['variant_index'];
                $variantOptionId = null;

                // Check the existance of variant_option_id inside
                // $variantOptionIds list. If exists, then use that id
                // as option_id/link.
                if ($optionType === 'merchant') {
                    $variantOptionId = $optionValue;
                }
                else if (isset($variantOptionIds[$variantIndex])) {
                    $selectedVariant = $variantOptionIds[$variantIndex];
                    $variantOptionId = $selectedVariant[$optionValue];
                }

                $newBPVariantOption = BrandProductVariantOption::create([
                    'brand_product_variant_id' => $newBrandProductVariant
                        ->brand_product_variant_id,
                    'option_type' => $optionType,
                    'option_id' => $variantOptionId,
                ]);

                $bpVariantOptionList[] = $newBPVariantOption;
            }

            $brandProductVariantList[] = $bpVariantOptionList;
        }

        $newBrandProduct->brand_product_variants = $brandProductVariantList;
    }
}
