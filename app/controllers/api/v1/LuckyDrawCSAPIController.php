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
     * POST - Issue lucky draw number
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
                $message = 'Your role are not allowed to access this page.';
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
                    'user_id'       => 'required|numeric|orbit.empty.user',
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

            $customer = App::make('orbit.empty.user');
            $userId = $customer->user_id;

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

            // The total amount should be greater than the minimum amount of lucky draw
            if ((double)$totalAmount < (double)$luckyDraw->minimum_amount) {
                    $errorMessage = sprintf('The total spent is not enough to get Lucky Draw, minimum amount is %s.', number_format($luckyDraw->minimum_amount));
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
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

            if (empty($luckyDrawnumbers) || count($luckyDrawnumbers) === 0) {
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
                                                        ->where(function($query) {
                                                            $query->where('object_type', 'lucky_draw');
                                                            $query->orwhereNull('object_type');
                                                        })
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
                $luckyDrawReceipt->receipt_payment_type = $paymentTypes[$i];
                $luckyDrawReceipt->status = 'active';
                $luckyDrawReceipt->created_by = $user->user_id;
                $luckyDrawReceipt->object_type = 'lucky_draw';

                $luckyDrawReceipt->save();

                $luckyDrawReceipt->numbers()->sync($luckyDrawNumberIds);
            }

            Event::fire('orbit.luckydrawnumber.postnewluckydrawnumber.after.save', array($this, $widget));

            $luckyDraw->user_numbers = $luckyDrawnumbers;
            $this->response->data = $luckyDraw;

            // Insert to alert system
            $this->insertLuckyDrawNumberInbox($userId, $luckyDrawnumbers, $mallId);

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
     * POST - Issue Coupon Number Number
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @deprecated use postIssueCoupon() instead
     *
     * List of API Parameters
     * ----------------------
     * @return Illuminate\Support\Facades\Response
     */
    public function postIssueCouponNumber()
    {
        $activity = Activity::portal()
                            ->setActivityType('create');

        $customer = NULL;
        $widget = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.issuecoupon.postnewissuecoupon.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.issuecoupon.postnewissuecoupon.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.issuecoupon.postnewissuecoupon.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('create_lucky_draw')) {
                Event::fire('orbit.issuecoupon.postnewissuecoupon.authz.notallowed', array($this, $user));

                $errorMessage = Lang::get('validation.orbit.actionlist.a');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $errorMessage));

                ACL::throwAccessForbidden($message);
            }
