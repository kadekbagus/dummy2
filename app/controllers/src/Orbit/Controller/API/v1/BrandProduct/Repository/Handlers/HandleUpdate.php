<?php

namespace Orbit\Controller\API\v1\BrandProduct\Repository\Handlers;

use DB;
use App;
use Event;
use Media;
use Product;
use Request;
use Variant;
use BaseStore;
use Exception;
use BaseMerchant;
use BrandProduct;
use ProductVideo;
use VariantOption;
use BrandProductVideo;
use MediaAPIController;
use BrandProductVariant;
use ProductLinkToObject;
use BrandProductLinkToObject;
use BrandProductVariantOption;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Orbit\Controller\API\v1\BrandProduct\Product\DataBuilder\UpdateBrandProductBuilder;

/**
 * A helper that provide Brand Product update routines.
 *
 * @author Budi <budi@gotomalls.com>
 */
trait HandleUpdate
{
    /**
     * Update brand product.
     *
     * @param  ValidateRequest $request the request
     * @return Illuminate\Database\Eloquent\Model $brandProduct brand product
     */
    public function update($request)
    {
        $user = App::make('currentUser');
        $brandId = $user->base_merchant_id;
        $disableOnlineProduct = FALSE;

        $brandProductSingle = BrandProduct::where('brand_product_id', '=', $request->brand_product_id)->first();
        $onlineProduct = Product::where('brand_product_id', '=', $request->brand_product_id)->first();

        if (empty($brandProductSingle)) {
            OrbitShopAPI::throwInvalidArgument('Product not found');
        }

        // Build update data.
        $updateData = (new UpdateBrandProductBuilder($request))->build();

        // Update main data if needed.
        // Get the Brand Product detail.
        $brandProduct = $this->get($request->brand_product_id);

        if (count($updateData['main']) > 0) {
            foreach($updateData['main'] as $key => $data) {
                $brandProductSingle->{$key} = $data;
            }
            $brandProductSingle->save();
        }

        // Update categories
        if (! empty($updateData['categories'])) {
            $brandProduct->categories()->sync($updateData['categories']);

            // This is weird.
            $brandProduct->load([
                'categories' => function($query) {
                    $query->select('categories.category_id', 'category_name');
                }
            ]);
        }

        // Update youtube links if needed.
        $this->updateVideos($brandProduct, $updateData['videos']);

        // Update variants...
        if (count($updateData['variants']) > 0
            && count($updateData['brand_product_variants'])
        ) {
            $this->updateVariants(
                $brandProduct,
                $updateData['variants'],
                $updateData['brand_product_variants']
            );
        }

        // Update online product
        if ($onlineProduct) {

            OrbitInput::post('product_name', function($name) use ($onlineProduct) {
                $onlineProduct->name = $name;
            });

            OrbitInput::post('product_description', function($short_description) use ($onlineProduct) {
                $onlineProduct->short_description = $short_description;
            });

            OrbitInput::post('online_product_status', function($status) use ($onlineProduct) {
                $onlineProduct->status = $status;
            });

            // disable online product if marketplace deleted
            $disableOnlineProduct = $this->checkMarketplaceDeletion($request);

            if ($disableOnlineProduct) {
                $onlineProduct->status = 'inactive';
            }

            $onlineProduct->save();

            // update category
            OrbitInput::post('category_id', function($category_id) use ($onlineProduct) {
                $updateOnlineProductCategory = ProductLinkToObject::where('product_id', '=', $onlineProduct->product_id)
                                                                ->where('object_type', '=', 'category')
                                                                ->first();

                $updateOnlineProductCategory->object_id = $category_id;
                $updateOnlineProductCategory->save();
                $onlineProduct->category = $updateOnlineProductCategory;
            });

            // update youtube links
            OrbitInput::post('youtube_ids', function($youtubeIds) use ($onlineProduct) {
                $deletedOldData = ProductVideo::where('product_id', '=', $onlineProduct->product_id)->delete();

                $videos = array();
                foreach ($youtubeIds as $youtubeId) {
                    $productVideos = new ProductVideo();
                    $productVideos->product_id = $onlineProduct->product_id;
                    $productVideos->youtube_id = $youtubeId;
                    $productVideos->save();
                    $videos[] = $productVideos;
                }
                $onlineProduct->product_videos = $videos;
            });
        }

        // save marketplaces
        OrbitInput::post('marketplaces', function($marketplace_json_string) use ($brandProduct, $onlineProduct, $request, $brandId) {
            if (!$onlineProduct) {
                // create online product
                $onlineProduct = new Product;
                $onlineProduct->name = $request->product_name;
                $onlineProduct->short_description = $request->product_description;
                $onlineProduct->status = $request->status;
                $onlineProduct->country_id = $this->getCountryId($brandId);
                $onlineProduct->brand_product_id = $brandProduct->brand_product_id;
                $onlineProduct->save();

                // create category
                $newCategory = new ProductLinkToObject();
                $newCategory->product_id = $onlineProduct->product_id;
                $newCategory->object_id = $request->category_id;
                $newCategory->object_type = 'category';
                $newCategory->save();

                // create link to brand
                $newLinkToBrand = new ProductLinkToObject();
                $newLinkToBrand->product_id = $onlineProduct->product_id;
                $newLinkToBrand->object_id = $brandId;
                $newLinkToBrand->object_type = 'brand';
                $newLinkToBrand->save();

                // create product video
                $videos = array();
                foreach ($request->youtube_ids as $youtubeId) {
                    $productVideos = new ProductVideo();
                    $productVideos->product_id = $onlineProduct->product_id;
                    $productVideos->youtube_id = $youtubeId;
                    $productVideos->save();
                    $videos[] = $productVideos;
                }

                $onlineProduct->category = $newCategory;
                $onlineProduct->link_to_brand = $newLinkToBrand;
                $onlineProduct->product_videos = $videos;
            }
            $this->validateAndSaveMarketplaces($brandProduct, $onlineProduct, $marketplace_json_string, $scenario = 'create');
        });

        // Reload relationship.
        $brandProduct->load(['brand_product_variants.variant_options']);

        // online product
        $brandProduct->online_product = $onlineProduct;

        // Update images if changed.
        $this->updateImages($brandProduct, $updateData);

        return $brandProduct;
    }

