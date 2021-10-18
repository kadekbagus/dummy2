<?php

namespace Orbit\Controller\API\v1\BrandProduct\Order;

use DB;
use App;
use Lang;
use Config;
use stdclass;
use Exception;
use Validator;
use Carbon\Carbon;
use DominoPOS\OrbitACL\ACL;

use Order;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\ControllerAPI;
use Illuminate\Support\Facades\Event;
use Illuminate\Database\QueryException;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Orbit\Database\ObjectID;
use Orbit\Helper\Midtrans\API\Refund;

class OrderUpdateStatusAPIController extends ControllerAPI
{

    /**
     * Update status of order 
     *
     * @author Kadek <kadek@dominopos.com>
     */
    public function postUpdate()
    {
        try {
            $httpCode = 200;

            $user = App::make('currentUser');
            $userId = $user->bpp_user_id;
            $userType = $user->user_type;
            $brandId = $user->base_merchant_id;
            $merchantId = $user->merchant_id;
            $orderId = OrbitInput::post('order_id');
            $status = OrbitInput::post('status');
            $cancelReason = OrbitInput::post('cancel_reason', 'Out of Stock');

            $this->registerCustomValidation();

            $validator = Validator::make(
                array(
                    'order_id'      => $orderId,
                    'status'        => $status,
                ),
                array(
                    'order_id'      => 'required|orbit.order.exists:'.$brandId.'|orbit.order.status',
                    'status'        => 'required|in:'. join(',', [
                                                                    Order::STATUS_READY_FOR_PICKUP,
                                                                    Order::STATUS_DECLINED,
                                                                    Order::STATUS_CANCELLED,
                                                                    Order::STATUS_DONE,
                                                                    Order::STATUS_NOT_DONE,
                                        ]),
                ),
                array(
                    'orbit.order.exists' => 'Order not found',
                    'orbit.order.status' => 'Cannot update this order',
                    'order_id.required'  => 'Order ID is required',
                    'status.in' => 'available status are: '.Order::STATUS_READY_FOR_PICKUP.','
                                                           .Order::STATUS_DECLINED.','
                                                           .Order::STATUS_CANCELLED.','
                                                           .Order::STATUS_DONE.','
                                                           .Order::STATUS_NOT_DONE,
                )
            );

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // ready for pickup order
            if ($status === Order::STATUS_READY_FOR_PICKUP) {
                Order::readyForPickup($orderId, $userId);
            }

            // declined order
            if ($status === Order::STATUS_DECLINED) {
                Order::declined($orderId, $cancelReason, $userId);
            }

            // confirm cancel order
            if ($status === Order::STATUS_CANCELLED) {
                Order::cancelled($orderId);
            }

            // done/confirm order
            if ($status === Order::STATUS_DONE) {
                Order::done($orderId, $userId);
            }

            if ($status === Order::STATUS_NOT_DONE) {
                Order::markAsNotDone($orderId);
            }

            // Commit the changes
            $this->commit();

            $order = Order::with(['order_details.brand_product_variant'])
                            ->where('order_id', $orderId)
                            ->where('brand_id', '=', $brandId)
                            ->first();

            $this->response->data = $order;
        } catch (ACLForbiddenException $e) {
            // Rollback the changes
            $this->rollBack();
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            // Rollback the changes
            $this->rollBack();
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (QueryException $e) {
            // Rollback the changes
            $this->rollBack();
            $this->response->code = $e->getCode();
            $this->response->status = 'error';

            // Only shows full query error when we are in debug mode
            if (Config::get('app.debug')) {
                $this->response->message = $e->getMessage();
            } else {
                $this->response->message = Lang::get('validation.orbit.queryerror');
            }
            $this->response->data = null;
            $httpCode = 500;
        } catch (\Exception $e) {
            // Rollback the changes
            $this->rollBack();
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        return $this->render($httpCode);
    }

    protected function registerCustomValidation()
    {
        // Check the existance of order
        Validator::extend('orbit.order.exists', function ($attribute, $value, $parameters) {
            $brandId = $parameters[0];
            $prefix = DB::getTablePrefix();

            $order = Order::where('order_id', $value)->where('brand_id', '=', $brandId)->first();
            
            if (empty($order)) {
                return FALSE;
            }

            App::instance('orbit.order.exists', $order);

            return TRUE;
        });


        // Check the order status
        Validator::extend('orbit.order.status', function ($attribute, $value, $parameters) {
            $prefix = DB::getTablePrefix();
            $status = OrbitInput::post('status');
            $order = App::make('orbit.order.exists');

            // if ($status === Order::STATUS_READY_FOR_PICKUP || $status === Order::STATUS_DECLINED) {
            //     if ($order->status !== Order::STATUS_PAID) {
            //         return FALSE;
            //     }
            // }

            if ($status === Order::STATUS_DONE) {
                if ($order->status !== Order::STATUS_READY_FOR_PICKUP) {
                    return FALSE;
                }
            }

            return TRUE;
        });
    }
}

