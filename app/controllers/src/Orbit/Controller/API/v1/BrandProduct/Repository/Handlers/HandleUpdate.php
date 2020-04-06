<?php

namespace Orbit\Controller\API\v1\BrandProduct\Repository\Handlers;

use App;
use BrandProduct;
use BrandProductVideo;
use BrandProductVariant;
use BrandProductVariantOption;
use DB;
use Event;
use Exception;
use MediaAPIController;
use Orbit\Controller\API\v1\BrandProduct\Product\DataBuilder\UpdateBrandProductBuilder;
use Variant;
use VariantOption;

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
        // Get the Brand Product detail.
        $brandProduct = $this->get($request->brand_product_id);

        // Build update data.
        $updateData = (new UpdateBrandProductBuilder($request))->build();

        // Update main data if needed.
        if (count($updateData['main']) > 0) {
            foreach($updateData['main'] as $key => $data) {
                $brandProduct->{$key} = $data;
            }
            $brandProduct->save();
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

        // Reload relationship.
        $brandProduct->load(['brand_product_variants.variant_options']);

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

            $newBpVariant->sku = $bpVariant->sku;
            $newBpVariant->product_code = $bpVariant->product_code;
            $newBpVariant->original_price = $bpVariant->original_price;
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

                $newBpVariantOption = BrandProductVariantOption::create([
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
            [$brandProduct]
        );
    }
}