    /**
     * Update brand product videos if needed.
     *
     * @param  [type] $brandProduct [description]
     * @param  [type] $videos       [description]
     * @return [type]               [description]
     */
    private function updateVideos($brandProduct, $videos)
    {
        if (! empty($videos)) {
            $brandProduct->videos()->delete();
            foreach($videos as $videoId) {
                $brandProductVideo = new BrandProductVideo();
                $brandProductVideo->brand_product_id =
                    $brandProduct->brand_product_id;
                $brandProductVideo->youtube_id = $videoId;
                $brandProductVideo->save();
            }
        }
    }

    /**
     * Update brand product variants if needed.
     *
     * @param  [type] $brandProduct [description]
     * @param  [type] $updateData   [description]
     * @param  [type] $request      [description]
     * @return [type]               [description]
     */
    private function updateVariants(
        $brandProduct,
        $variants,
        $brandProductVariants
    ) {
        $variants = $variants ?: [];
        $variantOptionIds = [];

        $index = 0;
        foreach($variants as $variant) {
            $variantOptionIds[$index] = [];
            $variantName = strtolower($variant->name);
            $newVariant = Variant::where('variant_name', $variantName)
                ->first();

            if (empty($newVariant)) {
                $newVariant = Variant::create([
                    'variant_name' => $variantName,
                ]);
            }

            // Save variant options?
            foreach($variant->options as $option) {
                $option = strtolower($option);
                $newVariantOption = VariantOption::where(
                        'variant_id', $newVariant->variant_id
                    )->where('value', $option)->first();

                if (empty($newVariantOption)) {
                    $newVariantOption = VariantOption::create([
                        'variant_id' => $newVariant->variant_id,
                        'value' => $option,
                    ]);
                }
            }

            $newVariant->load('options');

            // Repopulate all options (old and new).
            foreach($newVariant->options as $option) {
                $optionValue = strtolower($option->value);
                $variantOptionIds[$index][$optionValue] =
                    $option->variant_option_id;
            }

            $index++;
        }

        $this->updateBrandProductVariants(
            $brandProduct,
            $variantOptionIds,
            $brandProductVariants
        );
    }

