<?php
/**
 * An API controller for managing Promo Code.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Helper\EloquentRecordCounter as RecordCounter;
use Carbon\Carbon as Carbon;

class PromoCodeAPIController extends ControllerAPI
{
    /**
     * Flag to return the query builder.
     *
     * @var Builder
     */
    protected $returnBuilder = FALSE;

    protected $promoCodeViewRoles = ['super admin', 'mall admin', 'mall owner', 'campaign owner', 'campaign employee', 'campaign admin'];
    protected $promoCodeModifiyRoles = ['super admin', 'mall admin', 'mall owner', 'campaign owner', 'campaign employee'];

    /**
     * POST - Create New Promo Code
     *
     * @author kadek <kadek@dominopos.com>
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postNewPromoCode()
    {
        $user = NULL;
        $newnews = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.promocode.postnewpromocode.before.auth', array($this));

            $this->checkAuth();

            Event::fire('orbit.promocode.postnewpromocode.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.promocode.postnewpromocode.before.authz', array($this, $user));

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->promoCodeModifiyRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.promocode.postnewpromocode.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $discount_title = OrbitInput::post('discount_title');
            $discount_code = OrbitInput::post('discount_code');
            $value_in_percent = OrbitInput::post('value_in_percent');
            $start_date = OrbitInput::post('start_date');
            $end_date = OrbitInput::post('end_date');
            $max_per_transaction = OrbitInput::post('max_per_transaction');
            $max_per_user = OrbitInput::post('max_per_user');
            $max_redemption = OrbitInput::post('max_redemption');
            $type = OrbitInput::post('type', 'coupon');
            $status = OrbitInput::post('status', 'inactive');

            $validator_value = [
                'discount_title'      => $discount_title,
                'discount_code'       => $discount_code,
                'value_in_percent'    => $value_in_percent,
                'start_date'          => $start_date,
                'end_date'            => $end_date,
                'max_per_transaction' => $max_per_transaction,
                'max_per_user'        => $max_per_user,
                'max_redemption'      => $max_redemption,
                'type'                => $type,
                'status'              => $status,
            ];
            $validator_validation = [
                'discount_title'      => 'required',
                'discount_code'       => 'required|orbit.exist.promocode',
                'value_in_percent'    => 'required',
                'start_date'          => 'required|date|orbit.empty.hour_format',
                'end_date'            => 'required|date|orbit.empty.hour_format',
                'max_per_transaction' => 'required',
                'max_per_user'        => 'required',
                'max_redemption'      => 'required',
                'type'                => 'in:coupon,pulsa',
                'status'              => 'in:active,inactive',
            ];
            $validator_message = [
                'discount_title.required'      => 'Promo Name required',
                'discount_code.required'       => 'Promo Code required',
                'orbit.exist.promocode'        => 'Promo Code already used',
                'value_in_percent.required'    => 'Promo Value required',
                'max_per_transaction.required' => 'Maximum Redeemed Code Per Transaction required',
                'max_per_user.required'        => 'Number of Use Per User required',
                'max_redemption.required'      => 'Maximum Redeemed Code Quantity',
            ];

            $validator = Validator::make(
                $validator_value,
                $validator_validation,
                $validator_message
            );

            Event::fire('orbit.promocode.postnewpromocode.before.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            Event::fire('orbit.promocode.postnewpromocode.after.validation', array($this, $validator));

            $newPromoCode = new Discount();
            $newPromoCode->discount_title = $discount_title;
            $newPromoCode->discount_code = $discount_code;
            $newPromoCode->value_in_percent = $value_in_percent;
            $newPromoCode->start_date = $start_date;
            $newPromoCode->end_date = $end_date;
            $newPromoCode->max_per_transaction = $max_per_transaction;
            $newPromoCode->max_per_user = $max_per_user;
            $newPromoCode->max_redemption = $max_redemption;
            $newPromoCode->type = $type;
            $newPromoCode->status = $status;
            $newPromoCode->save();

            Event::fire('orbit.promocode.postnewpromocode.after.save', array($this, $newPromoCode));

            $this->response->data = $newPromoCode;

            // Commit the changes
            $this->commit();

            Event::fire('orbit.promocode.postnewpromocode.after.commit', array($this, $newPromoCode));
        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
            // Rollback the changes
            $this->rollBack();
        } catch (QueryException $e) {
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
        } catch (Exception $e) {
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = $e->getLine();
            // Rollback the changes
            $this->rollBack();
        }
        return $this->render($httpCode);
    }

    /**
     * POST - Update Promo Code
     *
     * @author kadek <kadek@dominopos.com>
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postUpdatePromoCode()
    {
        $user = NULL;
        $updatedPromoCode = NULL;
        try {
            $httpCode=200;

            Event::fire('orbit.promocode.postupdatepromocode.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.promocode.postupdatepromocode.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.promocode.postupdatepromocode.before.authz', array($this, $user));

            $role = $user->role;
            $validRoles = $this->promoCodeModifiyRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.promocode.postupdatepromocode.after.authz', array($this, $user));

            $discount_id = OrbitInput::post('discount_id');
            $discount_code = OrbitInput::post('discount_code');

            $this->registerCustomValidation();

            $validator_value = [
                'discount_id'      => $discount_id,
                'discount_code'    => $discount_code,
            ];
            $validator_validation = [
                'discount_id'       => 'required|orbit.exist.discount_id',
                'discount_code'     => 'orbit.exist.promocode_but_me:'.$discount_id,
            ];
            $validator_message = [
                'discount_id.required'           => 'Discount id required',
                'orbit.exist.promocode_but_me'   => 'Promo Code already used',
            ];

            $validator = Validator::make(
                $validator_value,
                $validator_validation,
                $validator_message
            );

            Event::fire('orbit.promocode.postupdatepromocode.before.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.promocode.postupdatepromocode.after.validation', array($this, $validator));

            $prefix = DB::getTablePrefix();

            $updatedPromoCode = Discount::where('discount_id', $discount_id)->first();

            // save News
            OrbitInput::post('discount_title', function($discount_title) use ($updatedPromoCode) {
                $updatedPromoCode->discount_title = $discount_title;
            });

            OrbitInput::post('discount_code', function($discount_code) use ($updatedPromoCode) {
                $updatedPromoCode->discount_code = $discount_code;
            });

            OrbitInput::post('value_in_percent', function($value_in_percent) use ($updatedPromoCode) {
                $updatedPromoCode->value_in_percent = $value_in_percent;
            });

            OrbitInput::post('start_date', function($start_date) use ($updatedPromoCode) {
                $updatedPromoCode->start_date = $start_date;
            });

            OrbitInput::post('end_date', function($end_date) use ($updatedPromoCode) {
                $updatedPromoCode->end_date = $end_date;
            });

            OrbitInput::post('max_per_transaction', function($max_per_transaction) use ($updatedPromoCode) {
                $updatedPromoCode->max_per_transaction = $max_per_transaction;
            });

            OrbitInput::post('max_per_user', function($max_per_user) use ($updatedPromoCode) {
                $updatedPromoCode->max_per_user = $max_per_user;
            });

            OrbitInput::post('max_redemption', function($max_redemption) use ($updatedPromoCode) {
                $updatedPromoCode->max_redemption = $max_redemption;
            });

            OrbitInput::post('type', function($type) use ($updatedPromoCode) {
                $updatedPromoCode->type = $type;
            });

            OrbitInput::post('status', function($status) use ($updatedPromoCode) {
                $updatedPromoCode->status = $status;
            });

            $updatedPromoCode->touch();
            $updatedPromoCode->save();

            Event::fire('orbit.promocode.postupdatepromocode.after.save', array($this, $updatedPromoCode));
            $this->response->data = $updatedPromoCode;

            // Commit the changes
            $this->commit();

            Event::fire('orbit.promocode.postupdatepromocode.after.commit', array($this, $updatedPromoCode));
        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
            // Rollback the changes
            $this->rollBack();
        } catch (QueryException $e) {
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
        } catch (Exception $e) {
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = [$e->getMessage(), $e->getFile(), $e->getLine()];
            // Rollback the changes
            $this->rollBack();
        }

        return $this->render($httpCode);
    }

    /**
     * GET - Search Promo Code
     *
     * @author kadek <kadek@dominopos.com>
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchPromoCode()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.promocode.getsearchpromocode.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.promocode.getsearchpromocode.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.promocode.getsearchpromocode.before.authz', array($this, $user));
            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->promoCodeViewRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.promocode.getsearchpromocode.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sortby');

            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                ),
                array(
                    'sort_by' => 'in:discount_title,discount_code,value_in_percent,start_date,end_date,type,status,created_at,updated_at',
                ),
                array(
                    'in' => 'invalid sortby',
                )
            );

            Event::fire('orbit.promocode.getsearchpromocode.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.promocode.getsearchpromocode.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.promocode.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.promocode.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            $prefix = DB::getTablePrefix();

            // Builder object
            $promoCode = Discount::select('discount_id', 'discount_title', 'start_date', 'end_date', 'type', 'status', 'created_at', 'updated_at');

            // Filter news by Ids
            OrbitInput::get('discount_id', function($discount_id) use ($promoCode)
            {
                $promoCode->where('discounts.discount_id', (array)$discount_id);
            });

            // Filter news by news name
            OrbitInput::get('discount_title', function($discount_title) use ($promoCode)
            {
                $promoCode->where('discounts.discount_title', '=', $discount_title);
            });

            // Filter news by matching news name pattern
            OrbitInput::get('discount_title_like', function($discount_title_like) use ($promoCode)
            {
                $promoCode->where('discounts.discount_title', 'like', "%$discount_title_like%");
            });

            // Filter news by keywords for advert link to
            OrbitInput::get('discount_code', function($discount_code) use ($promoCode)
            {
                $promoCode->where('discounts.discount_code', '=', $discount_code);
            });

            // Filter news by keywords for advert link to
            OrbitInput::get('discount_code_like', function($discount_code_like) use ($promoCode)
            {
                $promoCode->where('discounts.discount_code', 'like', "$discount_code_like%");
            });

            // Filter news by date
            OrbitInput::get('end_date', function($end_date) use ($promoCode)
            {
                $promoCode->where('discounts.start_date', '<=', $end_date);
            });

            // Filter news by date
            OrbitInput::get('begin_date', function($begin_date) use ($promoCode)
            {
                $promoCode->where('discounts.end_date', '>=', $begin_date);
            });

            // Filter news by sticky order
            OrbitInput::get('status', function ($status) use ($promoCode) {
                $promoCode->where('discounts.status', $status);
            });

            // Filter news by link object type
            OrbitInput::get('type', function ($type) use ($promoCode) {
                $promoCode->where('discounts.type', $type);
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_promoCode = clone $promoCode;

            if (! $this->returnBuilder) {
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
                $promoCode->take($take);

                $skip = 0;
                OrbitInput::get('skip', function($_skip) use (&$skip, $promoCode)
                {
                    if ($_skip < 0) {
                        $_skip = 0;
                    }

                    $skip = $_skip;
                });
                $promoCode->skip($skip);
            }

            // Default sort by
            $sortBy = 'discounts.updated_at';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'discount_title'  => 'discounts.discount_title',
                    'discount_code'   => 'discounts.discount_code',
                    'value_in_percent'=> 'discounts.value_in_percent',
                    'start_date'      => 'discounts.start_date',
                    'end_date'        => 'discounts.end_date',
                    'type'            => 'discounts.type',
                    'status'          => 'discounts.status',
                    'created_at'      => 'discounts.created_at',
                    'updated_at'      => 'discounts.updated_at'
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });

            $promoCode->orderBy($sortBy, $sortMode);

            $totalNews = RecordCounter::create($_promoCode)->count();
            $listOfNews = $promoCode->get();

            $data = new stdclass();
            $data->total_records = $totalNews;
            $data->returned_records = count($listOfNews);
            $data->records = $listOfNews;

            if ($totalNews === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.news');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.promocode.getsearchpromocode.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.promocode.getsearchpromocode.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.promocode.getsearchpromocode.query.error', array($this, $e));

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
            Event::fire('orbit.promocode.getsearchpromocode.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.promocode.getsearchpromocode.before.render', array($this, &$output));

        return $output;
    }

    public function getDetailPromoCode()
    {
        $user = NULL;
        try {
            $httpCode = 200;

            $this->checkAuth();

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->promoCodeViewRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            $discount_id = OrbitInput::get('discount_id');

            $this->registerCustomValidation();

            $validator = Validator::make(
                array(
                    'discount_id' => $discount_id,
                ),
                array(
                    'discount_id' => 'required|orbit.exist.discount_id',
                ),
                array(
                    'orbit.exist.discount_id' => 'Discount id not found',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $promoCode = Discount::where('discount_id', $discount_id)->firstOrFail();

            $this->response->data = $promoCode;

        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (QueryException $e) {
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
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = $e->getLine();
        }

        return $this->render($httpCode);
    }

    protected function registerCustomValidation()
    {
        // Validate the time format for over 23 hour
        Validator::extend('orbit.empty.hour_format', function ($attribute, $value, $parameters) {
            // explode the format Y-m-d H:i:s
            $dateTimeExplode = explode(' ', $value);
            // explode the format H:i:s
            $timeExplode = explode(':', $dateTimeExplode[1]);
            // get the Hour format
            if($timeExplode[0] > 23){
                return false;
            }

            return true;
        });

        // Check the existance of news id for update with permission check
        Validator::extend('orbit.exist.discount_id', function ($attribute, $value, $parameters) {
            $exist = Discount::where('discount_id', $value)->first();

            if (empty($exist)) {
                return false;
            }

            return true;
        });

        Validator::extend('orbit.exist.promocode', function ($attribute, $value, $parameters) {
            $exist = Discount::where('discount_code', $value)->first();

            if (!empty($exist)) {
                return false;
            }

            return true;
        });

        Validator::extend('orbit.exist.promocode_but_me', function ($attribute, $value, $parameters) {
            $discount_id = trim($parameters[0]);
            $discount = Discount::where('discount_code', $value)
                                ->where('discount_id', '!=', $discount_id)
                                ->first();

            if (!empty($discount)) {
                return false;
            }

            return true;
        });
    }

    protected function getTimezone($current_mall)
    {
        $timezone = Mall::leftJoin('timezones','timezones.timezone_id','=','merchants.timezone_id')
            ->where('merchants.merchant_id','=', $current_mall)
            ->first();

        return $timezone->timezone_name;
    }

    protected function getTimezoneOffset($timezone)
    {
        $dt = new DateTime('now', new DateTimeZone($timezone));

        return $dt->format('P');
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }

    public function setReturnBuilder($bool)
    {
        $this->returnBuilder = $bool;

        return $this;
    }
}
