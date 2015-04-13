<?php
/**
 * An API controller for managing widget.
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

class LuckyDrawCSAPIController extends ControllerAPI
{
    /**
     * POST - Create new widget
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @return Illuminate\Support\Facades\Response
     */
    public function postIssueLuckyDrawNumber()
    {
        $activity = Activity::portal()
                            ->setActivityType('create');

        $user = NULL;
        $widget = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.luckydrawnumber.postnewluckydrawnumber.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.luckydrawnumber.postnewluckydrawnumber.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.luckydrawnumber.postnewluckydrawnumber.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('create_lucky_draw')) {
                Event::fire('orbit.luckydrawnumber.postnewluckydrawnumber.authz.notallowed', array($this, $user));

                $errorMessage = Lang::get('validation.orbit.actionlist.a');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $errorMessage));

                ACL::throwAccessForbidden($message);
            }
*/
            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = ['super admin', 'mall admin', 'mall owner', 'mall customer service'];
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to login.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.luckydrawnumber.postnewluckydrawnumber.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $tenants = OrbitInput::post('tenants');
            $amounts = OrbitInput::post('amounts');
            $receipts = OrbitInput::post('receipts');
            $receiptDates = OrbitInput::post('receipt_dates');
            $paymentTypes = OrbitInput::post('payment_types');
            $luckyDrawId = OrbitInput::post('lucky_draw_id');
            $userId = OrbitInput::post('user_id');
            $mode = OrbitInput::post('mode');
            $userLuckyNumber = OrbitInput::post('lucky_number', NULL);

            $validator = Validator::make(
                array(
                    'tenants'       => $tenants,
                    'amounts'       => $amounts,
                    'receipts'      => $receipts,
                    'receipt_dates' => $receiptDates,
                    'payment_types' => $paymentTypes,
                    'user_id'       => $userId,
                    'lucky_draw_id' => $luckyDrawId,
                    'mode'          => $mode,
                    'lucky_number'  => $userLuckyNumber
                ),
                array(
                    'tenants'       => 'array|required',
                    'amounts'       => 'array|required',
                    'receipts'      => 'array|required',
                    'receipt_dates' => 'array|required',
                    'payment_types' => 'array|required',
                    'user_id'       => 'required|numeric',
                    'lucky_draw_id' => 'required|numeric|orbit.empty.lucky_draw',
                    'mode'          => 'required|in:sequence,number_driven,random',
                    'lucky_number'  => 'numeric|min:0:max:9'
                )
            );

            Event::fire('orbit.luckydrawnumber.postnewluckydrawnumber.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.luckydrawnumber.postnewluckydrawnumber.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            $luckyDraw = App::make('orbit.empty.lucky_draw');

            // Minimum amount to get Lucky Draw
            $minimumAmount = (double)$luckyDraw->minimumAmount;

            // Loop through tenants to get the amounts
            $totalAmount = 0.0;
            foreach ($amounts as $amount) {
                if (! is_numeric($amount)) {
                    $errorMessage = 'Amount of spent must be numerical value only.';
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }

                if ($amount < 0) {
                    $errorMessage = 'Amount of spent must be greater than zero.';
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }

                $totalAmount += $amount;
            }

            if ((int)$totalAmount === 0) {
                if ($amount < 0) {
                    $errorMessage = 'Total amount of spent must be greater than zero.';
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }
            }
            $numberOfLuckyDraw = floor($totalAmount / $luckyDraw->minimum_amount);

            foreach ($receiptDates as $i=>$receiptDate) {
                $result = date_parse_from_format('Y-m-d', $receiptDate);

                if (! empty($result['warnings'])) {
                    $errorMessage = sprintf('Receipt date format is invalid on item number %s.', ++$i);
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }
            }

            /**
             *
             * -- @param 1 - Lucky draw number to issue
             * -- @param 2 - How to issue the number 'sequencial', 'number_driven', or 'random'
             * -- @param 3 - The lucky number - Only genered number which ends with that number
             * -- @param 4 - The customer user ID
             * -- @param 5 - The customer service user ID
             * -- @param 6 - Date issued
             * -- @param 7 - Status should be 'active'
             */
            $number = $numberOfLuckyDraw;
            $issueType = $mode;
            $luckyNumberDriven = is_null($userLuckyNumber) ? 1 : $userLuckyNumber;
            $employeeUserId = $user->user_id;
            $issueDate = date('Y-m-d H:i:s');
            $status = 'active';

            $storedProcArgs = [$number, $issueType, $luckyNumberDriven, $userId, $employeeUserId, $issueDate, $status];
            $luckyDrawnumbers = DB::select("call issue_lucky_draw_number(?, ?, ?, ?, ?, ?, ?)", $storedProcArgs);

            if (empty($result) || count($result) === 0) {
                $message = 'There is no lucky draw number left.';
                ACL::throwAccessForbidden($message);
            }

            $luckyDrawNumberIds = [];
            foreach ($luckyDrawnumbers as $row) {
                $luckyDrawNumberIds[] = $row->lucky_draw_number_id;
            }

            Event::fire('orbit.luckydrawnumber.postnewluckydrawnumber.before.save', array($this, $widget));

            // Save each receipt numbers
            // @Todo: remove query inside loop
            $mallId = Config::get('orbit.shop.id');
            foreach ($receipts as $i=>$receipt) {
                $luckyDrawReceipt = new LuckyDrawReceipt();
                $luckyDrawReceipt->mall_id = $mallId;
                $luckyDrawReceipt->user_id = $userId;

                if (! isset($tenants[$i])) {
                    $errorMessage = sprintf('Tenant for receipt line %s is empty.', $i);
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }
                $luckyDrawReceipt->receipt_retailer_id = $tenants[$i];
                $luckyDrawReceipt->receipt_number = $receipt;

                // Check if the receipt is not exists yet on this mall and particular tenants
                $prevLuckyDrawReceipt = LuckyDrawReceipt::active()
                                                        ->where('receipt_retailer_id', $tenants[$i])
                                                        ->where('mall_id', $mallId)
                                                        ->where('receipt_number', $receipt)
                                                        ->first();

                if (is_object($prevLuckyDrawReceipt)) {
                    // The customer wants to cheat us huh?
                    $receiptIssueDate = date('l m/d/Y', strtotime($prevLuckyDrawReceipt->created_at));
                    $message = sprintf('Receipt number %s was already used on %s.', $receipt, $receiptIssueDate);
                    ACL::throwAccessForbidden(htmlentities($message));
                }

                if (! isset($receiptDates[$i])) {
                    $errorMessage = sprintf('Receipt date for receipt line %s is empty.', $i);
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }
                $luckyDrawReceipt->receipt_date = $receiptDates[$i];

                if (! isset($amounts[$i])) {
                    $errorMessage = sprintf('Receipt date for receipt line %s is empty.', $i);
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }
                $luckyDrawReceipt->receipt_amount = $amounts[$i];
                $luckyDrawReceipt->status = 'active';
                $luckyDrawReceipt->created_by = $user->user_id;

                $luckyDrawReceipt->save();

                $luckyDrawReceipt->numbers()->sync($luckyDrawNumberIds);
            }

            Event::fire('orbit.luckydrawnumber.postnewluckydrawnumber.after.save', array($this, $widget));

            $luckyDraw->user_numbers = $luckyDrawnumbers;
            $this->response->data = $luckyDraw;

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activity->setUser($user)
                    ->setActivityName('issue_lucky_draw')
                    ->setActivityNameLong('Issue Lucky Draw')
                    ->setObject($luckyDraw)
                    ->responseOK();

            Event::fire('orbit.luckydrawnumber.postnewluckydrawnumber.after.commit', array($this, $widget));
        } catch (ACLForbiddenException $e) {
            // Rollback the changes
            $this->rollBack();

            Event::fire('orbit.luckydrawnumber.postnewluckydrawnumber.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('issue_lucky_draw')
                    ->setActivityNameLong('Issue Lucky Draw Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            // Rollback the changes
            $this->rollBack();

            Event::fire('orbit.luckydrawnumber.postnewluckydrawnumber.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 400;

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('issue_lucky_draw')
                    ->setActivityNameLong('Issue Lucky Draw Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            // Rollback the changes
            $this->rollBack();

            Event::fire('orbit.luckydrawnumber.postnewluckydrawnumber.query.error', array($this, $e));

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

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('issue_lucky_draw')
                    ->setActivityNameLong('Issue Lucky Draw Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            // Rollback the changes
            $this->rollBack();

            Event::fire('orbit.luckydrawnumber.postnewluckydrawnumber.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();

            if (Config::get('app.debug')) {
                $this->response->data = $e->__toString();
            } else {
                $this->response->data = null;
            }

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('issue_lucky_draw')
                    ->setActivityNameLong('Issue Lucky Draw Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save the activity
        $activity->save();

        return $this->render($httpCode);
    }

    /**
     * POST - Update widget
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param integer   `wiget_id`              (required) - The Widget ID
     * @param string    `type`                  (optional) - Widget type, 'catalogue', 'new_product', 'promotion', 'coupon'
     * @param integer   `object_id`             (optional) - The object ID
     * @param integer   `merchant_id`           (optional) - Merchant ID
     * @param integer   `retailer_ids`          (optional) - Retailer IDs
     * @param string    `animation`             (optional) - Animation type, 'none', 'horizontal', 'vertical'
     * @param string    `slogan`                (optional) - Widget slogan
     * @param integer   `widget_order`          (optional) - Order of the widget
     * @param array     `images`                (optional)
     * @return Illuminate\Support\Facades\Response
     */
    public function postUpdateWidget()
    {
        $activity = Activity::portal()
                           ->setActivityType('update');

        $user = NULL;
        $widget = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.widget.postupdatewidget.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.widget.postupdatewidget.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.widget.postupdatewidget.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('update_widget')) {
                Event::fire('orbit.widget.postupdatewidget.authz.notallowed', array($this, $user));

                $errorMessage = Lang::get('validation.orbit.actionlist.update_widget');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $errorMessage));

                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.widget.postupdatewidget.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $widgetId = OrbitInput::post('widget_id');
            $widgetType = OrbitInput::post('widget_type');
            $widgetObjectId = OrbitInput::post('object_id');
            $merchantId = OrbitInput::post('merchant_id');
            $retailerIds = OrbitInput::post('retailer_ids');
            $slogan = OrbitInput::post('slogan');
            $animation = OrbitInput::post('animation');
            $widgetOrder = OrbitInput::post('widget_order');
            $images = OrbitInput::files('images');

            $validator = Validator::make(
                array(
                    'widget_id'             => $widgetId,
                    'object_id'             => $widgetObjectId,
                    'merchant_id'           => $merchantId,
                    'widget_type'           => $widgetType,
                    'retailer_ids'          => $retailerIds,
                    'slogan'                => $slogan,
                    'animation'             => $animation,
                    'widget_order'          => $widgetOrder,
                    'images'                => $images
                ),
                array(
                    'widget_id'             => 'required|numeric|orbit.empty.widget',
                    'object_id'             => 'numeric',
                    'merchant_id'           => 'numeric|orbit.empty.merchant',
                    'widget_type'           => 'required|in:catalogue,new_product,promotion,coupon|orbit.exists.widget_type_but_me:' . $merchantId . ', ' . $widgetId,
                    'animation'             => 'in:none,horizontal,vertical',
                    'images'                => 'required_if:animation,none',
                    'widget_order'          => 'numeric',
                    'retailer_ids'          => 'array|orbit.empty.retailer',
                ),
                array(
                    'orbit.exists.widget_type_but_me' => Lang::get('validation.orbit.exists.widget_type'),
                )
            );

            Event::fire('orbit.widget.postupdatewidget.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.widget.postupdatewidget.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            $widget = App::make('orbit.empty.widget');

            OrbitInput::post('widget_type', function($type) use ($widget) {
                $widget->widget_type = $type;
            });

            OrbitInput::post('object_id', function($objectId) use ($widget) {
                $widget->widget_object_id = $objectId;
            });

            OrbitInput::post('merchant_id', function($merchantId) use ($widget) {
                $widget->merchant_id = $merchantId;
            });

            OrbitInput::post('slogan', function($slogan) use ($widget) {
                $widget->widget_slogan = $slogan;
            });

            OrbitInput::post('widget_order', function($order) use ($widget) {
                $widget->widget_order = $order;
            });

            OrbitInput::post('animation', function($animation) use ($widget) {
                $widget->animation = $animation;
            });

            Event::fire('orbit.widget.postupdatewidget.before.save', array($this, $widget));

            $widget->modified_by = $user->user_id;
            $widget->save();

            // Insert attribute values if specified by the caller
            OrbitInput::post('retailer_ids', function($retailerIds) use ($widget) {
                $widget->retailers()->sync($retailerIds);
            });

            // If widget is empty then it should be applied to all retailers
            if (empty(OrbitInput::post('retailer_ids', NULL))) {
                $merchant = App::make('orbit.empty.merchant');
                $listOfRetailerIds = $merchant->getMyRetailerIds();
                $widget->retailers()->sync($listOfRetailerIds);
            }

            Event::fire('orbit.widget.postupdatewidget.after.save', array($this, $widget));
            $this->response->data = $widget;

            // Commit the changes
            $this->commit();

            // Successfull Update
            $activityNotes = sprintf('Widget updated: %s', $widget->widget_slogan);
            $activity->setUser($user)
                    ->setActivityName('update_widget')
                    ->setActivityNameLong('Update Widget OK')
                    ->setObject($widget)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.widget.postupdatewidget.after.commit', array($this, $widget));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.widget.postupdatewidget.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_widget')
                    ->setActivityNameLong('Update Widget Failed')
                    ->setObject($widget)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.widget.postupdatewidget.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_widget')
                    ->setActivityNameLong('Update Widget Failed')
                    ->setObject($widget)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.widget.postupdatewidget.query.error', array($this, $e));

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
                    ->setActivityName('update_widget')
                    ->setActivityNameLong('Update Widget Failed')
                    ->setObject($widget)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.widget.postupdatewidget.general.exception', array($this, $e));

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
                    ->setActivityName('update_widget')
                    ->setActivityNameLong('Update Widget Failed')
                    ->setObject($widget)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save activity
        $activity->save();

        return $this->render($httpCode);
    }

    /**
     * POST - Delete widget
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param integer   `wiget_id`              (required) - The Widget ID
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeleteWidget()
    {
        $activity = Activity::portal()
                          ->setActivityType('delete');

        $user = NULL;
        $widget = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.widget.postdeletewiget.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.widget.postdeletewiget.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.widget.postdeletewiget.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('delete_widget')) {
                Event::fire('orbit.widget.postdeletewiget.authz.notallowed', array($this, $user));

                $errorMessage = Lang::get('validation.orbit.actionlist.delete_widget');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $errorMessage));

                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.widget.postdeletewiget.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $widgetId = OrbitInput::post('widget_id');
            $validator = Validator::make(
                array(
                    'widget_id'             => $widgetId,
                ),
                array(
                    'widget_id'             => 'required|numeric|orbit.empty.widget',
                )
            );

            Event::fire('orbit.widget.postdeletewiget.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.widget.postdeletewiget.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            $widget = App::make('orbit.empty.widget');
            $widget->status = 'deleted';
            $widget->modified_by = $user->user_id;
            $widget->save();

            Event::fire('orbit.widget.postdeletewiget.after.save', array($this, $widget));
            $this->response->data = $widget;

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activityNotes = sprintf('Widget Deleted: %s', $widget->widget_slogan);
            $activity->setUser($user)
                    ->setActivityName('delete_widget')
                    ->setActivityNameLong('Delete Widget OK')
                    ->setObject($widget)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.widget.postdeletewiget.after.commit', array($this, $widget));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.widget.postdeletewiget.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_widget')
                    ->setActivityNameLong('Delete Widget Failed')
                    ->setObject($widget)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.widget.postdeletewiget.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_widget')
                    ->setActivityNameLong('Delete Widget Failed')
                    ->setObject($widget)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.widget.postdeletewiget.query.error', array($this, $e));

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
                    ->setActivityName('delete_widget')
                    ->setActivityNameLong('Delete Widget Failed')
                    ->setObject($widget)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.widget.postdeletewiget.general.exception', array($this, $e));

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
                    ->setActivityName('delete_widget')
                    ->setActivityNameLong('Delete Widget Failed')
                    ->setObject($widget)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save the activity
        $activity->save();

        return $this->render($httpCode);
    }

    /**
     * GET - List of Widgets.
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param array         `widget_ids`            (optional) - List of widget IDs
     * @param array         `widget_type`           (optional) - Type of the widget, e.g: 'catalogue', 'new_product', 'promotion', 'coupon'
     * @param array         `merchant_ids`          (optional) - List of Merchant IDs
     * @param array         `retailer_ids`          (optional) - List of Retailer IDs
     * @param array         `animations`            (optional) - Filter by animation
     * @param array         `types`                 (optional) - Filter by widget types
     * @param array         `with`                  (optional) - relationship included
     * @param integer       `take`                  (optional) - limit
     * @param integer       `skip`                  (optional) - limit offset
     * @param string        `sort_by`               (optional) - column order by
     * @param string        `sort_mode`             (optional) - asc or desc
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchWidget()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.widget.getwidget.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.widget.getwidget.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.widget.getwidget.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('view_widget')) {
                Event::fire('orbit.widget.getwidget.authz.notallowed', array($this, $user));

                $errorMessage = Lang::get('validation.orbit.actionlist.view_widget');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $errorMessage));

                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.widget.getwidget.after.authz', array($this, $user));

            $validator = Validator::make(
                array(
                    'widget_ids'    => OrbitInput::get('widget_ids'),
                    'merchant_ids'  => OrbitInput::get('merchant_ids'),
                    'retailer_ids'  => OrbitInput::get('retailer_ids'),
                    'animations'    => OrbitInput::get('animations'),
                    'types'         => OrbitInput::get('types')
                ),
                array(
                    'widget_ids'    => 'array|min:1',
                    'merchant_ids'  => 'array|min:1',
                    'retailer_ids'  => 'array|min:1',
                    'animations'    => 'array|min:1',
                    'types'         => 'array|min:1'
                )
            );

            Event::fire('orbit.widget.postdeletewiget.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.widget.postdeletewiget.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.widget.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.widget.per_page');
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
            $widgets = Widget::joinRetailer()
                            ->excludeDeleted('widgets');

            // Include other relationship
            OrbitInput::get('with', function($with) use ($widgets) {
                $widgets->with($with);
            });

            // Filter by ids
            OrbitInput::get('widget_ids', function($widgetIds) use ($widgets) {
                $widgets->whereIn('widgets.widget_id', $widgetIds);
            });

            // Filter by merchant ids
            OrbitInput::get('merchant_ids', function($merchantIds) use ($widgets) {
                $listOfMerchantIds = (array)$merchantIds;
            });

            // Filter by retailer ids
            OrbitInput::get('retailer_ids', function($retailerIds) use ($widgets) {
                $listOfRetailerIds = (array)$retailerIds;
            });

            // Filter by animation
            OrbitInput::get('animations', function($animation) use ($widgets) {
                $widgets->whereIn('widgets.animation', $animation);
            });

            // Filter by widget type
            OrbitInput::get('types', function($types) use ($widgets) {
                $widgets->whereIn('widgets.widget_type', $types);
            });

            // @To do: Replace this hacks
            if (! $user->isSuperAdmin()) {
                $listOfMerchantIds = $user->getMyMerchantIds();

                if (empty($listOfMerchantIds)) {
                    $listOfMerchantIds = [-1];
                }
                $widgets->whereIn('widgets.merchant_id', $listOfMerchantIds);
            } else {
                if (! empty($listOfMerchantIds)) {
                    $widgets->whereIn('widgets.merchant_id', $listOfMerchantIds);
                }
            }

            // @To do: Replace this hacks
            if (! $user->isSuperAdmin()) {
                $listOfRetailerIds = $user->getMyRetailerIds();

                if (empty($listOfRetailerIds)) {
                    $listOfRetailerIds = [-1];
                }
                $widgets->whereIn('widget_retailer.retailer_id', $listOfRetailerIds);
            } else {
                if (! empty($listOfRetailerIds)) {
                    $widgets->whereIn('widget_retailer.retailer_id', $listOfRetailerIds);
                }
            }

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_widgets = clone $widgets;

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
            $widgets->take($take);

            $skip = 0;
            OrbitInput::get('skip', function ($_skip) use (&$skip, $widgets) {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $widgets->skip($skip);

            // Default sort by
            $sortBy = 'widgets.widget_order';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function ($_sortBy) use (&$sortBy) {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'widget_order'  => 'widgets.widget_order',
                    'id'            => 'widgets.widget_id',
                    'created'       => 'widgets.created_at',
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function ($_sortMode) use (&$sortMode) {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });
            $widgets->orderBy($sortBy, $sortMode);

            $totalWidgets = RecordCounter::create($_widgets)->count();
            $listOfWidgets = $widgets->get();

            $data = new stdclass();
            $data->total_records = $totalWidgets;
            $data->returned_records = count($listOfWidgets);
            $data->records = $listOfWidgets;

            if ($totalWidgets === 0) {
                $data->records = null;
                $this->response->message = Lang::get('statuses.orbit.nodata.widget');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.widget.getwidget.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.widget.getwidget.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.widget.getwidget.query.error', array($this, $e));

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
            Event::fire('orbit.widget.getwidget.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();

            if (Config::get('app.debug')) {
                $this->response->data = $e->__toString();
            } else {
                $this->response->data = null;
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.widget.getwidget.before.render', array($this, &$output));

        return $output;
    }

    protected function registerCustomValidation()
    {
        // Check the existance of widget id
        Validator::extend('orbit.empty.lucky_draw', function ($attribute, $value, $parameters) {
            $luckyDraw = LuckyDraw::active()->where('lucky_draw_id', $value)->first();

            if (empty($luckyDraw)) {
                $errorMessage = sprintf('Lucky draw ID %s is not found.', $value);
                OrbitShopAPI::throwInvalidArgument(htmlentities($errorMessage));
            }

            App::instance('orbit.empty.lucky_draw', $luckyDraw);

            return TRUE;
        });
    }
}
