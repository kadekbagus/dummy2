<?php

namespace Orbit\Controller\API\v1\BrandProduct\Product;

use App;
use BrandProduct;
use BrandProductCategory;
use BrandProductVariant;
use BrandProductVariantOption;
use BrandProductVideo;
use BrandProductLinkToObject;
use Config;
use DB;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Event;
use Exception;
use Illuminate\Database\QueryException;
use Lang;
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\OrbitShopAPI;
use Orbit\Controller\API\v1\BrandProduct\Product\Validator\BrandProductValidator;
use Request;
use Validator;
use Variant;
use VariantOption;
use Product;
use ProductLinkToObject;
use ProductVideo;
use BaseMerchant;

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
            $youtubeIds = OrbitInput::post('youtube_ids', []);
            $categoryId = OrbitInput::post('category_id');
            $variants = OrbitInput::post('variants');
            $brandProductVariants = OrbitInput::post('brand_product_variants');
            $brandProductMainPhoto = Request::file('brand_product_main_photo');
            $marketplaces = OrbitInput::post('marketplaces', []);
            $onlineProductStatus = OrbitInput::post('online_product_status', 'inactive');
            $newOnlineProduct = null;

            // Begin database transaction
            $this->beginTransaction();

            $this->registerCustomValidations();

            $validator = Validator::make(
                array(
                    'product_name'        => $productName,
                    'status'              => $status,
                    'category_id'         => $categoryId,
                    'variants'            => $variants,
                    'brand_product_variants' => $brandProductVariants,
                    'brand_product_main_photo' => $brandProductMainPhoto,
                ),
                array(
                    'product_name'        => 'required',
                    'status'              => 'in:active,inactive',
                    'category_id'         => 'required',
                    'variants'            => 'required|orbit.brand_product.variants',
                    'brand_product_variants' => join('|', [
                        'required',
                        'orbit.brand_product.product_variants',
                        'orbit.brand_product.selling_price_lt_original_price',
                        'orbit.brand_product.can_create',
                    ]),
                    'brand_product_main_photo' => 'required|image|max:1024',
                ),
                array(
                    'product_name.required' => 'Product Name is required.',
                    'category_id.required' => 'The Category is required.',
                    'orbit.brand_product.can_create' => 'You are not allowed to create the product.',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $newBrandProduct = new BrandProduct();
            $newBrandProduct->brand_id = $brandId;
            $newBrandProduct->product_name = strip_tags($productName);
            $newBrandProduct->product_description = strip_tags(
                $productDescription
            );
            $newBrandProduct->tnc = strip_tags($tnc);
            $newBrandProduct->status = $status;
            $newBrandProduct->max_reservation_time = $maxReservationTime * 60;
            $newBrandProduct->created_by = $userId;
            $newBrandProduct->save();

            // save brand_product_categories
            $newBrandProductCategories = new BrandProductCategory();
            $newBrandProductCategories->brand_product_id =
                $newBrandProduct->brand_product_id;
            $newBrandProductCategories->category_id = $categoryId;
            $newBrandProductCategories->save();

            // save brand_product_videos
            $brandProductVideos = array();
            foreach ($youtubeIds as $youtube_id) {
                $newBrandProductVideo = new BrandProductVideo();
                $newBrandProductVideo->brand_product_id =
                    $newBrandProduct->brand_product_id;
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

            // prepare data for online product
            $onlineProductData = array('name' => $productName,
                                       'shortDescription' => $productDescription,
                                       'status' => $status,
                                       'categoryId' => $categoryId,
                                       'youtubeIds' => $youtubeIds,
                                       'brandId' => $brandId,
                                       'onlineProductStatus' => $onlineProductStatus
                                    );

            // save marketplaces
            OrbitInput::post('marketplaces', function($marketplace_json_string) use ($newBrandProduct, &$newOnlineProduct, $onlineProductData) {
                // create online product
                $newOnlineProduct = $this->createOnlineProduct($onlineProductData, $newBrandProduct);
                $this->validateAndSaveMarketplaces($newBrandProduct, $newOnlineProduct, $marketplace_json_string, $scenario = 'create');
            });

            Event::fire(
                'orbit.brandproduct.postnewbrandproduct.after.save',
                [$newBrandProduct, $newOnlineProduct]
            );

            // Commit the changes
            $this->commit();

            Event::fire(
                'orbit.brandproduct.after.commit',
                [$newBrandProduct->brand_product_id]
            );

            if (isset($newOnlineProduct->product_id)) {
                Event::fire('orbit.newproduct.postnewproduct.after.commit', array($this, $newOnlineProduct));
            }

            $newBrandProduct->online_product = isset($newOnlineProduct->product_id) ? $newOnlineProduct : null;
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
            $variant['name'] = strtolower($variant['name']);
            $newVariant = Variant::where('variant_name', $variant['name'])
                ->first();

            if (empty($newVariant)) {
                $newVariant = Variant::create([
                    'variant_name' => $variant['name'],
                ]);
            }

            // Save variant options?
            foreach($variant['options'] as $option) {
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

            // Load variant options...
            $newVariant->load(['options']);

            foreach($newVariant->options as $option) {
                $optionValue = strtolower($option->value);
                $variantOptionIds[$index][$optionValue] =
                    $option->variant_option_id;
            }

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
            // Save main BrandProductVariant record.
            $newBrandProductVariant = BrandProductVariant::create([
                'brand_product_id' => $newBrandProduct->brand_product_id,
                'sku' => isset($bpVariant['sku']) ? $bpVariant['sku'] : null,
                'product_code' => isset($bpVariant['product_code'])
                    ? $bpVariant['product_code'] : null,
                'original_price' => isset($bpVariant['original_price'])
                    ? $bpVariant['original_price'] : null,
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
                    $optionValue = strtolower($optionValue);
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

    /**
     * Register custom validations.
     */
    private function registerCustomValidations()
    {
        Validator::extend(
            'orbit.brand_product.can_create',
            BrandProductValidator::class . '@canCreate'
        );

        Validator::extend(
            'orbit.brand_product.variants',
            BrandProductValidator::class . '@variants'
        );

        Validator::extend(
            'orbit.brand_product.product_variants',
            BrandProductValidator::class . '@productVariants'
        );

        Validator::extend(
            'orbit.brand_product.selling_price_lt_original_price',
            BrandProductValidator::class . '@sellingPriceLowerThanOriginalPrice'
        );

        Validator::extend(
            'unique_sku',
            BrandProductValidator::class . '@uniqueSKU'
        );
    }

    private function validateAndSaveMarketplaces($newBrandProduct, $newOnlineProduct, $marketplace_json_string, $scenario = 'create')
    {
        $data = @json_decode($marketplace_json_string, true);
        $data = $data ?: [];
        $marketplaceData = [];

        // delete existing links
        $deletedLinks = BrandProductLinkToObject::where('brand_product_id', '=', $newBrandProduct->brand_product_id)
                                                ->where('object_type', '=', 'marketplace')
                                                ->get();

        $deletedProductLinks = ProductLinkToObject::where('product_id', '=', $newOnlineProduct->product_id)
                                                ->where('object_type', '=', 'marketplace')
                                                ->get();

        foreach ($deletedLinks as $deletedLink) {
            $deletedLink->delete(true);
        }

        foreach ($deletedProductLinks as $deletedProductLink) {
            $deletedProductLink->delete(true);
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
                $saveObjectMarketPlaces->brand_product_id = $newBrandProduct->brand_product_id;
                $saveObjectMarketPlaces->object_id = $item['id'];
                $saveObjectMarketPlaces->object_type = 'marketplace';
                $saveObjectMarketPlaces->product_url = $item['website_url'];
                $saveObjectMarketPlaces->original_price = $item['original_price'];
                $saveObjectMarketPlaces->selling_price = $item['selling_price'];
                $saveObjectMarketPlaces->sku = isset($item['sku']) ? $item['sku'] : null;
                $saveObjectMarketPlaces->save();

                $saveObjectMarketPlacesOnlineProduct = new ProductLinkToObject();
                $saveObjectMarketPlacesOnlineProduct->product_id = $newOnlineProduct->product_id;
                $saveObjectMarketPlacesOnlineProduct->object_id = $item['id'];
                $saveObjectMarketPlacesOnlineProduct->object_type = 'marketplace';
                $saveObjectMarketPlacesOnlineProduct->product_url = $item['website_url'];
                $saveObjectMarketPlacesOnlineProduct->original_price = $item['original_price'];
                $saveObjectMarketPlacesOnlineProduct->selling_price = $item['selling_price'];
                $saveObjectMarketPlacesOnlineProduct->sku = isset($item['sku']) ? $item['sku'] : null;
                $saveObjectMarketPlacesOnlineProduct->save();

                $marketplaceData[] = $saveObjectMarketPlaces;
                $marketplaceDataOnlineProduct[] = $saveObjectMarketPlacesOnlineProduct;
            }
            $newBrandProduct->marketplaces = $marketplaceData;
            $newOnlineProduct->marketplaces = $marketplaceDataOnlineProduct;
        }
    }

    private function createOnlineProduct($data, $newBrandProduct)
    {
        // create product
        $newProduct = new Product;
        $newProduct->name = $data['name'];
        $newProduct->short_description = $data['shortDescription'];
        $newProduct->status = $data['onlineProductStatus'];
        $newProduct->country_id = $this->getCountryId($data['brandId']);
        $newProduct->brand_product_id = $newBrandProduct->brand_product_id;
        $newProduct->save();

        // create category
        $newCategory = new ProductLinkToObject();
        $newCategory->product_id = $newProduct->product_id;
        $newCategory->object_id = $data['categoryId'];
        $newCategory->object_type = 'category';
        $newCategory->save();

        // create link to brand
        $newLinkToBrand = new ProductLinkToObject();
        $newLinkToBrand->product_id = $newProduct->product_id;
        $newLinkToBrand->object_id = $data['brandId'];
        $newLinkToBrand->object_type = 'brand';
        $newLinkToBrand->save();

        // create product video
        $videos = array();
        foreach ($data['youtubeIds'] as $youtubeId) {
            $productVideos = new ProductVideo();
            $productVideos->product_id = $newProduct->product_id;
            $productVideos->youtube_id = $youtubeId;
            $productVideos->save();
            $videos[] = $productVideos;
        }

        $newProduct->category = $newCategory;
        $newProduct->link_to_brand = $newLinkToBrand;
        $newProduct->product_videos = $videos;

        return $newProduct;
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
