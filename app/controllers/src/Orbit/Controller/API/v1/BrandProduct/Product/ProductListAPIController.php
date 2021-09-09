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

class ProductListAPIController extends ControllerAPI
{

    /**
     * Product list on brand product portal.
     *
     * @author ahmad <ahmad@dominopos.com>
     */
    public function getSearchProduct()
    {
        try {
            $httpCode = 200;

            $user = App::make('currentUser');
            $userId = $user->bpp_user_id;
            $brandId = $user->base_merchant_id;
            $merchantId = $user->merchant_id;

            $status = OrbitInput::get('status', null);
            $sortBy = OrbitInput::get('sortby', null);
            $sortMode = OrbitInput::get('sortmode', null);

            $validator = Validator::make(
                array(
                    'status'      => $status,
                    'sortBy'      => $sortBy,
                    'sortMode'    => $sortMode,
                ),
                array(
                    'status'      => 'in:inactive,active',
                    'sortBy'      => 'in:product_name,min_price,total_quantity,status',
                    'sortMode'    => 'in:asc,desc',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $prefix = DB::getTablePrefix();

            $products = BrandProduct::select(DB::raw("
                    {$prefix}brand_products.brand_product_id,
                    {$prefix}brand_products.product_name,
                    min({$prefix}brand_product_variants.selling_price) as min_price,
                    max({$prefix}brand_product_variants.selling_price) as max_price,
                    sum({$prefix}brand_product_variants.quantity) as total_quantity,
                    {$prefix}brand_products.status
                "))
                ->with([
                    'reservation_details_count',
                    'order_details_count',
                    'brand_product_main_photo' => function($q2) {
                        $q2->select('media_id', 'object_id', 'path', 'cdn_url')
                            ->where('media_name_long', 'brand_product_main_photo_orig');
                    }
                ])
                ->leftJoin('brand_product_variants', 'brand_products.brand_product_id', '=', 'brand_product_variants.brand_product_id')
                ->where(DB::raw("{$prefix}brand_products.brand_id"), $brandId)
                ->where('brand_products.status', '<>', 'deleted')
                ->groupBy(DB::raw("{$prefix}brand_products.brand_product_id"));

            if (! empty($merchantId)) {
                $products->leftJoin('brand_product_variant_options', 'brand_product_variant_options.brand_product_variant_id', '=', 'brand_product_variants.brand_product_variant_id')
                    ->where('brand_product_variant_options.option_type', 'merchant')
                    ->where('brand_product_variant_options.option_id', $merchantId);
            }

            OrbitInput::get('product_name_like', function($keyword) use ($products)
            {
                $products->where('brand_products.product_name', 'like', "%$keyword%");
            });

            OrbitInput::get('category_id', function($categoryId) use ($products)
            {
                $products->leftJoin('brand_product_categories', 'brand_products.brand_product_id', '=', 'brand_product_categories.brand_product_id')
                    ->where('category_id', $categoryId);
            });

            OrbitInput::get('status', function($status) use ($products)
            {
                $products->where('status', $status);
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_products = clone $products;

            // @todo: change the parseTakeFromGet to brand_products
            $take = PaginationNumber::parseTakeFromGet('merchant');
            $products->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $products->skip($skip);

            // Default sort by
            $sortBy = 'brand_products.updated_at';
            // Default sort mode
            $sortMode = 'desc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'status' => 'brand_products.status',
                    'updated_at' => 'brand_products.updated_at',
                    'product_name' => 'brand_products.product_name',
                    'min_price' => 'min_price',
                    'total_quantity' => 'total_quantity',
                );

                if (array_key_exists($_sortBy, $sortByMapping)) {
                    $sortBy = $sortByMapping[$_sortBy];
                }
            });

            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) !== 'desc') {
                    $sortMode = 'asc';
                }
            });
            $products->orderBy($sortBy, $sortMode);

            $totalItems = RecordCounter::create($_products)->count();
            $listOfItems = $products->get();

            foreach ($listOfItems as $item) {
                $item->total_reserved = 0;
                if (!empty($item->reservation_details_count)) {
                    if (isset($item->reservation_details_count[0]) && is_object($item->reservation_details_count[0])) {
                        $item->total_reserved = $item->reservation_details_count[0]->total_reservation;
                    }
                }
                unset($item->reservation_details_count);
                $item->total_ordered = 0;
                if (!empty($item->order_details_count)) {
                    if (isset($item->order_details_count[0]) && is_object($item->order_details_count[0])) {
                        $item->total_ordered = $item->order_details_count[0]->total_order;
                    }
                }
                unset($item->order_details_count);
            }

            $data = new stdclass();
            $data->total_records = $totalItems;
            $data->returned_records = count($listOfItems);
            $data->records = $listOfItems;

            if ($totalItems === 0) {
                $data->records = NULL;
                $this->response->message = "There is no products that matched your search criteria";
            }

            $this->response->data = $data;

        } catch (Exception $e) {
            return $this->handleException($e);
        }

        return $this->render();
    }
}
