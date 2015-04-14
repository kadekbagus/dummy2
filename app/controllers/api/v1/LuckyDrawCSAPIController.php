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

            // Insert to alert system
            $this->insertIntoInbox($userId, $luckyDrawnumbers);

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
     *
     * List of API Parameters
     * ----------------------
     * @return Illuminate\Support\Facades\Response
     */
    public function postIssueCouponNumber()
    {
        $activity = Activity::portal()
                            ->setActivityType('create');

        $user = NULL;
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
            $userLuckyNumber = OrbitInput::post('lucky_number', NULL);

            $validator = Validator::make(
                array(
                    'tenants'       => $tenants,
                    'amounts'       => $amounts,
                    'receipts'      => $receipts,
                    'receipt_dates' => $receiptDates,
                    'payment_types' => $paymentTypes,
                    'user_id'       => $userId,
                    'lucky_number'  => $userLuckyNumber
                ),
                array(
                    'tenants'       => 'array|required',
                    'amounts'       => 'array|required',
                    'receipts'      => 'array|required',
                    'receipt_dates' => 'array|required',
                    'payment_types' => 'array|required',
                    'user_id'       => 'required|numeric',
                    'lucky_number'  => 'numeric|min:0:max:9'
                )
            );

            Event::fire('orbit.issuecoupon.postnewissuecoupon.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.issuecoupon.postnewissuecoupon.after.validation', array($this, $validator));

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

            $applicableCoupons = Coupon::getApplicableCoupons($totalAmount);
            $applicableCouponsCount = RecordCounter::create($applicableCoupons)->count();

            if ($applicableCoupons === 0) {
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
                $issuedCoupon = new IssuedCoupon();
                $issuedCoupon->issue($applicable, $userId, $user);

                $issuedCoupons[$issuedCoupon->issued_coupon_code] = $applicable->promotion_name;
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

            $this->response->data = $data;

            // Insert to alert system
            // $this->insertIntoInbox($userId, $luckyDrawnumbers);

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activity->setUser($user)
                    ->setActivityName('issue_lucky_draw')
                    ->setActivityNameLong('Issue Lucky Draw')
                    ->setObject($luckyDraw)
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
                    ->setActivityName('issue_lucky_draw')
                    ->setActivityNameLong('Issue Lucky Draw Failed')
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
                    ->setActivityNameLong('Issue Lucky Draw Failed')
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
                    ->setActivityNameLong('Issue Lucky Draw Failed')
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
                    ->setActivityNameLong('Issue Lucky Draw Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save the activity
        $activity->save();

        return $this->render($httpCode);
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

    /**
     * Insert issued lucky draw numbers into inbox table.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param int $userId - The user id
     * @param array $numbers - Issued numbers
     * @return void
     */
    protected function insertIntoInbox($userId, $numbers)
    {
        $user = User::active()->find($userId);
        $name = $user->getFullName();
        $name = $name ? $name : $user->email;

        // Oh yeah, we have mixed the view!!
        $template = <<<VIEW
<div class="modal fade" id="numberModal" tabindex="-1" role="dialog" aria-labelledby="numberModalLabel" aria-hidden="true">
    <div class="modal-dialog orbit-modal">
        <div class="modal-content">
            <div class="modal-body">
                <p style="margin-bottom: 1em;"><strong>You Got New Lucky Draw Numbers!</strong></p>
                <p>Hello {{NAME}},
                <br><br>

                Congratulation you got {{NUMBERS}} lucky draw number:
                <ul>
                    {{LIST}}
                </ul>
                </p>

                <br>
                <p>Lippo Mall</p>
            </div>
        </div>
    </div>
</div>
VIEW;

        $list = '';
        foreach ($numbers as $number) {
            $list .= '<li>' . str_pad($number->lucky_draw_number_code, '0', STR_PAD_LEFT) . '</li>' . "\n";
        }

        $template = str_replace('{{NAME}}', $name, $template);
        $template = str_replace('{{NUMBERS}}', count($numbers), $template);
        $template = str_replace('{{LIST}}', $list, $template);

        $inbox = new Inbox();
        $inbox->user_id = $userId;
        $inbox->from_id = 0;
        $inbox->from_name = 'Orbit';
        $inbox->subject = 'You got new lucky draw number.';
        $inbox->content = $template;
        $inbox->inbox_type = 'alert';
        $inbox->status = 'active';
        $inbox->is_read = 'N';
        $inbox->save();
    }
}
