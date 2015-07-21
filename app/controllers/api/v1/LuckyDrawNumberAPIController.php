<?php
/**
 * An API controller for managing Lucky Draw.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Helper\EloquentRecordCounter as RecordCounter;

class LuckyDrawNumberAPIController extends ControllerAPI
{
    /**
     * Hold the cache of validation result
     *
     * @var array
     */
    public $cachedValidationResult = [];

    /**
     * POST - Create New Lucky Draw
     *
     * List of API Parameters
     * ----------------------
     * @param string     `lucky_draw_name`       (required) - Lucky Draw name
     * @param string     `status`                (required) - Status. Valid value: active, inactive, pending, blocked, deleted.
     * @param string     `description`           (optional) - Description
     * @param datetime   `start_date`            (optional) - Start date. Example: 2015-04-13 00:00:00
     * @param datetime   `end_date`              (optional) - End date. Example: 2015-04-13 23:59:59
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postNewLuckyDrawNumber()
    {
        $activity = Activity::portal()
                            ->setActivityType('create');

        $user = NULL;
        $number = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.luckydraw.postnewluckydrawnumber.before.auth', array($this));

            $this->checkAuth();

            Event::fire('orbit.luckydraw.postnewluckydrawnumber.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.luckydraw.postnewluckydrawnumber.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('create_lucky_draw')) {
                Event::fire('orbit.luckydraw.postnewluckydrawnumber.authz.notallowed', array($this, $user));
                $createLuckyDrawLang = Lang::get('validation.orbit.actionlist.new_lucky_draw');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $createLuckyDrawLang));
                ACL::throwAccessForbidden($message);
            }
*/
            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = ['super admin', 'mall admin', 'mall owner', 'mall customer service'];
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.luckydraw.postnewluckydrawnumber.after.authz', array($this, $user));

            // Begin database transaction
            $this->beginTransaction();

            $this->registerCustomValidation();

            $userId = OrbitInput::post('user_id');
            $luckyDrawId = OrbitInput::post('lucky_draw_id');
            $luckyDrawNumberStart = OrbitInput::post('lucky_draw_number_start');
            $luckyDrawNumberEnd = OrbitInput::post('lucky_draw_number_end');
            $receipts = OrbitInput::post('receipts');

            // Mall ID
            $currentMallId = Config::get('orbit.shop.id');
            $mallId = OrbitInput::post('mall_id', $currentMallId);

            $validator = Validator::make(
                array(
                    'user_id'                   => $userId,
                    'lucky_draw_id'             => $luckyDrawId,
                    'lucky_draw_number_start'   => $luckyDrawNumberStart,
                    'lucky_draw_number_end'     => $luckyDrawNumberEnd,
                    'receipts'                  => $receipts
                ),
                array(
                    'user_id'                   => 'required|orbit.user.exists',
                    'lucky_draw_id'             => 'required|orbit.lucky_draw.exists',
                    'lucky_draw_number_start'   => 'required|numeric|orbit.number_unused:' . $luckyDrawId,
                    'lucky_draw_number_end'     => 'required|numeric|orbit.number_unused:' . $luckyDrawId,
                    'receipts'                  => 'required|orbit.check_json'
                )
            );

            Event::fire('orbit.luckydraw.postnewluckydrawnumber.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            Event::fire('orbit.luckydraw.postnewluckydrawnumber.after.validation', array($this, $validator));

            // Save the receipt first
            $customer = $this->cachedValidationResult['orbit.user.exists'];
            $decodedReceipts = $this->cachedValidationResult['orbit.check_json'];
            $savedReceipts = LuckyDrawReceipt::saveFromArrayObject($mallId, $customer, $decodedReceipts);
            $group = $savedReceipts[0]->receipt_group;

            // Save the lucky draw numbers
            $number = $luckyDrawNumberEnd - $luckyDrawNumberStart;
            $issueType = 'sequence';
            $luckyNumberDriven = 0;
            $employeeUserId = $user->user_id;
            $issueDate = date('Y-m-d H:i:s');
            $status = 'active';
            $maxRecordReturned = Config::get('orbit.pagination.lucky_draw.max_record', 50);

            $storedProcArgs = [
                $number + 1,        // 1, e.g: 1005 - 1001, it should be 5 numbers not 4
                $issueType,         // 2
                $luckyNumberDriven, // 3
                $userId,            // 4
                $employeeUserId,    // 5
                $issueDate,         // 6
                $status,            // 7
                $maxRecordReturned, // 8
                $group,             // 9
                $luckyDrawId        // 10
            ];
            $luckyDrawnumbers = DB::select("call issue_lucky_draw_numberv2(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", $storedProcArgs);

            // Save each associated receipt and its LD number
            foreach ($savedReceipts as $savedReceipt) {
                LuckyDrawNumberReceipt::syncUsingHashNumber($savedReceipt->lucky_draw_receipt_id, $group);
            }

            Event::fire('orbit.luckydraw.postnewluckydrawnumber.before.save', array($this, $savedReceipts, $luckyDrawnumbers));

            Event::fire('orbit.luckydraw.postnewluckydrawnumber.after.save', array($this, $savedReceipts, $luckyDrawnumbers));

            $data = new stdClass();
            $data->returned_records = count($luckyDrawnumbers);
            $data->total_records = (int)$luckyDrawnumbers[0]->total_issued_number;
            $data->records = $luckyDrawnumbers;

            $this->response->data = $data;

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activityNotes = sprintf('Total Lucky Draw Number Created: %s', $number);
            $activity->setUser($user)
                    ->setActivityName('create_lucky_draw_number')
                    ->setActivityNameLong('Create Lucky Draw Number')
                    ->setObject(NULL)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.luckydraw.postnewluckydrawnumber.after.commit', array($this, $savedReceipts, $luckyDrawnumbers));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.luckydraw.postnewluckydrawnumber.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_lucky_draw')
                    ->setActivityNameLong('Create Lucky Draw Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.luckydraw.postnewluckydrawnumber.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_lucky_draw')
                    ->setActivityNameLong('Create Lucky Draw Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.luckydraw.postnewluckydrawnumber.query.error', array($this, $e));

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
                    ->setActivityName('create_lucky_draw')
                    ->setActivityNameLong('Create Lucky Draw Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.luckydraw.postnewluckydrawnumber.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_lucky_draw')
                    ->setActivityNameLong('Create Lucky Draw Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save the activity
        $activity->save();

        return $this->render($httpCode);
    }

    protected function registerCustomValidation()
    {
        // Check the existance of user id
        // @Todo: Make sure it belongs to the mall caller
        Validator::extend('orbit.user.exists', function ($attribute, $value, $parameters) {
            $user = User::excludeDeleted()->where('user_id', $value)->first();

            if (empty($user)) {
                $errorMessage = sprintf('User ID %s is not found.', $value);
                OrbitShopAPI::throwInvalidArgument(htmlentities($errorMessage));
            }

            if (strtolower($user->status) !== 'active') {
                $errorMessage = sprintf('Status of user %s is not active.', $value);
                OrbitShopAPI::throwInvalidArgument(htmlentities($errorMessage));
            }

            $this->cachedValidationResult['orbit.user.exists'] = $user;

            return TRUE;
        });

        // Check the starting and ending number, it should be unused
        Validator::extend('orbit.number_unused', function ($attribute, $value, $parameters) {
            $luckyDrawId = $parameters[0];

            $number = LuckyDrawNumber::excludeDeleted()
                                     ->where('lucky_draw_id', $luckyDrawId)
                                     ->where('lucky_draw_number_code', $value)
                                     ->where(function($query) {
                                        $query->where('user_id', '!=', 0);
                                        $query->orWhereNotNull('user_id');
                                     })->first();

            if (! empty($number)) {
                $errorMessage = sprintf('Lucky draw number %s already issued.', $value);
                OrbitShopAPI::throwInvalidArgument(htmlentities($errorMessage));
            }

            return TRUE;
        });

        // Check the existance of lucky_draw id
        // @Todo: Make sure the lucky draw belongs to the mall caller
        Validator::extend('orbit.lucky_draw.exists', function ($attribute, $value, $parameters) {
            $luckyDraw = LuckyDraw::excludeDeleted()
                                   ->where('lucky_draw_id', $value)
                                   ->first();

            if (empty($luckyDraw)) {
                $errorMessage = sprintf('Lucky draw ID %s is not found.', $value);
                OrbitShopAPI::throwInvalidArgument(htmlentities($errorMessage));
            }

            $this->cachedValidationResult['orbit.lucky_draw.exists'] = $luckyDraw;

            return TRUE;
        });

        // Check the validity of the JSON
        Validator::extend('orbit.check_json', function ($attribute, $value, $parameters) {
            $errorMessage = Lang::get('validation.orbit.jsonerror.field.format');

            if (! is_string($value)) {
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $result = @json_decode($value);
            if (json_last_error() !== JSON_ERROR_NONE) {
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $errorMessage = Lang::get('validation.orbit.jsonerror.field.array');
            if (! is_array($result)) {
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $this->cachedValidationResult['orbit.check_json'] = $result;

            return TRUE;
        });
    }
}