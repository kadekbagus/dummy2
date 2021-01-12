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
     * Product detail on brand product portal.
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

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $prefix = DB::getTablePrefix();

            $product = BrandProduct::select(DB::raw("
                    {$prefix}brand_products.brand_product_id,
                    {$prefix}brand_products.product_name,
                    {$prefix}brand_products.product_description,
                    {$prefix}brand_products.tnc,
                    {$prefix}brand_products.max_reservation_time,
                    {$prefix}brand_products.status
                "))
                ->with([
                    'brand_product_main_photo' => function($q) {
                        $q->select('media_id', 'object_id', 'path', 'cdn_url')
                            ->where('media_name_long', 'brand_product_main_photo_orig');
                    },
                    'brand_product_photos' => function($q) {
                        $q->select('media_id', 'object_id', 'path', 'cdn_url')
                            ->where('media_name_long', 'brand_product_photos_orig');
                    },
                    'videos' => function($q) {
                        $q->select('brand_product_video_id', 'brand_product_id', 'youtube_id');
                    },
                    'categories' => function($q) {
                        $q->select('categories.category_id', 'categories.category_name');
                    },
                    'brand_product_variants.variant_options' => function($q) use ($prefix) {
                        $q->with([
                            'option.variant',
                            'store' => function($q2) use ($prefix) {
                                $q2->select(
                                        'merchants.merchant_id',
                                        'merchants.name',
                                        DB::raw("malls.name as mall_name"),
                                        DB::raw("CONCAT({$prefix}merchants.floor, ' ', {$prefix}merchants.unit) as location")
                                    )
                                    ->leftJoin(
                                        DB::raw("{$prefix}merchants malls"),
                                        DB::raw("malls.merchant_id"),
                                        '=',
                                        'merchants.parent_id'
                                    );
                            }
                        ]);
                    },
                    'marketplaces' => function ($q) {
                        $q->addSelect('marketplaces.status')
                          ->where('marketplaces.status', '=', 'active');
                    }
                ])
                ->where('brand_products.brand_product_id', $productId)
                ->where('brand_products.brand_id', $brandId);

            if (! empty($merchantId)) {
                $product->leftJoin('brand_product_variants', 'brand_products.brand_product_id', '=', 'brand_product_variants.brand_product_id')
                    ->leftJoin('brand_product_variant_options', 'brand_product_variant_options.brand_product_variant_id', '=', 'brand_product_variants.brand_product_variant_id')
                    ->where('brand_product_variant_options.option_type', 'merchant')
                    ->where('brand_product_variant_options.option_id', $merchantId);
            }

            $product = $product->groupBy('brand_products.brand_product_id')
                ->firstOrFail();

            foreach ($product->brand_product_photos as $key => $value) {
                $img = new stdclass();
                $img->media_id = $value->media_id;
                $img->object_id = $value->object_id;
                $img->path = $value->path;
                $img->cdn_url = $value->cdn_url;
                $product->{"image".$key} = $img;
            }

            $variants = [];
            foreach ($product->brand_product_variants as $key => $bpv) {
                foreach ($bpv->variant_options as $key => $vo) {
                    if ($vo->option_type === 'variant_option') {
                        $variant = new stdclass();
                        $variant->variant_id = $vo->option->variant_id;
                        $variant->name = $vo->option->variant->variant_name;
                        $variant->options = [];

                        $variantExist = false;
                        foreach ($variants as $key => $variantItem) {
                            if ($variantItem->name === $variant->name) {
                                $variantExist = true;
                                break;
                            }
                        }

                        if (! $variantExist) {
                            $variants[] = $variant;
                        }
                    }
                }
            }

            foreach ($variants as &$variantItem) {
                foreach ($product->brand_product_variants as $key => $bpv) {
                    foreach ($bpv->variant_options as $key => $vo) {
                        if ($vo->option_type = 'variant_options' && isset($vo->option->variant)) {
                            if ($variantItem->name === $vo->option->variant->variant_name) {
                                $option = new stdclass();
                                $option->option_id = $vo->option->variant_option_id;
                                $option->option = $vo->option->value;

                                $optionExist = false;
                                foreach ($variantItem->options as $key => $optionItem) {
                                    if ($optionItem->option === $vo->option->value) {
                                        $optionExist = true;
                                        break;
                                    }
                                }
                                if (! $optionExist) {
                                    $variantItem->options[] = $option;
                                }
                            }
                        }
                    }
                }
            }

            $selectedStores = [];
            foreach ($product->brand_product_variants as $key => $bpv) {
                foreach ($bpv->variant_options as $key => $vo) {
                    if ($vo->option_type = 'merchant' && ! is_null($vo->store)) {
                        $store = new stdclass();
                        $store->id = $vo->store->merchant_id;
                        $store->name = $vo->store->name;
                        $store->mall_name = $vo->store->mall_name;
                        $store->location = $vo->store->location;

                        $storeExist = false;
                        foreach ($selectedStores as $key => $storeItem) {
                            if ($storeItem->id === $vo->store->merchant_id) {
                                $storeExist = true;
                                break;
                            }
                        }
                        if (! $storeExist) {
                            $selectedStores[] = $store;
                        }
                    }
                }
            }

            $product->variants = $variants;
            $product->selected_stores = $selectedStores;
            $this->response->data = $product;

        } catch (Exception $e) {
            return $this->handleException($e);
        }

        return $this->render();
    }

}
