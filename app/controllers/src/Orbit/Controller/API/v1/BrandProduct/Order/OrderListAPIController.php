<?php

namespace Orbit\Controller\API\v1\BrandProduct\Order;

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
use Order;

class OrderListAPIController extends ControllerAPI
{

    /**
     * Order list on brand product portal.
     *
     * @author kadek <kadek@dominopos.com>
     */
    public function getSearchOrder()
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
                    'sortBy'      => 'in:created_at,updated_at,payment_status,status',
                    'sortMode'    => 'in:asc,desc',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $prefix = DB::getTablePrefix();

            $orders = Order::select('orders.order_id',
                                    'orders.status',
                                    'orders.user_id',
                                    DB::raw("CONCAT({$prefix}users.user_firstname,' ',{$prefix}users.user_lastname) as username"),
                                    DB::raw("{$prefix}media.path as user_picture"),
                                    'payment_transactions.status as payment_status',
                                    'orders.created_at as order_date',
                                    'orders.total_amount')
                            ->join('payment_transaction_details', function ($q) {
                                    $q->on('payment_transaction_details.object_id','=','orders.order_id');
                                    $q->where('payment_transaction_details.object_type', '=', 'order');
                            })
                            ->join('payment_transactions', 'payment_transactions.payment_transaction_id','=','payment_transaction_details.payment_transaction_id')
                            ->leftjoin('users', 'users.user_id', '=', 'orders.user_id')
                            ->leftjoin('media', function ($q) {
                                    $q->on('media.object_id', '=', 'users.user_id');
                                    $q->where('media.object_name', '=', 'user');
                                    $q->where('media.media_name_id', '=', 'user_profile_picture');
                                    $q->where('media.media_name_long', '=', 'user_profile_picture_orig');
                            })
                            ->with(['order_details' => function($q) use ($prefix) {
                                        $q->addSelect('order_details.order_id',
                                                      'order_details.order_detail_id',
                                                      'order_details.brand_product_variant_id',
                                                      'order_details.original_price',
                                                      'order_details.selling_price',
                                                      'order_details.quantity', 
                                                      DB::raw("{$prefix}order_details.selling_price*{$prefix}order_details.quantity as total_payment"));
                                        $q->with(['brand_product_variant' => function($q) use ($prefix) {
                                            $q->addSelect('brand_product_id','brand_product_variant_id');
                                            $q->with(['brand_product' => function($q) use ($prefix) {
                                                $q->addSelect('brand_product_id','product_name', DB::raw("{$prefix}media.path as product_picture"));
                                                $q->leftjoin('media', function ($q) {
                                                    $q->on('media.object_id', '=', 'brand_products.brand_product_id');
                                                    $q->where('media.object_name', '=', 'brand_product');
                                                    $q->where('media.media_name_id', '=', 'brand_product_main_photo');
                                                    $q->where('media.media_name_long', '=', 'brand_product_main_photo_orig');
                                                });
                                            }]);
                                        }, 'order_variant_details' => function($q){
                                            $q->addSelect('order_detail_id', 'variant_name', 'value');
                                        }]);
                                    }
                                ])
                            ->where('orders.brand_id', '=', $brandId);

            isset($merchantId) ? $orders->where('orders.merchant_id', '=', $merchantId) : null;

            OrbitInput::get('payment_status', function($status) use ($orders)
            {
                $status = (array) $status;
                $orders->whereIn('payment_transactions.status', $status);
            });

            OrbitInput::get('order_id', function($orderId) use ($orders)
            {
                $orders->where('orders.order_id', $orderId);
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_orders = clone $orders;

            // @todo: change the parseTakeFromGet to brand_products
            $take = PaginationNumber::parseTakeFromGet('merchant');
            $orders->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $orders->skip($skip);

            // Default sort by
            $sortBy = 'orders.updated_at';
            // Default sort mode
            $sortMode = 'desc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'status' => 'orders.status',
                    'updated_at' => 'orders.updated_at',
                    'created_at' => 'orders.created_at',
                    'payment_status' => 'payment_transactions.status',
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

            $orders->orderBy($sortBy, $sortMode);

            $totalItems = RecordCounter::create($_orders)->count();
            $listOfItems = $orders->get();

            foreach ($listOfItems as $key => $value) {
                foreach ($value->order_details as $key2 => $value2) {
                    $value->order_details[$key2]->product_name = $value2->brand_product_variant->brand_product->product_name;
                    $value->order_details[$key2]->product_image = $value2->brand_product_variant->brand_product->product_picture;
                    unset($value2->brand_product_variant);
                    $var = null;
                    foreach ($value->order_details[$key2]->order_variant_details as $key3 => $value3) {
                        $var[] = $value3->value;
                    }
                    $value->order_details[$key2]->variant = $var;
                    unset($value2->order_variant_details);
                }
            }

            $data = new stdclass();
            $data->total_records = $totalItems;
            $data->returned_records = count($listOfItems);
            $data->records = $listOfItems;

            if ($totalItems === 0) {
                $data->records = NULL;
                $this->response->message = "There is no orders that matched your search criteria";
            }

            $this->response->data = $data;

        } catch (Exception $e) {
            return $this->handleException($e);
        }

        return $this->render();
    }

}