    /**
     * Update brand product variants.
     *
     * @param  [type] $brandProduct         [description]
     * @param  [type] $variantOptionIds     [description]
     * @param  [type] $brandProductVariants [description]
     * @return [type]                       [description]
     */
    private function updateBrandProductVariants(
        $brandProduct,
        $variantOptionIds,
        $brandProductVariants
    ) {
        // Delete diff
        $this->deleteDiffVariants($brandProduct, $brandProductVariants);

        // Insert or update new ones
        $this->createOrUpdateBrandProductVariants(
            $brandProduct,
            $variantOptionIds,
            $brandProductVariants
        );
    }

    /**
     * Delete diff variants.
     *
     * @param  [type] $brandProduct         [description]
     * @param  [type] $brandProductVariants [description]
     * @return [type]                       [description]
     */
    private function deleteDiffVariants($brandProduct, $brandProductVariants)
    {
        // Old brandProduct variants
        $updatedBrandProductVariantIds = [];
        $deletedBrandProductVariantIds = [];

        foreach($brandProductVariants as $bpVariant) {
            if (isset($bpVariant->id) && ! empty($bpVariant->id)) {
                $updatedBrandProductVariantIds[] = $bpVariant->id;
            }
        }

        foreach($brandProduct->brand_product_variants as $bpVariant) {
            $bpVariantId = $bpVariant->brand_product_variant_id;

            if (! in_array($bpVariantId, $updatedBrandProductVariantIds)) {
                $deletedBrandProductVariantIds[] = $bpVariantId;
            }
        }

        // Delete unneded bp variant
        if (! empty($deletedBrandProductVariantIds)) {
            BrandProductVariant::whereIn(
                    'brand_product_variant_id', $deletedBrandProductVariantIds
                )->delete();
        }
    }

    /**
     * Save new or updated brand product variant.
     *
     * @param  [type] $brandProduct         [description]
     * @param  [type] $variantOptionIds     [description]
     * @param  [type] $brandProductVariants [description]
     * @return [type]                       [description]
     */
    private function createOrUpdateBrandProductVariants(
        $brandProduct,
        $variantOptionIds,
        $brandProductVariants
    )
    {
        $user = App::make('currentUser');
        $brandProductId = $brandProduct->brand_product_id;

        foreach($brandProductVariants as $bpVariant) {
            // If bp variant id exists, then update
            $newBpVariant = null;
            if (isset($bpVariant->id) && ! empty($bpVariant->id)) {
                $newBpVariant = BrandProductVariant::with(['variant_options'])
                    ->where('brand_product_variant_id', $bpVariant->id)
                    ->first();
            }

            if (empty($newBpVariant)) {
                $newBpVariant = new BrandProductVariant;
                $newBpVariant->created_by = $user->bpp_user_id;
                $newBpVariant->brand_product_id = $brandProductId;
            }

            $newBpVariant->sku = isset($bpVariant->sku)
                ? $bpVariant->sku : null;
            $newBpVariant->product_code = isset($bpVariant->product_code)
                ? $bpVariant->product_code : null;
            $newBpVariant->original_price = isset($bpVariant->original_price)
                ? $bpVariant->original_price : null;
            $newBpVariant->selling_price = $bpVariant->selling_price;
            $newBpVariant->quantity = $bpVariant->quantity;

            $newBpVariant->save();

            // Delete all variant options...
            $newBpVariant->variant_options()->delete();

            // Save new bp variant options
            $brandProductVariantId = $newBpVariant->brand_product_variant_id;
            foreach($bpVariant->variant_options as $variantOption) {
                $optionType = $variantOption->option_type;
                $optionValue = $variantOption->value;

                // $variantIndex should reflect the order of $variants
                $variantIndex = $variantOption->variant_index;
                $variantOptionId = null;

                // Check the existance of variant_option_id inside
                // $variantOptionIds list. If exists, then use that id
                // as option_id/link.
                if ($optionType === 'merchant') {
                    $variantOptionId = $optionValue;
                }
                else if (isset($variantOptionIds[$variantIndex])) {
                    $optionValue = strtolower($optionValue);
                    $selectedVariant = $variantOptionIds[$variantIndex];
                    $variantOptionId = $selectedVariant[$optionValue];
                }

                BrandProductVariantOption::create([
                    'brand_product_variant_id' => $brandProductVariantId,
                    'option_type' => $optionType,
                    'option_id' => $variantOptionId,
                ]);
            }
        }
    }

