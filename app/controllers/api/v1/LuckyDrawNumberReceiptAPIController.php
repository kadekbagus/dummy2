<?php
/**
 * An API controller for managing Lucky Draw Number Receipt.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Helper\EloquentRecordCounter as RecordCounter;

class LuckyDrawNumberReceiptAPIController extends ControllerAPI
{

    /**
     * GET - Search Lucky Draw Number Receipt
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
    public function getSearchLuckyDrawNumberReceipt()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.luckydrawnumberreceipt.getsearchluckydrawnumberreceipt.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.luckydrawnumberreceipt.getsearchluckydrawnumberreceipt.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.luckydrawnumberreceipt.getsearchluckydrawnumberreceipt.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('view_lucky_draw')) {
                Event::fire('orbit.luckydrawnumberreceipt.getsearchluckydrawnumberreceipt.authz.notallowed', array($this, $user));
                $viewLuckyDrawLang = Lang::get('validation.orbit.actionlist.view_lucky_draw');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewLuckyDrawLang));
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

            Event::fire('orbit.luckydrawnumberreceipt.getsearchluckydrawnumberreceipt.after.authz', array($this, $user));

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
                    'in' => Lang::get('validation.orbit.empty.lucky_draw_number_receipt_sortby'),
                )
            );

            Event::fire('orbit.luckydrawnumberreceipt.getsearchluckydrawnumberreceipt.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.luckydrawnumberreceipt.getsearchluckydrawnumberreceipt.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.lucky_draw_number_receipt.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.lucky_draw_number_receipt.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            // Builder object
            $luckydraws = LuckyDrawReceipt::excludeDeleted('lucky_draw_receipts')
                                          ->select('lucky_draw_receipts.*', 'lucky_draw_numbers.lucky_draw_id', 'lucky_draw_numbers.lucky_draw_number_code')
                                          ->where('object_type', 'lucky_draw')
                                          ->join('lucky_draw_number_receipt', 'lucky_draw_number_receipt.lucky_draw_receipt_id', '=', 'lucky_draw_receipts.lucky_draw_receipt_id')
                                          ->join('lucky_draw_numbers', 'lucky_draw_numbers.lucky_draw_number_id', '=', 'lucky_draw_number_receipt.lucky_draw_number_id')
                                          ;

            // Filter by mall_id
            OrbitInput::get('mall_id', function ($data) use ($luckydraws) {
                $luckydraws->whereIn('lucky_draw_receipts.mall_id', $data);
            });

            // Filter by user_id
            OrbitInput::get('user_id', function($data) use ($luckydraws)
            {
                $luckydraws->whereIn('lucky_draw_receipts.user_id', $data);
            });

            // Filter by receipt_number
            OrbitInput::get('receipt_number', function($data) use ($luckydraws)
            {
                $luckydraws->whereIn('lucky_draw_receipts.receipt_number', $data);
            });

            // Filter by matching pattern receipt_number
            OrbitInput::get('receipt_number_like', function($data) use ($luckydraws)
            {
                $luckydraws->where('lucky_draw_receipts.receipt_number', 'like', "%$data%");
            });

            // Filter by receipt_amount
            OrbitInput::get('amount_gt', function($data) use ($luckydraws)
            {
                $luckydraws->where('lucky_draw_receipts.receipt_amount', '>=', $data);
            });

            // Filter by receipt_amount
            OrbitInput::get('amount_lt', function($data) use ($luckydraws)
            {
                $luckydraws->where('lucky_draw_receipts.receipt_amount', '<=', $data);
            });

            // Filter by payment_type
            OrbitInput::get('payment_type', function($data) use ($luckydraws)
            {
                $luckydraws->whereIn('lucky_draw_receipts.receipt_payment_type', $data);
            });

            // Filter by receipt_group
            OrbitInput::get('receipt_group', function($data) use ($luckydraws)
            {
                $luckydraws->whereIn('lucky_draw_receipts.receipt_group', $data);
            });

            // Filter by lucky_draw_number
            OrbitInput::get('lucky_draw_number', function($data) use ($luckydraws)
            {
                $luckydraws->whereIn('lucky_draw_numbers.lucky_draw_number_code', $data);
            });

            // Filter by lucky_draw_id
            OrbitInput::get('lucky_draw_id', function($data) use ($luckydraws)
            {
                $luckydraws->whereIn('lucky_draw_numbers.lucky_draw_id', $data);
            });

            // Filter by external_receipt_id
            OrbitInput::get('external_receipt_id', function($data) use ($luckydraws)
            {
                $luckydraws->whereIn('lucky_draw_receipts.external_receipt_id', $data);
            });

            // Filter by status
            OrbitInput::get('status', function ($data) use ($luckydraws) {
                $luckydraws->whereIn('lucky_draw_receipts.status', $data);
            });

            // Filter by created_at date
            OrbitInput::get('created_at_after', function($data) use ($luckydraws) {
                $luckydraws->where('lucky_draw_receipts.created_at', '>=', $data);
            });

            // Filter by created_at date
            OrbitInput::get('created_at_before', function($data) use ($luckydraws) {
                $luckydraws->where('lucky_draw_receipts.created_at', '<=', $data);
            });

            // Filter by updated_at date
            OrbitInput::get('updated_at_after', function($data) use ($luckydraws) {
                $luckydraws->where('lucky_draw_receipts.updated_at', '>=', $data);
            });

            // Filter by updated_at date
            OrbitInput::get('updated_at_before', function($data) use ($luckydraws) {
                $luckydraws->where('lucky_draw_receipts.updated_at', '<=', $data);
            });

            // Add new relation based on request
            OrbitInput::get('with', function ($with) use ($luckydraws) {
                $with = (array) $with;

                foreach ($with as $relation) {
                    if ($relation === 'mall') {
                        $luckydraws->with('mall');
                    } elseif ($relation === 'user') {
                        $luckydraws->with('user');
                    } elseif ($relation === 'receipt_retailer') {
                        $luckydraws->with('receiptRetailer');
                    } elseif ($relation === 'numbers') {
                        $luckydraws->with('numbers');
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
            $sortBy = 'lucky_draw_receipts.receipt_number';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'registered_date'          => 'lucky_draw_receipts.created_at',
                    'lucky_draw_number'        => 'lucky_draw_numbers.lucky_draw_number_code',
                    'lucky_draw_id'            => 'lucky_draw_numbers.lucky_draw_id',
                    'user_id'                  => 'lucky_draw_receipts.user_id',
                    'status'                   => 'lucky_draw_receipts.status',
                );

                if (array_key_exists($_sortBy, $sortByMapping)) {
                    $sortBy = $sortByMapping[$_sortBy];
                }
            });

            if ($sortBy !== 'lucky_draw_receipts.status') {
                $luckydraws->orderBy('lucky_draw_receipts.status', 'asc');
            }

            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
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
                $this->response->message = Lang::get('statuses.orbit.nodata.lucky_draw_number_receipt');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.luckydrawnumberreceipt.getsearchluckydrawnumberreceipt.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.luckydrawnumberreceipt.getsearchluckydrawnumberreceipt.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.luckydrawnumberreceipt.getsearchluckydrawnumberreceipt.query.error', array($this, $e));

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
            Event::fire('orbit.luckydrawnumberreceipt.getsearchluckydrawnumberreceipt.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.luckydrawnumberreceipt.getsearchluckydrawnumberreceipt.before.render', array($this, &$output));

        return $output;
    }

    protected function registerCustomValidation()
    {

        // Check the existance of mall id
        Validator::extend('orbit.empty.mall', function ($attribute, $value, $parameters) {
            $mall = Retailer::excludeDeleted()
                            ->isMall()
                            ->where('merchant_id', $value)
                            ->first();

            if (empty($mall)) {
                return FALSE;
            }

            App::instance('orbit.empty.mall', $mall);

            return TRUE;
        });

    }
}
