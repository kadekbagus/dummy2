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
            $popup = OrbitInput::post('popup', 'no');

            // validate user mall id for current_mall
            $mallId = OrbitInput::post('current_mall');
            $listOfMallIds = $user->getUserMallIds($mallId);
            if (empty($listOfMallIds)) { // invalid mall id
                $errorMessage = 'Invalid mall id.';
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            } else {
                $mallId = $listOfMallIds[0];
            }

            $validator = Validator::make(
                array(
                    'current_mall'              => $mallId,
                    'user_id'                   => $userId,
                    'lucky_draw_id'             => $luckyDrawId,
                    'lucky_draw_number_start'   => $luckyDrawNumberStart,
                    'lucky_draw_number_end'     => $luckyDrawNumberEnd,
                    'receipts'                  => $receipts
                ),
                array(
                    'current_mall'              => 'required|orbit.empty.mall',
                    'user_id'                   => 'required|orbit.user.exists',
                    'lucky_draw_id'             => 'required|orbit.lucky_draw.exists',
                    'lucky_draw_number_start'   => 'required|numeric|orbit.number_start_greater_than_or_equal_to_min_number:' . $luckyDrawId . '|orbit.number_unused:' . $luckyDrawId,
                    'lucky_draw_number_end'     => 'required|numeric|orbit.number_end_less_than_or_equal_to_max_number:' . $luckyDrawId . '|orbit.number_unused:' . $luckyDrawId,
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
            $savedReceipts = LuckyDrawReceipt::saveFromArrayObject($mallId, $user, $decodedReceipts, $customer);
            $total_receipts = count($savedReceipts);

            // calculate $total_receipts_amount
            $total_receipts_amount = 0;
            foreach($savedReceipts as $r) {
                $total_receipts_amount += $r->receipt_amount;
            };

            $group = $savedReceipts[0]->receipt_group;

            // Save the lucky draw numbers
            $luckyDraw = $this->cachedValidationResult['orbit.lucky_draw.exists'];
            $number = $luckyDrawNumberEnd - $luckyDrawNumberStart;
            $issueType = 'sequence';
            $luckyNumberDriven = 0;
            $employeeUserId = $user->user_id;
            $issueDate = date('Y-m-d H:i:s');
            $status = 'active';
            $maxRecordReturned = Config::get('orbit.pagination.lucky_draw.max_record', 50);
            $minimumNumber = $luckyDraw->min_number;

            // Number of issuance should not more than one defined in config
            $maxIssuance = Config::get('orbit.lucky_draw.max_per_issuance', 1000);

            // @todo
            // If the number more than max issuance, what we gonna do? because there is possibilities of
            // a customer who got number more than our maximum. Send it to the queue?
            if ($number > $maxIssuance) {
                $errorMessage = Lang::get('validation.exceed.lucky_draw.max_issuance', ['max_number' => $maxIssuance]);
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // Issue the number
            $maxReturnedRecord = 50;
            $luckyDrawnumbers = [];
            for ($i=$luckyDrawNumberStart; $i<=$luckyDrawNumberEnd; $i++) {
                $newNumber = new LuckyDrawNumber();
                $newNumber->lucky_draw_id = $luckyDraw->lucky_draw_id;
                $newNumber->user_id = $userId;
                $newNumber->lucky_draw_number_code = $i;
                $newNumber->issued_date = date('Y-m-d H:i:s');
                $newNumber->hash = $group;
                $newNumber->status = 'active';
                $newNumber->created_by = $this->api->user->user_id;
                $newNumber->modified_by = $this->api->user->user_id;
                if (! $newNumber->save()) {
                    $errorMessage = Lang::get('validation.save_error.lucky_draw.issue_number', ['number' => $i]);
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }

                $luckyDrawnumbers[] = $newNumber;
            }

            // Save each associated receipt and its LD number
            foreach ($savedReceipts as $savedReceipt) {
                LuckyDrawNumberReceipt::syncUsingHashNumber($savedReceipt->lucky_draw_receipt_id, $group);
            }

            Event::fire('orbit.luckydraw.postnewluckydrawnumber.before.save', array($this, $savedReceipts, $luckyDrawnumbers));

            Event::fire('orbit.luckydraw.postnewluckydrawnumber.after.save', array($this, $savedReceipts, $luckyDrawnumbers));

            $data = new stdClass();
            $data->lucky_draw_id = $luckyDrawId;
            $data->receipt_group = $group;
            $data->lucky_draw_number_start = $luckyDrawNumberStart;
            $data->lucky_draw_number_end = $luckyDrawNumberEnd;
            $data->total_receipts = $total_receipts;
            $data->total_amounts = $total_receipts_amount;
            $data->total_generated_lucky_draw_number = count($luckyDrawnumbers);
            $data->records = $luckyDrawnumbers;
            $data->total_records = $data->total_generated_lucky_draw_number;

            $this->response->data = $data;

            if ($popup === 'yes') {
                $this->insertLuckyDrawNumberInbox($customer, $data, $mallId, $luckyDraw);
            }

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

    /**
     * GET - Search Lucky Draw Number
     *
     * List of API Parameters
     * ----------------------
     * @param string   `with`                  (optional) - Valid value: .
     * @param string   `sortby`                (optional) - Column order by. Valid value: .
     * @param string   `sortmode`              (optional) - ASC or DESC
     * @param integer  `take`                  (optional) - Limit
     * @param integer  `skip`                  (optional) - Limit offset
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchLuckyDrawNumber()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.luckydrawnumber.getsearchluckydrawnumber.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.luckydrawnumber.getsearchluckydrawnumber.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.luckydrawnumber.getsearchluckydrawnumber.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('view_lucky_draw')) {
                Event::fire('orbit.luckydrawnumber.getsearchluckydrawnumber.authz.notallowed', array($this, $user));
                $viewLuckyDrawLang = Lang::get('validation.orbit.actionlist.view_lucky_draw');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewLuckyDrawLang));
                ACL::throwAccessForbidden($message);
            }
*/
            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = ['super admin', 'mall admin', 'mall owner', 'mall customer service', 'consumer'];
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.luckydrawnumber.getsearchluckydrawnumber.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sortby');
            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                ),
                array(
                    'sort_by' => 'in:lucky_draw_number,lucky_draw_id,user_id',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.lucky_draw_number_sortby'),
                )
            );

            Event::fire('orbit.luckydrawnumber.getsearchluckydrawnumber.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.luckydrawnumber.getsearchluckydrawnumber.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.lucky_draw_number.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.lucky_draw_number.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            // Builder object
            $luckydraws = LuckyDrawNumber::excludeDeleted('lucky_draw_numbers')
                                          ->select('lucky_draw_numbers.*', 'lucky_draw_number_receipt.lucky_draw_receipt_id')
                                          ->where('lucky_draw_receipts.object_type', 'lucky_draw')
                                          ->join('lucky_draw_number_receipt', 'lucky_draw_number_receipt.lucky_draw_number_id', '=', 'lucky_draw_numbers.lucky_draw_number_id')
                                          ->join('lucky_draw_receipts', 'lucky_draw_receipts.lucky_draw_receipt_id', '=', 'lucky_draw_number_receipt.lucky_draw_receipt_id')
                                          ->join('lucky_draws', 'lucky_draws.lucky_draw_id', '=', 'lucky_draw_numbers.lucky_draw_id')
                                          ->groupBy('lucky_draw_numbers.lucky_draw_number_id')
                                          ;

             // Filter by lucky_draw_number
            OrbitInput::get('lucky_draw_number', function($data) use ($luckydraws)
            {
                $luckydraws->whereIn('lucky_draw_numbers.lucky_draw_number_code', $data);
            });

            // Filter by matching pattern lucky_draw_number
            OrbitInput::get('lucky_draw_number_like', function($data) use ($luckydraws)
            {
                $luckydraws->where('lucky_draw_numbers.lucky_draw_number_code', 'like', "%$data%");
            });

            // Filter by lucky_draw_id
            OrbitInput::get('lucky_draw_id', function($data) use ($luckydraws)
            {
                $luckydraws->whereIn('lucky_draw_numbers.lucky_draw_id', $data);
            });

            // Filter by external_lucky_draw_id
            OrbitInput::get('external_lucky_draw_id', function($data) use ($luckydraws)
            {
                $luckydraws->whereIn('lucky_draws.external_lucky_draw_id', $data);
            });

            // Filter by user_id
            OrbitInput::get('user_id', function($data) use ($luckydraws)
            {
                $luckydraws->whereIn('lucky_draw_numbers.user_id', $data);
            });

            // Filter by retailer_id
            OrbitInput::get('retailer_id', function($data) use ($luckydraws)
            {
                $luckydraws->whereIn('lucky_draw_receipts.receipt_retailer_id', $data);
            });

            // Filter by status
            OrbitInput::get('status', function ($data) use ($luckydraws) {
                $luckydraws->whereIn('lucky_draw_numbers.status', $data);
            });

            // Filter by created_at date
            OrbitInput::get('created_at_after', function($data) use ($luckydraws) {
                $luckydraws->where('lucky_draw_numbers.created_at', '>=', $data);
            });

            // Filter by created_at date
            OrbitInput::get('created_at_before', function($data) use ($luckydraws) {
                $luckydraws->where('lucky_draw_numbers.created_at', '<=', $data);
            });

            // Filter by updated_at date
            OrbitInput::get('updated_at_after', function($data) use ($luckydraws) {
                $luckydraws->where('lucky_draw_numbers.updated_at', '>=', $data);
            });

            // Filter by updated_at date
            OrbitInput::get('updated_at_before', function($data) use ($luckydraws) {
                $luckydraws->where('lucky_draw_numbers.updated_at', '<=', $data);
            });

            // groupBy
            OrbitInput::get('group_by', function($data) use ($luckydraws)
            {
                $data = (array) $data;

                foreach ($data as $groupBy) {
                    if ($groupBy === 'lucky_draw_number_id') {
                        $luckydraws->groupBy('lucky_draw_numbers.lucky_draw_number_id');
                    }
                }
            });

            // Add new relation based on request
            OrbitInput::get('with', function ($with) use ($luckydraws) {
                $with = (array) $with;

                foreach ($with as $relation) {
                    if ($relation === 'lucky_draw') {
                        $luckydraws->with('luckyDraw');
                    } elseif ($relation === 'user') {
                        $luckydraws->with('user');
                    } elseif ($relation === 'receipts') {
                        $luckydraws->with('receipts');
                    }
                }
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_luckydraws = clone $luckydraws;

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
            $luckydraws->take($take);

            $skip = 0;
            OrbitInput::get('skip', function($_skip) use (&$skip, $luckydraws)
            {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            if (($take > 0) && ($skip > 0)) {
                $luckydraws->skip($skip);
            }

            // Default sort by
            $sortBy = 'lucky_draw_numbers.created_at';
            // Default sort mode
            $sortMode = 'desc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'registered_date'          => 'lucky_draw_numbers.created_at',
                    'lucky_draw_number'        => 'lucky_draw_numbers.lucky_draw_number_code',
                    'lucky_draw_id'            => 'lucky_draw_numbers.lucky_draw_id',
                    'user_id'                  => 'lucky_draw_numbers.user_id',
                    'status'                   => 'lucky_draw_numbers.status',
                );

                if (array_key_exists($_sortBy, $sortByMapping)) {
                    $sortBy = $sortByMapping[$_sortBy];
                }
            });

            if ($sortBy !== 'lucky_draw_numbers.created_at') {
                $luckydraws->orderBy('lucky_draw_numbers.created_at', 'desc');
            }

            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) !== 'desc') {
                    $sortMode = 'asc';
                }
            });

            $luckydraws->orderBy($sortBy, $sortMode);

            $totalLuckyDraws = RecordCounter::create($_luckydraws)->count();
            $listOfLuckyDraws = $luckydraws->get();

            $data = new stdclass();
            $data->total_records = $totalLuckyDraws;
            $data->returned_records = count($listOfLuckyDraws);
            $data->records = $listOfLuckyDraws;

            if ($totalLuckyDraws === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.lucky_draw_number');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.luckydrawnumber.getsearchluckydrawnumber.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.luckydrawnumber.getsearchluckydrawnumber.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.luckydrawnumber.getsearchluckydrawnumber.query.error', array($this, $e));

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
            Event::fire('orbit.luckydrawnumber.getsearchluckydrawnumber.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.luckydrawnumber.getsearchluckydrawnumber.before.render', array($this, &$output));

        return $output;
    }

    protected function registerCustomValidation()
    {

        // Check the existance of mall id
        Validator::extend('orbit.empty.mall', function ($attribute, $value, $parameters) {
            $mall = Mall::excludeDeleted()
                            ->where('merchant_id', $value)
                            ->first();

            if (empty($mall)) {
                return FALSE;
            }

            App::instance('orbit.empty.mall', $mall);

            return TRUE;
        });

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

        // Check the POST lucky_draw_number_start, it should be greater than or equal to lucky draw campaign min_number
        Validator::extend('orbit.number_start_greater_than_or_equal_to_min_number', function ($attribute, $value, $parameters) {
            $luckyDrawId = $parameters[0];
            $number_start = $value;

            $data = LuckyDraw::excludeDeleted()
                             ->where('lucky_draw_id', $luckyDrawId)
                             ->first();

            if ($number_start < $data->min_number) {
                $errorMessage = sprintf('Lucky draw number start should be greater than or equal to lucky draw campaign min number.', $value);
                OrbitShopAPI::throwInvalidArgument(htmlentities($errorMessage));
            }

            return TRUE;
        });

        // Check the POST lucky_draw_number_end, it should be less than or equal to lucky draw campaign max_number
        Validator::extend('orbit.number_end_less_than_or_equal_to_max_number', function ($attribute, $value, $parameters) {
            $luckyDrawId = $parameters[0];
            $number_end = $value;

            $data = LuckyDraw::excludeDeleted()
                             ->where('lucky_draw_id', $luckyDrawId)
                             ->first();

            if ($number_end > $data->max_number) {
                $errorMessage = sprintf('Lucky draw number end should be less than or equal to lucky draw campaign max number.', $value);
                OrbitShopAPI::throwInvalidArgument(htmlentities($errorMessage));
            }

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

            $attributes = ['receipt_retailer_id', 'receipt_number', 'receipt_date', 'receipt_payment_type', 'receipt_card_number', 'receipt_amount', 'external_receipt_id', 'external_retailer_id'];
            $validPayments = ['cash', 'credit_card', 'debit_card', 'other', 'BCA', 'BNI', 'BRI', 'BTN', 'CIMB NIAGA', 'DANAMON', 'MANDIRI', 'MEGA', 'PANIN', 'PERMATA'];
            $validPayments = array_map('strtolower', $validPayments);
            foreach ($result as $receipt) {
                // Check attributes
                foreach ($attributes as $attr) {
                    if (! property_exists($receipt, $attr)) {
                        $errorMessage = sprintf('Attribute %s in receipts json is not found.', $attr);
                        OrbitShopAPI::throwInvalidArgument($errorMessage);
                    }
                }

                // Check retailer ID
                $retailer = Mall::excludeDeleted()->find($receipt->receipt_retailer_id);
                if (empty($retailer)) {
                    $errorMessage = sprintf('Retailer ID %s on receipt is not found.', $receipt->receipt_payment_type);
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }

                // Check payment type
                if (! in_array(strtolower($receipt->receipt_payment_type), $validPayments)) {
                    $errorMessage = sprintf('Payment type %s on receipt is not found.', $receipt->receipt_payment_type);
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }
            }

            $this->cachedValidationResult['orbit.check_json'] = $result;

            return TRUE;
        });
    }

    /**
     * Insert issued lucky draw numbers into inbox table.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param User $userId - The User object
     * @param array $response - Issued numbers
     * @param int $retailerId - The retailer
     * @param LuckyDraw $luckyDraw - The LuckyDraw object
     * @return void
     */
    public function insertLuckyDrawNumberInbox($user, $response, $retailerId, $luckyDraw)
    {
        $name = $user->getFullName();
        $name = $name ? $name : $user->email;
        $userId = $user->user_id;
        $subject = 'Lucky Draw';

        $inbox = new Inbox();
        $inbox->user_id = $userId;
        $inbox->merchant_id = $retailerId;
        $inbox->from_id = 0;
        $inbox->from_name = 'Orbit';
        $inbox->subject = $subject;
        $inbox->content = '';
        $inbox->inbox_type = 'alert';
        $inbox->status = 'active';
        $inbox->is_read = 'N';
        $inbox->save();

        $numbers = array_slice($response->records, 0, 15);

        $dateIssued = date('d-M-Y H:i', strtotime($numbers[0]->issued_date));

        $totalLuckyDrawNumber = LuckyDrawNumber::active()
                                               ->where('user_id', $userId)
                                               ->where('lucky_draw_id', $luckyDraw->lucky_draw_id)
                                               ->count();

        $retailer = Mall::isMall()->where('merchant_id', $retailerId)->first();
        $data = [
            'fullName'              => $name,
            'subject'               => 'Lucky Draw',
            'inbox'                 => $inbox,
            'retailerName'          => $retailer->name,
            'numberOfLuckyDraw'     => $response->total_records,
            'numbers'               => $numbers,
            'luckyDrawCampaign'     => $luckyDraw->lucky_draw_name,
            'mallName'              => $retailer->name,
            'totalLuckyDrawNumber'  => $totalLuckyDrawNumber,
            'dateIssued'            => $dateIssued,
            'maxShown'              => 15
        ];

        $template = View::make('mobile-ci.push-notification-lucky-draw', $data);
        $template = $template->render();

        $inbox->content = $template;
        $inbox->save();
    }
}