    /**
     * Update images if needed.
     *
     * @param  [type] $brandProduct [description]
     * @param  [type] $updateData   [description]
     * @return [type]               [description]
     */
    private function updateImages($brandProduct, $updateData)
    {
        // If client update main photo, then remove the old ones.
        // (only if media_id doesn't included in deleted_images).
        if (Request::hasFile('brand_product_main_photo')) {
            $mainPhotos = Media::select('media_id')
                ->where('object_id', $brandProduct->brand_product_id)
                ->where('media_name_id', 'brand_product_main_photo')
                ->get();

            foreach($mainPhotos as $mainPhoto) {
                $mediaId = $mainPhoto->media_id;
                if (! in_array($mediaId, $updateData['deleted_images'])) {
                    $updateData['deleted_images'][] = $mediaId;
                }
            }

            // online product
            if (isset($brandProduct->online_product->product_id)) {
                $mainPhotosOnlineProduct = Media::select('media_id')
                                    ->where('object_id', $brandProduct->online_product->product_id)
                                    ->where('media_name_id', 'product_image')
                                    ->get();

                foreach($mainPhotosOnlineProduct as $mainPhotoOnlineProduct) {
                    $mediaId = $mainPhotoOnlineProduct->media_id;
                    if (! in_array($mediaId, $updateData['deleted_images'])) {
                        $updateData['deleted_images'][] = $mediaId;
                    }
                }
            }
        }

        // Delete old media if needed.
        $_POST['media_id'] = '';
        $user = App::make('currentUser');
        App::instance('orbit.upload.user', $user);

        foreach($updateData['deleted_images'] as $mediaId) {

            $_POST['media_id'] = $mediaId;

            $response = MediaAPIController::create('raw')
                                        ->setEnableTransaction(false)
                                        ->setSkipRoleChecking()
                                        ->delete();

            if ($response->code !== 0)
            {
                throw new Exception($response->message, $response->code);
            }
        }

        unset($_POST['media_id']);

        // Process new images
        $images = Event::fire(
            'orbit.brandproduct.postnewbrandproduct.after.save',
            [$brandProduct, $brandProduct->online_product]
        );
    }

