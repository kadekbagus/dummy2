<?php
/**
 * An API controller for managing POS Quick Product.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use Helper\EloquentRecordCounter as RecordCounter;

class PosQuickProductAPIController extends ControllerAPI
{
    /**
     * POST - Create pos quick product
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param integer   `product_id`    (required) - ID of the product
     * @param integer   `merchant_id`   (required) - ID of the merchant
     * @param integer   `retailer_id`   (required) - ID of the retailer
     * @param integer   `product_order` (required) - Order of the Pos Quick Product Order
     * @return Illuminate\Support\Facades\Response
     */
    public function postNewPosQuickProduct()
    {
        $activity = Activity::portal()
                            ->setActivityType('create');

        $user = NULL;
        $posQuickProduct = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.product.postnewposquickproduct.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.product.postnewposquickproduct.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.product.postnewposquickproduct.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('create_pos_quick_product')) {
                Event::fire('orbit.product.postnewposquickproduct.authz.notallowed', array($this, $user));

                $lang = Lang::get('validation.orbit.actionlist.new_pos_quick_product');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $lang));

                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.product.postnewposquickproduct.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $productId = OrbitInput::post('product_id');
            $merchantId = OrbitInput::post('merchant_id');
            $order = OrbitInput::post('product_order');

            $retailerId = OrbitInput::post('retailer_id');
            // @TODO should not be here for next version.
            if (empty($retailerId)) {
                $retailerId = Setting::where('setting_name', 'current_retailer')->first()->setting_value;
            }

            $validator = Validator::make(
                array(
                    'product_id'        => $productId,
                    'merchant_id'       => $merchantId,
                    'retailer_id'       => $retailerId,
                    'product_order'     => $order,
                ),
                array(
                    'product_id'        => 'required|numeric|orbit.empty.product',
                    'merchant_id'       => 'required|numeric|orbit.empty.merchant',
                    'retailer_id'       => 'required|numeric|orbit.empty.retailer',
                    'product_order'     => 'required|numeric|min:0'
                )
            );

            Event::fire('orbit.product.postnewposquickproduct.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.product.postnewposquickproduct.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            $posQuickProduct = PosQuickProduct::excludeDeleted()
                                              ->where('product_id', $productId)
                                              ->where('merchant_id', $merchantId)
                                              ->where('retailer_id', $retailerId)
                                              ->first();
            if (empty($posQuickProduct)) {
                $posQuickProduct = new PosQuickProduct();
            }
            $posQuickProduct->product_id = $productId;
            $posQuickProduct->merchant_id = $merchantId;
            $posQuickProduct->retailer_id = $retailerId;
            $posQuickProduct->product_order = $order;

            Event::fire('orbit.product.postnewposquickproduct.before.save', array($this, $posQuickProduct));

            $posQuickProduct->save();

            Event::fire('orbit.product.postnewposquickproduct.after.save', array($this, $posQuickProduct));
            $this->response->data = $posQuickProduct;

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activityNotes = sprintf('Pos Quick Product Created: %s', $posQuickProduct->product->product_name);
            $activity->setUser($user)
                    ->setActivityName('create_pos_quick_product')
                    ->setActivityNameLong('Create Pos Quick Product OK')
                    ->setObject($posQuickProduct)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.product.postnewposquickproduct.after.commit', array($this, $posQuickProduct));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.product.postnewposquickproduct.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_pos_quick_product')
                    ->setActivityNameLong('Create Pos Quick Product Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.product.postnewposquickproduct.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 400;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_pos_quick_product')
                    ->setActivityNameLong('Create Pos Quick Product Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.product.postnewposquickproduct.query.error', array($this, $e));

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

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_pos_quick_product')
                    ->setActivityNameLong('Create Pos Quick Product Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.product.postnewposquickproduct.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();

            if (Config::get('app.debug')) {
                $this->response->data = $e->__toString();
            } else {
                $this->response->data = null;
            }

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_pos_quick_product')
                    ->setActivityNameLong('Create Pos Quick Product Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save the activity
        $activity->save();

        return $this->render($httpCode);
    }

    /**
     * POST - Update pos quick product
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param integer   `product_id`    (required) - ID of the product
     * @param integer   `merchant_id`   (required) - ID of the merchant
     * @param integer   `retailer_id`   (required) - ID of the retailer
     * @param integer   `product_order` (required) - Order of the Pos Quick Product Order
     * @return Illuminate\Support\Facades\Response
     */
    public function postUpdatePosQuickProduct()
    {
        $activity = Activity::portal()
                           ->setActivityType('update');

        $user = NULL;
        $posQuickProduct = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.product.postupdateposquickproduct.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.product.postupdateposquickproduct.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.product.postupdateposquickproduct.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('update_pos_quick_product')) {
                Event::fire('orbit.product.postupdateposquickproduct.authz.notallowed', array($this, $user));

                $lang = Lang::get('validation.orbit.actionlist.update_pos_quick_product');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $lang));

                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.product.postupdateposquickproduct.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $productId = OrbitInput::post('product_id');
            $merchantId = OrbitInput::post('merchant_id');
            $order = OrbitInput::post('product_order');

            $retailerId = OrbitInput::post('retailer_id');
            // @TODO should not be here for next version.
            if (empty($retailerId)) {
                $retailerId = Setting::where('setting_name', 'current_retailer')->first()->setting_value;
            }

            $validator = Validator::make(
                array(
                    'product_id'        => $productId,
                    'merchant_id'       => $merchantId,
                    'retailer_id'       => $retailerId,
                    'product_order'     => $order,
                ),
                array(
                    'product_id'        => 'required|numeric|orbit.empty.product',
                    'merchant_id'       => 'required|numeric|orbit.empty.merchant',
                    'retailer_id'       => 'required|numeric|orbit.empty.retailer',
                    'product_order'     => 'required|numeric|min:0'
                )
            );

            Event::fire('orbit.product.postupdateposquickproduct.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.product.postupdateposquickproduct.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            $posQuickProduct = PosQuickProduct::excludeDeleted()
                                              ->where('product_id', $productId)
                                              ->where('merchant_id', $merchantId)
                                              ->where('retailer_id', $retailerId)
                                              ->first();
            if (empty($posQuickProduct)) {
                $posQuickProduct = new PosQuickProduct();
            }
            $posQuickProduct->product_id = $productId;
            $posQuickProduct->merchant_id = $merchantId;
            $posQuickProduct->retailer_id = $retailerId;
            $posQuickProduct->product_order = $order;

            Event::fire('orbit.product.postupdateposquickproduct.before.save', array($this, $posQuickProduct));

            $posQuickProduct->save();

            Event::fire('orbit.product.postupdateposquickproduct.after.save', array($this, $posQuickProduct));
            $this->response->data = $posQuickProduct;

            // Commit the changes
            $this->commit();

            // Successfull Update
            $activityNotes = sprintf('Pos Quick Product updated: %s', $posQuickProduct->product->product_name);
            $activity->setUser($user)
                    ->setActivityName('update_pos_quick_product')
                    ->setActivityNameLong('Update Pos Quick Product OK')
                    ->setObject($posQuickProduct)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.product.postupdateposquickproduct.after.commit', array($this, $posQuickProduct));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.product.postupdateposquickproduct.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_pos_quick_product')
                    ->setActivityNameLong('Update Pos Quick Product Failed')
                    ->setObject($posQuickProduct)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.product.postupdateposquickproduct.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 400;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_pos_quick_product')
                    ->setActivityNameLong('Update Pos Quick Product Failed')
                    ->setObject($posQuickProduct)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.product.postupdateposquickproduct.query.error', array($this, $e));

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

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_pos_quick_product')
                    ->setActivityNameLong('Update Pos Quick Product Failed')
                    ->setObject($posQuickProduct)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.product.postupdateposquickproduct.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();

            if (Config::get('app.debug')) {
                $this->response->data = $e->__toString();
            } else {
                $this->response->data = null;
            }

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_pos_quick_product')
                    ->setActivityNameLong('Update Pos Quick Product Failed')
                    ->setObject($posQuickProduct)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save activity
        $activity->save();

        return $this->render($httpCode);
    }

    /**
     * POST - Delete pos quick product
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param integer   `product_id`    (required) - ID of the product
     * @param integer   `merchant_id`   (required) - ID of the merchant
     * @param integer   `retailer_id`   (required) - ID of the retailer
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeletePosQuickProduct()
    {
        $activity = Activity::portal()
                          ->setActivityType('delete');

        $user = NULL;
        $posQuickProduct = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.product.postdeleteposquickproduct.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.product.postdeleteposquickproduct.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.product.postdeleteposquickproduct.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('delete_pos_quick_product')) {
                Event::fire('orbit.product.postdeleteposquickproduct.authz.notallowed', array($this, $user));

                $lang = Lang::get('validation.orbit.actionlist.delete_pos_quick_product');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $lang));

                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.product.postdeleteposquickproduct.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $productId = OrbitInput::post('product_id');
            $merchantId = OrbitInput::post('merchant_id');

            $retailerId = OrbitInput::post('retailer_id');
            // @TODO should not be here for next version.
            if (empty($retailerId)) {
                $retailerId = Setting::where('setting_name', 'current_retailer')->first()->setting_value;
            }

            $validator = Validator::make(
                array(
                    'product_id'        => $productId,
                    'merchant_id'       => $merchantId,
                    'retailer_id'       => $retailerId,
                ),
                array(
                    'product_id'        => 'required|numeric|orbit.empty.product',
                    'merchant_id'       => 'required|numeric|orbit.empty.merchant',
                    'retailer_id'       => 'required|numeric|orbit.empty.retailer',
                )
            );

            Event::fire('orbit.product.postdeleteposquickproduct.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.product.postdeleteposquickproduct.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            $posQuickProduct = PosQuickProduct::excludeDeleted()
                                              ->where('product_id', $productId)
                                              ->where('merchant_id', $merchantId)
                                              ->where('retailer_id', $retailerId)
                                              ->first();
            if (empty($posQuickProduct)) {
                $errorMessage = Lang::get('validation.orbit.empty.posquickproduct');
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            Event::fire('orbit.product.postdeleteposquickproduct.before.save', array($this, $posQuickProduct));

            $posQuickProduct->delete();

            Event::fire('orbit.product.postdeleteposquickproduct.after.save', array($this, $posQuickProduct));
            $this->response->data = $posQuickProduct;

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activityNotes = sprintf('Pos Quick Product Deleted: %s', $posQuickProduct->product->product_name);
            $activity->setUser($user)
                    ->setActivityName('delete_pos_quick_product')
                    ->setActivityNameLong('Delete Pos Quick Product OK')
                    ->setObject($posQuickProduct)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.product.postdeleteposquickproduct.after.commit', array($this, $posQuickProduct));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.product.postdeleteposquickproduct.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_pos_quick_product')
                    ->setActivityNameLong('Delete Pos Quick Product Failed')
                    ->setObject($posQuickProduct)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.product.postdeleteposquickproduct.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 400;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_pos_quick_product')
                    ->setActivityNameLong('Delete Pos Quick Product Failed')
                    ->setObject($posQuickProduct)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.product.postdeleteposquickproduct.query.error', array($this, $e));

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

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_pos_quick_product')
                    ->setActivityNameLong('Delete Pos Quick Product Failed')
                    ->setObject($posQuickProduct)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.product.postdeleteposquickproduct.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();

            if (Config::get('app.debug')) {
                $this->response->data = $e->__toString();
            } else {
                $this->response->data = null;
            }

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_pos_quick_product')
                    ->setActivityNameLong('Delete Pos Quick Product Failed')
                    ->setObject($posQuickProduct)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save the activity
        $activity->save();

        return $this->render($httpCode);
    }

    /**
     * GET - List of POS Quick Product
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param array         `product_ids`              (optional) - IDs of the product
     * @param array         `merchant_ids`             (optional) - IDs of the merchant
     * @param array         `retailer_ids`             (optional) - IDs of the retailer
     * @param string        `sortby`                   (optional) - column order by. Valid value: id, price, name, product_order.
     * @param string        `sortmode`                 (optional) - asc or desc
     * @param integer       `take`                     (optional) - limit
     * @param integer       `skip`                     (optional) - limit offset
     * @param string        `is_current_retailer_only` (optional) - To show current retailer product only. Valid value: Y
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchPosQuickProduct()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.product.getposquickproduct.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.product.getposquickproduct.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.product.getposquickproduct.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('view_pos_quick_product')) {
                Event::fire('orbit.product.getposquickproduct.authz.notallowed', array($this, $user));
                $errorMessage = Lang::get('validation.orbit.actionlist.view_pos_quick_product');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $errorMessage));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.product.getposquickproduct.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sortby');
            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                ),
                array(
                    'sort_by' => 'in:id,price,name,product_order',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.posquickproduct_sortby'),
                )
            );

            Event::fire('orbit.product.getposquickproduct.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.product.getposquickproduct.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.pos_quick_product.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.pos_quick_product.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            // Available merchant to query
            $listOfMerchantIds = [];

            // Available retailer to query
            $listOfRetailerIds = [];

            // Builder object
            $posQuickProducts = PosQuickProduct::joinRetailer()
                                               ->excludeDeleted('pos_quick_products')
                                               ->with('product');

            // Filter by ids
            OrbitInput::get('id', function($posQuickIds) use ($posQuickProducts) {
                $posQuickProducts->whereIn('pos_quick_products.pos_quick_product_id', $posQuickIds);
            });

            // Filter by status
            OrbitInput::get('status', function($status) use ($posQuickProducts) {
                $posQuickProducts->whereIn('pos_quick_products.status', $status);
            });

            // Filter by merchant ids
            OrbitInput::get('merchant_ids', function($merchantIds) use ($posQuickProducts) {
                // $posQuickProducts->whereIn('pos_quick_products.merchant_id', $merchantIds);
                $listOfMerchantIds = (array)$merchantIds;
            });

            // Filter by retailer ids
            OrbitInput::get('retailer_ids', function($retailerIds) use ($posQuickProducts) {
                $posQuickProducts->whereIn('pos_quick_products.retailer_id', $retailerIds);
            });

            // Filter by current retailer
            OrbitInput::get('is_current_retailer_only', function ($is_current_retailer_only) use ($posQuickProducts) {
                if ($is_current_retailer_only === 'Y') {
                    $retailer_id = Setting::where('setting_name', 'current_retailer')->first();
                    if (! empty($retailer_id)) {
                        $posQuickProducts->where('pos_quick_products.retailer_id', $retailer_id->setting_value);
                    }
                }
            });

            // @Todo:
            // 1. Replace this stupid hacks
            // 2. Look also for retailer ids
            if (! $user->isSuperAdmin()) {
                $listOfMerchantIds = $user->getMyMerchantIds();

                if (empty($listOfMerchantIds)) {
                    $listOfMerchantIds = [-1];
                }
                $posQuickProducts->whereIn('pos_quick_products.merchant_id', $listOfMerchantIds);
            } else {
                if (! empty($listOfMerchantIds)) {
                    $posQuickProducts->whereIn('pos_quick_products.merchant_id', $listOfMerchantIds);
                }
            }

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_posQuickProducts = clone $posQuickProducts;

            // Get the take args
            $take = $perPage;
            OrbitInput::get('take', function ($_take) use (&$take, $maxRecord) {
                if ($_take > $maxRecord) {
                    $_take = $maxRecord;
                }
                $take = $_take;

                if ((int)$take <= 0) {
                    $take = $maxRecord;
                }
            });
            $posQuickProducts->take($take);

            $skip = 0;
            OrbitInput::get('skip', function ($_skip) use (&$skip, $posQuickProducts) {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $posQuickProducts->skip($skip);

            // Default sort by
            $sortBy = 'pos_quick_products.product_order';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function ($_sortBy) use (&$sortBy) {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'id'            => 'pos_quick_products.pos_quick_product_id',
                    'name'          => 'products.product_name',
                    'product_order' => 'pos_quick_products.product_order',
                    'price'         => 'products.price',
                    'created'       => 'pos_quick_products.created_at',
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function ($_sortMode) use (&$sortMode) {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });
            $posQuickProducts->orderBy($sortBy, $sortMode);

            $totalPosQuickProducts = RecordCounter::create($_posQuickProducts)->count();
            $listOfPosQuickProducts = $posQuickProducts->get();

            $data = new stdclass();
            $data->total_records = $totalPosQuickProducts;
            $data->returned_records = count($listOfPosQuickProducts);
            $data->records = $listOfPosQuickProducts;

            if ($listOfPosQuickProducts === 0) {
                $data->records = null;
                $this->response->message = Lang::get('statuses.orbit.nodata.pos_quick_product');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.product.getposquickproduct.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.product.getposquickproduct.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.product.getposquickproduct.query.error', array($this, $e));

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
        } catch (Exception $e) {
            Event::fire('orbit.product.getposquickproduct.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            if (Config::get('app.debug')) {
                $this->response->data = $e->__toString();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.product.getposquickproduct.before.render', array($this, &$output));

        return $output;
    }

    protected function registerCustomValidation()
    {
        $user = $this->api->user;
        Validator::extend('orbit.empty.merchant', function ($attribute, $merchantId, $parameters) use ($user) {
            $merchant = Merchant::allowedForUser($user)
                                ->excludeDeleted()
                                ->where('merchant_id', $merchantId)
                                ->first();

            if (empty($merchant)) {
                return FALSE;
            }

            App::instance('orbit.empty.merchant', $merchant);

            return TRUE;
        });

        // Check the existance of product id
        Validator::extend('orbit.empty.product', function ($attribute, $value, $parameters) use ($user) {
            $product = Product::excludeDeleted()
                                ->allowedForUser($user)
                                ->where('product_id', $value)
                                ->first();

            if (empty($product)) {
                return FALSE;
            }

            App::instance('orbit.empty.product', $product);

            return TRUE;
        });

        // Check the existance of retailer id
        Validator::extend('orbit.empty.retailer', function ($attribute, $value, $parameters) {
            $retailer = Retailer::excludeDeleted()
                        ->where('merchant_id', $value)
                        ->first();

            if (empty($retailer)) {
                return FALSE;
            }

            App::instance('orbit.empty.retailer', $retailer);

            return TRUE;
        });
    }
}
