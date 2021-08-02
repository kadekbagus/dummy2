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

class OrderUpdateStatusAPIController extends ControllerAPI
{

    /**
     * Decline or accept brand product order
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
                            Order::STATUS_CANCELLED,
                        ]),
                ),
                array(
                    'orbit.order.exists' => 'Order not found',
                    'orbit.order.status' => 'Cannot update this order',
                    'order_id.required'  => 'Order ID is required',
                    'status.in' => 'available status are: '.Order::STATUS_READY_FOR_PICKUP.','.Order::STATUS_CANCELLED
                )
            );

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $order = App::make('orbit.order.exists');

            $order->status = $status;

            $order->save();

            // Commit the changes
            $this->commit();

            if ($status === Order::STATUS_CANCELLED) {
                Event::fire('orbit.order.cancelled', [$order]);
            }

            if ($status === Order::STATUS_READY_FOR_PICKUP) {
                Event::fire('orbit.order.ready_for_pickup', [$order]);
            }

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

            $order = App::make('orbit.order.exists');

            if ($order->status !== Order::STATUS_PENDING) {
                return FALSE;
            }

            return TRUE;
        });
    }
}

