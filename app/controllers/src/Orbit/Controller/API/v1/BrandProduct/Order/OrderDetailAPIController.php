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

class OrderDetailAPIController extends ControllerAPI
{

    /**
     * Order detail on brand product portal.
     *
     * @author kadek <kadek@dominopos.com>
     */
    public function getOrderDetail()
    {
        try {
            $httpCode = 200;

            $user = App::make('currentUser');
            $userId = $user->bpp_user_id;
            $brandId = $user->base_merchant_id;
            $merchantId = $user->merchant_id;
            $orderId = OrbitInput::get('order_id', null);

            $validator = Validator::make(
                array(
                    'order_id'    => $orderId,
                ),
                array(
                    'order_id'    => 'required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $prefix = DB::getTablePrefix();

            $order = Order::select('orders.order_id',
                                    'orders.status',
                                    'orders.total_amount as total_payment',
                                    'orders.user_id',
                                    'orders.merchant_id',
                                    'orders.created_at as order_date',
                                    'orders.pick_up_code',
                                    'orders.cancel_reason',
                                    DB::raw("CONCAT({$prefix}users.user_firstname,' ',{$prefix}users.user_lastname) as username"),
                                    DB::raw("{$prefix}media.path as user_picture"),
                                    'payment_transactions.status as payment_status')
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
                            ->with([
                                'store.mall',
                                'order_details' => function($q) use ($prefix) {
                                        $q->addSelect('order_detail_id','order_id','brand_product_variant_id','sku','product_code','quantity','selling_price');
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
                            ->where('orders.brand_id', '=', $brandId)
                            ->where('orders.order_id', '=', $orderId);

            isset($merchantId) ? $order->where('orders.merchant_id', '=', $merchantId) : null;
            $order = $order->first();

            if (! is_object($order)) {
                $errorMessage = 'Order that you specify is not found';
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $storeName = '';
            $mallName = '';
            if (is_object($order->store)) {
                if (! empty($order->store->name)) {
                    $storeName = $order->store->name;
                }
                if (is_object($order->store->mall)) {
                    if (! empty($order->store->mall->name)) {
                        $mallName = $order->store->mall->name;
                    }
                }
            }
            $order->pick_up_location = '';
            if (!empty($storeName) && !empty($mallName)) {
                $order->pick_up_location = $storeName . ' at ' . $mallName;
            }

            unset($order->store);

            foreach ($order->order_details as $key => $value) {
                $order->order_details[$key]->product_name = $value->brand_product_variant->brand_product->product_name;
                $order->order_details[$key]->product_image = $value->brand_product_variant->brand_product->product_picture;
                unset($value->brand_product_variant);
                $var = null;
                foreach ($order->order_details[$key]->order_variant_details as $key3 => $value3) {
                    $var[] = $value3->value;
                }
                $order->order_details[$key]->variant = $var;
                unset($value->order_variant_details);
            }

            $this->response->data = $order;

        } catch (Exception $e) {
            return $this->handleException($e);
        }

        return $this->render();
    }

}