    private function validateAndSaveMarketplaces($brandProduct, $onlineProduct, $marketplace_json_string, $scenario = 'create')
    {
        $data = @json_decode($marketplace_json_string, true);
        $data = $data ?: [];
        $marketplaceData = [];

        // delete existing links
        $deletedLinks = BrandProductLinkToObject::where('brand_product_id', '=', $brandProduct->brand_product_id)
                                                ->where('object_type', '=', 'marketplace')
                                                ->get();

        foreach ($deletedLinks as $deletedLink) {
            $deletedLink->delete(true);
        }

        if (isset($onlineProduct->product_id)) {
            $deletedProductLinks = ProductLinkToObject::where('product_id', '=', $onlineProduct->product_id)
                                                    ->where('object_type', '=', 'marketplace')
                                                    ->get();

            foreach ($deletedProductLinks as $deletedProductLink) {
                $deletedProductLink->delete(true);
            }
        }

        if (! empty($data) && $data[0] !== '') {
            foreach ($data as $item) {

                if (!isset($item['id']) || $item['id'] == "" || $item['id'] == null) {
                    OrbitShopAPI::throwInvalidArgument('Marketplace id cannot empty');
                }

                if (!isset($item['website_url']) || $item['website_url'] == "" || $item['website_url'] == null) {
                    OrbitShopAPI::throwInvalidArgument('Product URL is required');
                }

                if (!isset($item['selling_price']) || $item['selling_price'] == "" || $item['selling_price'] == null) {
                    OrbitShopAPI::throwInvalidArgument('Selling price cannot empty');
                }

                if (!isset($item['original_price']) || $item['original_price'] == "" || $item['original_price'] == null) {
                    $item['original_price'] = 0;
                }

                if ($item['selling_price'] == "" || $item['selling_price'] == null) {
                    OrbitShopAPI::throwInvalidArgument('Selling price cannot empty');
                }

                if ($item['original_price'] == "" || $item['original_price'] == "0" || $item['original_price'] == null) {

                } else {
                    if ($item['selling_price'] > $item['original_price']) {
                        OrbitShopAPI::throwInvalidArgument('Link to marketplace selling price cannot higher than original price');
                    }
                }

                $saveObjectMarketPlaces = new BrandProductLinkToObject();
                $saveObjectMarketPlaces->brand_product_id = $brandProduct->brand_product_id;
                $saveObjectMarketPlaces->object_id = $item['id'];
                $saveObjectMarketPlaces->object_type = 'marketplace';
                $saveObjectMarketPlaces->product_url = $item['website_url'];
                $saveObjectMarketPlaces->original_price = $item['original_price'];
                $saveObjectMarketPlaces->selling_price = $item['selling_price'];
                $saveObjectMarketPlaces->sku = isset($item['sku']) ? $item['sku'] : null;
                $saveObjectMarketPlaces->save();
                $marketplaceData[] = $saveObjectMarketPlaces;

                if (isset($onlineProduct->product_id)) {
                    $saveObjectMarketPlacesOnlineProduct = new ProductLinkToObject();
                    $saveObjectMarketPlacesOnlineProduct->product_id = $onlineProduct->product_id;
                    $saveObjectMarketPlacesOnlineProduct->object_id = $item['id'];
                    $saveObjectMarketPlacesOnlineProduct->object_type = 'marketplace';
                    $saveObjectMarketPlacesOnlineProduct->product_url = $item['website_url'];
                    $saveObjectMarketPlacesOnlineProduct->original_price = $item['original_price'];
                    $saveObjectMarketPlacesOnlineProduct->selling_price = $item['selling_price'];
                    $saveObjectMarketPlacesOnlineProduct->sku = isset($item['sku']) ? $item['sku'] : null;
                    $saveObjectMarketPlacesOnlineProduct->save();
                    $marketplaceDataOnlineProduct[] = $saveObjectMarketPlacesOnlineProduct;
                }
            }
            $brandProduct->marketplaces = $marketplaceData;

            if (isset($onlineProduct->product_id)) {
                $onlineProduct->marketplaces = $marketplaceDataOnlineProduct;
            }
        }
    }

    private function checkMarketplaceDeletion($request)
    {
        $marketplaceRequest = @json_decode($request->marketplaces, true);
        $marketplaceRequest = $marketplaceRequest ?: [];

        if (count($marketplaceRequest) === 0) {
            return TRUE;
        }

        return FALSE;
    }

    private function getCountryId($base_merchant_id)
    {
        // get country id from base merchant
        $country = BaseMerchant::select('country_id')->where('base_merchant_id', $base_merchant_id)->first();
        if (!$country) {
            OrbitShopAPI::throwInvalidArgument('Country Id not found');
        }
        return $country->country_id;
    }
}
