<?php
/**
 * An API controller for managing Membership Number.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Helper\EloquentRecordCounter as RecordCounter;

class MembershipNumberAPIController extends ControllerAPI
{
    /**
     * GET - Search Membership Number
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string   `with`                  (optional) - Valid value: mall
     * @param string   `sortby`                (optional) - Sort by
     * @param string   `sortmode`              (optional) - Sort mode. Valid value: asc, desc
     * @param integer  `take`                  (optional) - Limit
     * @param integer  `skip`                  (optional) - Limit offset
     * @param string   `membership_id`         (optional) - Membership ID
     * @param string   `mall_id`               (optional) - Mall ID
     * @param string   `membership_number`       (optional) - Membership name
     * @param string   `membership_number_like`  (optional) - Membership name like
     * @param string   `description`           (optional) - Description
     * @param string   `description_like`      (optional) - Description like
     * @param string   `status`                (optional) - Status. Valid value: active, inactive, deleted
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchMembershipNumber()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.membershipnumber.getsearchmembershipnumber.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.membershipnumber.getsearchmembershipnumber.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.membershipnumber.getsearchmembershipnumber.before.authz', array($this, $user));

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = ['super admin', 'mall admin', 'mall owner', 'mall customer service', 'consumer'];
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.membershipnumber.getsearchmembershipnumber.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $mall_id = OrbitInput::get('mall_id');
            $sort_by = OrbitInput::get('sortby');

            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                ),
                array(
                    'sort_by' => 'in:membership_name,membership_number,join_date,status,merchant_name',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.membership_number_sortby'),
                )
            );

            Event::fire('orbit.membershipnumber.getsearchmembershipnumber.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.membershipnumber.getsearchmembershipnumber.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.membership_number.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.membership_number.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            $prefix = DB::getTablePrefix();
            // Builder membership
            $record = MembershipNumber::select('membership_numbers.membership_number_id', 'membership_numbers.membership_id', 'membership_numbers.user_id', 'membership_numbers.membership_number', 'membership_numbers.expired_date', 'membership_numbers.join_date', 'membership_numbers.issuer_merchant_id', 'memberships.merchant_id', 'merchants.name AS merchant_name', 'memberships.membership_name', DB::raw("CASE WHEN {$prefix}settings.setting_value = 'true' THEN {$prefix}membership_numbers.status ELSE 'inactive' END AS status"))
                                      ->where('membership_numbers.status', '=', 'active')
                                      ->join('memberships', 'memberships.membership_id', '=', 'membership_numbers.membership_id')
                                      ->join('merchants', 'merchants.merchant_id', '=', 'memberships.merchant_id')
                                      ->excludeDeleted('memberships')
                                      ->leftJoin('settings', function($q) {
                                              $q->on('memberships.merchant_id', '=', 'settings.object_id')
                                                ->where('settings.setting_name', '=', 'enable_membership_card')
                                                ->where('settings.object_type', '=', 'merchant')
                                                ;
                                      });

            // get user mall_ids
            $listOfMallIds = $user->getUserMallIds($mall_id);

            // filter mall based on user role
            if (empty($listOfMallIds)) { // invalid mall id
                $record->whereRaw('0');
            } elseif ($listOfMallIds[0] === 1) { // if super admin
                // show all users
            } else { // valid mall id
                $record->whereIn('memberships.merchant_id', $listOfMallIds);
            }

            // Filter by user_ids
            if ($user->isConsumer()) {
                $record->where('membership_numbers.user_id', $user->user_id);
            } else {
                OrbitInput::get('user_id', function ($arg) use ($record)
                {
                    $record->whereIn('membership_numbers.user_id', (array)$arg);
                });
            }

            // Filter membership by ids
            OrbitInput::get('membership_id', function ($arg) use ($record)
            {
                $record->whereIn('membership_numbers.membership_id', (array)$arg);
            });

            // Filter membership by membership name
            OrbitInput::get('membership_number', function ($arg) use ($record)
            {
                $record->whereIn('membership_numbers.membership_number', (array)$arg);
            });

            // Filter membership by matching membership name pattern
            OrbitInput::get('membership_number_like', function ($arg) use ($record)
            {
                $record->where('membership_numbers.membership_number', 'like', "%$arg%");
            });

            // Filter membership by status
            OrbitInput::get('status', function ($arg) use ($record, $prefix) {
                $record->whereIn(DB::raw("CASE WHEN {$prefix}settings.setting_value = 'true' THEN 'active' ELSE 'inactive' END"), (array)$arg);
            });

            // Add new relation based on request
            OrbitInput::get('with', function ($with) use ($record) {
                $with = (array) $with;

                foreach ($with as $relation) {
                    if ($relation === 'mall') {
                        $record->with('membership.mall');
                    } elseif ($relation === 'membership') {
                        $record->with('membership.media');
                    } elseif ($relation === 'user') {
                        $record->with('user');
                    }
                }
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_record = clone $record;

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
            $record->take($take);

            $skip = 0;
            OrbitInput::get('skip', function($_skip) use (&$skip, $record)
            {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $record->skip($skip);

            // Default sort by
            $sortBy = 'membership_number';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'join_date'         => 'membership_numbers.join_date',
                    'membership_name'   => 'membership_name',
                    'membership_number' => 'membership_numbers.membership_number',
                    'status'            => 'status',
                    'merchant_name'     => 'merchant_name'
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });
            $record->orderBy($sortBy, $sortMode);

            $totalMembership = RecordCounter::create($_record)->count();
            $listOfMembership = $record->get();

            $data = new stdclass();
            $data->total_records = $totalMembership;
            $data->returned_records = count($listOfMembership);
            $data->records = $listOfMembership;

            if ($totalMembership === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.membership');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.membershipnumber.getsearchmembershipnumber.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.membershipnumber.getsearchmembershipnumber.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.membershipnumber.getsearchmembershipnumber.query.error', array($this, $e));

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
            Event::fire('orbit.membershipnumber.getsearchmembershipnumber.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.membershipnumber.getsearchmembershipnumber.before.render', array($this, &$output));

        return $output;
    }

    protected function registerCustomValidation()
    {
        // Check the existance of membership id
        Validator::extend('orbit.empty.membership', function ($attribute, $value, $parameters) {
            $membership = Membership::excludeDeleted()
                                    ->where('membership_id', $value)
                                    ->first();

            if (empty($membership)) {
                return FALSE;
            }

            App::instance('orbit.empty.membership', $membership);

            return TRUE;
        });

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
    }

}