*/
            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = ['super admin', 'mall admin', 'mall owner', 'mall customer service'];
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this page.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.issuecoupon.postnewissuecoupon.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $tenants = OrbitInput::post('tenants');
            $amounts = OrbitInput::post('amounts');
            $receipts = OrbitInput::post('receipts');
            $receiptDates = OrbitInput::post('receipt_dates');
            $paymentTypes = OrbitInput::post('payment_types');
            $userId = OrbitInput::post('user_id');

            $validator = Validator::make(
                array(
                    'tenants'       => $tenants,
                    'amounts'       => $amounts,
                    'receipts'      => $receipts,
                    'receipt_dates' => $receiptDates,
                    'payment_types' => $paymentTypes,
                    'user_id'       => $userId,
                ),
                array(
                    'tenants'       => 'array|required',
                    'amounts'       => 'array|required',
                    'receipts'      => 'array|required',
                    'receipt_dates' => 'array|required',
                    'payment_types' => 'array|required',
                    'user_id'       => 'required|numeric|orbit.empty.user',
                )
            );

            Event::fire('orbit.issuecoupon.postnewissuecoupon.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.issuecoupon.postnewissuecoupon.after.validation', array($this, $validator));

            $customer = App::make('orbit.empty.user');
            $userId = $customer->user_id;

            // Begin database transaction
            $this->beginTransaction();

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

            $applicableCoupons = Coupon::getApplicableCoupons($totalAmount, $tenants);
            $applicableCouponsCount = RecordCounter::create($applicableCoupons)->count();

            if ($applicableCouponsCount === 0) {
                $errorMessage = sprintf('There is no applicable coupon for those amount (or tenants).');
                ACL::throwAccessForbidden($errorMessage);
            }

            foreach ($receiptDates as $i=>$receiptDate) {
                $result = date_parse_from_format('Y-m-d', $receiptDate);

                if (! empty($result['warnings'])) {
                    $errorMessage = sprintf('Receipt date format is invalid on item number %s.', ++$i);
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }
            }

            $applicableCoupons = $applicableCoupons->get();

            $applicableCouponIds = [];
            $applicableCouponNames = [];
            foreach ($applicableCoupons as $row) {
                $applicableCouponIds[] = $row->promotion_id;
                $applicableCouponNames[] = $row->promotion_name;
            }

            Event::fire('orbit.issuecoupon.postnewissuecoupon.before.save', array($this, $widget));

            // Insert each applicable coupons to the issued coupons
            $numberOfCouponIssued = 0;
            $issuedCoupons = [];
            foreach ($applicableCoupons as $applicable) {
                $numberNeedToIssue = floor($totalAmount / $applicable->rule_value);

                for ($i=0; $i<$numberNeedToIssue; $i++) {
                    $issuedCoupon = new IssuedCoupon();
                    $tmp = $issuedCoupon->issue($applicable, $userId, $user);

                    $obj = new stdClass();
                    $obj->coupon_number = $tmp->issued_coupon_code;
                    $obj->coupon_name = $applicable->promotion_name;
                    $issuedCoupons[] = $obj;
                    $numberOfCouponIssued++;

                    $tmp = NULL;
                    $obj = NULL;
                }
            }

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
                                                        ->where(function($query) {
                                                            $query->where('object_type', 'coupon');
                                                            $query->orwhereNull('object_type');
                                                        })
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
                $luckyDrawReceipt->receipt_payment_type = $paymentTypes[$i];
                $luckyDrawReceipt->status = 'active';
                $luckyDrawReceipt->created_by = $user->user_id;
                $luckyDrawReceipt->object_type = 'coupon';

                $luckyDrawReceipt->save();

                $luckyDrawReceipt->coupons()->attach($applicableCouponIds);
            }

            Event::fire('orbit.issuecoupon.postnewissuecoupon.after.save', array($this, $widget));

            $data = new stdClass();
            $data->coupon_names = $applicableCouponNames;
            $data->coupon_numbers = $issuedCoupons;
            $data->total_coupon_issued = $numberOfCouponIssued;

            $this->response->data = $data;

            // Insert to alert system
            $this->insertCouponInbox($userId, $issuedCoupons);

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activity->setUser($user)
                    ->setActivityName('issue_coupon_number')
                    ->setActivityNameLong('Issue Coupon Number')
                    ->setObject(NULL)
                    ->responseOK();

            Event::fire('orbit.issuecoupon.postnewissuecoupon.after.commit', array($this, $widget));
        } catch (ACLForbiddenException $e) {
            // Rollback the changes
            $this->rollBack();

            Event::fire('orbit.issuecoupon.postnewissuecoupon.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('issue_coupon_number')
                    ->setActivityNameLong('Issue Coupon Number Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            // Rollback the changes
            $this->rollBack();

            Event::fire('orbit.issuecoupon.postnewissuecoupon.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 400;

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('issue_lucky_draw')
                    ->setActivityNameLong('Issue Coupon Number Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            // Rollback the changes
            $this->rollBack();

            Event::fire('orbit.issuecoupon.postnewissuecoupon.query.error', array($this, $e));

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
                    ->setActivityNameLong('Issue Coupon Number Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            // Rollback the changes
            $this->rollBack();

            Event::fire('orbit.issuecoupon.postnewissuecoupon.general.exception', array($this, $e));

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
                    ->setActivityNameLong('Issue Coupon Number Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save the activity
        $activity->save();

        return $this->render($httpCode);
    }

    /**
     * POST - Issue Coupon Number (the manual way)
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @return Illuminate\Support\Facades\Response
     */
    public function postIssueCouponManual()
    {
        $activity = Activity::portal()
                            ->setActivityType('create');

        $customer = NULL;
        $widget = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.issuecoupon.postnewissuecoupon.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.issuecoupon.postnewissuecoupon.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.issuecoupon.postnewissuecoupon.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('create_lucky_draw')) {
                Event::fire('orbit.issuecoupon.postnewissuecoupon.authz.notallowed', array($this, $user));

                $errorMessage = Lang::get('validation.orbit.actionlist.a');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $errorMessage));

                ACL::throwAccessForbidden($message);
            }
*/
            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = ['super admin', 'mall admin', 'mall owner', 'mall customer service'];
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this page.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.issuecoupon.postnewissuecoupon.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $coupons = OrbitInput::post('coupon_ids');
            $userId = OrbitInput::post('user_id');

            $validator = Validator::make(
                array(
                    'coupon_ids'       => $coupons,
                    'user_id'          => $userId,
                ),
                array(
                    'coupon_ids'        => 'array|required',
                    'user_id'           => 'required|numeric|orbit.empty.user',
                ),
                array(
                    'user_id.required'      => 'Please select customer first',
                    'coupon_ids.required'   => 'Please select a coupon first'
                )
            );

            Event::fire('orbit.issuecoupon.postnewissuecoupon.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.issuecoupon.postnewissuecoupon.after.validation', array($this, $validator));

            $customer = App::make('orbit.empty.user');
            $userId = $customer->user_id;

            // Begin database transaction
            $this->beginTransaction();

            Event::fire('orbit.issuecoupon.postnewissuecoupon.before.save', array($this, $widget));

            // Issue coupons
            $issuedCoupons = [];
            $numberOfCouponIssued = 0;
            $applicableCouponNames = [];
            $issuedCouponNames = [];
            $prefix = DB::getTablePrefix();

            foreach ($coupons as $couponId) {
                $coupon = Coupon::select('promotions.*',
                                         DB::raw("(select count(ic.issued_coupon_id) from {$prefix}issued_coupons ic
                                                  where ic.promotion_id={$prefix}promotions.promotion_id
                                                  and ic.status!='deleted') as total_issued_coupon"))
                                ->active('promotions')
                                ->where('end_date', '>=', DB::raw('now()'))
                                ->where('promotion_id', $couponId)->first();

                if (empty($coupon)) {
                    $errorMessage = sprintf('Coupon ID %s is not found.', $couponId);
                    OrbitShopAPI::throwInvalidArgument(htmlentities($errorMessage));
                }

                if (! trim($coupon->maximum_issued_coupon) !== '' &&  trim($coupon->maximum_issued_coupon) !== '0') {
                    if ($coupon->maximum_issued_coupon <= $coupon->total_issued_coupon) {
                        $errorMessage = sprintf('Coupon `%s` has been exceeded maximum issued coupon.', $coupon->promotion_name);
                        OrbitShopAPI::throwInvalidArgument(htmlentities($errorMessage));
                    }
                }

                $issuedCoupon = new IssuedCoupon();
                $tmp = $issuedCoupon->issue($coupon, $userId, $user);

                $obj = new stdClass();
                $obj->coupon_number = $tmp->issued_coupon_code;
                $obj->coupon_name = $coupon->promotion_name;
                $obj->promotion_id = $coupon->promotion_id;

                $issuedCoupons[] = $obj;
                $applicableCouponNames[] = $coupon->promotion_name;
                $issuedCouponNames[$tmp->issued_coupon_code] = $coupon->promotion_name;

                $tmp = NULL;
                $obj = NULL;

                $numberOfCouponIssued++;
            }

            Event::fire('orbit.issuecoupon.postnewissuecoupon.after.save', array($this, $widget));

            $data = new stdClass();
            $data->coupon_names = $applicableCouponNames;
            $data->coupon_numbers = $issuedCoupons;
            $data->total_coupon_issued = $numberOfCouponIssued;

            $this->response->data = $data;

            // Insert to alert system
            $issuedCouponNames = $this->flipArrayElement($issuedCouponNames);
            $this->insertCouponInbox($userId, $issuedCoupons, $issuedCouponNames);

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activity->setUser($customer)
                    ->setActivityName('issue_coupon_number')
                    ->setActivityNameLong('Issue Coupon Number')
                    ->setObject(NULL)
                    ->setStaff($user)
                    ->responseOK();

            Event::fire('orbit.issuecoupon.postnewissuecoupon.after.commit', array($this, $widget));
        } catch (ACLForbiddenException $e) {
            // Rollback the changes
            $this->rollBack();

            Event::fire('orbit.issuecoupon.postnewissuecoupon.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('issue_coupon_number')
                    ->setActivityNameLong('Issue Coupon Number Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            // Rollback the changes
            $this->rollBack();

            Event::fire('orbit.issuecoupon.postnewissuecoupon.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 400;

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('issue_lucky_draw')
                    ->setActivityNameLong('Issue Coupon Number Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            // Rollback the changes
            $this->rollBack();

            Event::fire('orbit.issuecoupon.postnewissuecoupon.query.error', array($this, $e));

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
                    ->setActivityNameLong('Issue Coupon Number Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            // Rollback the changes
            $this->rollBack();

            Event::fire('orbit.issuecoupon.postnewissuecoupon.general.exception', array($this, $e));

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
                    ->setActivityNameLong('Issue Coupon Number Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save the activity
        $activity->save();

        return $this->render($httpCode);
    }

    protected function registerCustomValidation()
    {
        // Check the existance of lucky draw id
        Validator::extend('orbit.empty.lucky_draw', function ($attribute, $value, $parameters) {
            $luckyDraw = LuckyDraw::active()->where('lucky_draw_id', $value)->first();

            if (empty($luckyDraw)) {
                $errorMessage = sprintf('Lucky draw ID %s is not found.', $value);
                OrbitShopAPI::throwInvalidArgument(htmlentities($errorMessage));
            }

            $now = strtotime(date('Y-m-d'));
            $luckyDrawDate = strtotime(date('Y-m-d', strtotime($luckyDraw->end_date)));

            if ($now > $luckyDrawDate) {
                $errorMessage = sprintf('The lucky draw already expired on %s.', date('d-M-Y', strtotime($luckyDraw->end_date)));
                OrbitShopAPI::throwInvalidArgument(htmlentities($errorMessage));
            }

            App::instance('orbit.empty.lucky_draw', $luckyDraw);

            return TRUE;
        });

        // Check the existance of user id
        Validator::extend('orbit.empty.user', function ($attribute, $value, $parameters) {
            $user = User::excludeDeleted()->where('user_id', $value)->first();

            if (empty($user)) {
                $errorMessage = sprintf('User ID %s is not found.', $value);
                OrbitShopAPI::throwInvalidArgument(htmlentities($errorMessage));
            }

            if (strtolower($user->status) !== 'active') {
                $errorMessage = sprintf('Status of user %s is not active.', $value);
                OrbitShopAPI::throwInvalidArgument(htmlentities($errorMessage));
            }

            // The user should be already membership
            if (trim($user->membership_number) === '') {
                $errorMessage = sprintf('User %s does not have membership number.', $user->user_email);
                OrbitShopAPI::throwInvalidArgument(htmlentities($errorMessage));
            }

            App::instance('orbit.empty.user', $user);

            return TRUE;
        });
    }

    /**
     * Method to group the issued coupon number based on the coupon name.
     *
     * Array (
     *    '101' => 'A',
     *    '102' => 'A',
     *    '103' => 'B'
     * )
     *
     * Becomes
     *
     * Array(
     *  'A' => [
     *      '101',
     *      '102',
     *  ],
     *  'B'  => [
     *     '103'
     *  ]
     * )
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param array $source the Array element
     * @return array
     */
    protected function flipArrayElement($source)
    {
        $flipped = [];

        $names = array_flip(array_unique(array_values($source)));
        foreach ($names as $key=>$name) {
            $names[$key] = [];
        }

        foreach ($source as $number=>$name) {
            $flipped[$name][] = $number;
        }

        return $flipped;
    }

    /**
     * Insert issued lucky draw numbers into inbox table.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param int $userId - The user id
     * @param array $numbers - Issued numbers
     * @param int $retailerId - The retailer
     * @return void
     */
    protected function insertLuckyDrawNumberInbox($userId, $numbers, $retailerId)
    {
        $user = User::find($userId);

        if (empty($user)) {
            throw new Exception ('Customer user ID not found.');
        }

        $name = $user->getFullName();
        $name = $name ? $name : $user->email;
        $subject = Lang::get('mobileci.inbox.lucky_draw.subject');

        $inbox = new Inbox();
        $inbox->user_id = $userId;
        $inbox->from_id = 0;
        $inbox->from_name = 'Orbit';
        $inbox->subject = $subject;
        $inbox->content = '';
        $inbox->inbox_type = 'alert';
        $inbox->status = 'active';
        $inbox->is_read = 'N';
        $inbox->save();

        $luckyDraw = App::make('orbit.empty.lucky_draw');

        $retailer = Retailer::isMall()->where('merchant_id', $retailerId)->first();
        $data = [
            'fullName'          => $name,
            'subject'           => 'Lucky Draw',
            'inbox'             => $inbox,
            'retailerName'      => $retailer->name,
            'numberOfLuckyDraw' => count($numbers),
            'numbers'           => $numbers,
            'luckyDrawCampaign' => $luckyDraw->lucky_draw_name,
            'mallName'          => $retailer->name
        ];

        $template = View::make('mobile-ci.push-notification-lucky-draw', $data);
        $template = $template->render();

        $inbox->content = $template;
        $inbox->save();
    }

    /**
     * Insert issued coupon numbers into inbox table.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param int $userId - The user id
     * @param array $coupons - Issued Coupons
     * @param array $couponNames - Issued Coupons with name based
     * @return void
     */
    protected function insertCouponInbox($userId, $coupons, $couponNames)
    {
        $user = User::find($userId);

        if (empty($user)) {
            throw new Exception ('Customer user ID not found.');
        }

        $name = $user->getFullName();
        $name = $name ? $name : $user->email;
        $subject = Lang::get('mobileci.inbox.coupon.subject');

        $inbox = new Inbox();
        $inbox->user_id = $userId;
        $inbox->from_id = 0;
        $inbox->from_name = 'Orbit';
        $inbox->subject = $subject;
        $inbox->content = '';
        $inbox->inbox_type = 'alert';
        $inbox->status = 'active';
        $inbox->is_read = 'N';
        $inbox->save();

        $retailerId = Config::get('orbit.shop.id');
        $retailer = Retailer::isMall()->where('merchant_id', $retailerId)->first();
        $data = [
            'fullName'          => $name,
            'subject'           => 'Coupon',
            'inbox'             => $inbox,
            'retailerName'      => $retailer->name,
            'numberOfCoupon'    => count($coupons),
            'coupons'           => $couponNames,
            'mallName'          => $retailer->name
        ];

        $template = View::make('mobile-ci.push-notification-coupon', $data);
        $template = $template->render();

        $inbox->content = $template;
        $inbox->save();
    }
}
