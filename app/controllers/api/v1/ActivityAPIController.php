<?php
/**
 * An API controller for managing history which happens on Orbit.
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

class ActivityAPIController extends ControllerAPI
{
    /**
     * GET - List of Activities history
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param array     `activity_types`        (optional) - Activity Type
     * @param array     `activity_names`        (optional) - Activity name
     * @param array     `activity_name_longs`   (optional) - Activity name (friendly name)
     * @param array     `user_ids`              (optional) - IDs of the user
     * @param array     `user_emails`           (optional) - Emails of the user
     * @param array     `groups`                (optional) - Name of the group, e.g: 'portal', 'mobile-ci', 'pos'
     * @param array     `role_ids`              (optional) - IDs of user role
     * @param array     `object_ids`            (optional) - IDs of the object, could be the IDs of promotion, coupon, etc
     * @param array     `object_names`          (optional) - Name of the object
     * @param array     `product_names`         (optional) - Name of the product
     * @param array     `coupon_names`          (optional) - Name of the coupon
     * @param array     `promotion_names`       (optional) - Name of the promotion
     * @param array     `event_names`           (optional) - Name of the event
     * @param array     `retailer_ids`          (optional) - IDs of retailer
     * @param array     `merchant_ids`          (optional) - IDs of merchant
     * @param array     `ip_address`            (optional) - List of IP Address
     * @param string    `ip_address_like`       (optional) - Pattern of the IP address. e.g: '192.168' or '220.'
     * @param string    `user_agent_like`       (optional) - User agent like
     * @param string    `fullname_like`         (optional) - Full name like
     * @param array     `staff_ids`             (optional) - User IDs of Cashier
     * @param array     `response_statuses`     (optional) - Response status, e.g: 'OK' or 'Failed'
     * @param string    `sort_by`               (optional) - column order by, e.g: 'id', 'created', 'activity_name', 'ip_address'
     * @param string    `sort_mode`             (optional) - asc or desc
     * @param integer   `take`                  (optional) - limit
     * @param integer   `skip`                  (optional) - limit offset
     * @param date      `start_date`            (optional) - Filter by start date, format 'Y-m-d'
     * @param date      `end_date`              (optional) - Filter by end date, format 'Y-m-d'
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchActivity()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.activity.getactivity.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.activity.getactivity.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.activity.getactivity.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('view_activity')) {
                Event::fire('orbit.activity.getactivity.authz.notallowed', array($this, $user));

                $errorMessage = Lang::get('validation.orbit.actionlist.view_activity');
                $message = Lang::get('validation.orbit.access.view_activity', array('action' => $errorMessage));

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

            Event::fire('orbit.activity.getactivity.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sortby');
            $start_date = OrbitInput::get('start_date');
            $end_date = OrbitInput::get('end_date');

            $tomorrow = date('Y-m-d H:i:s', strtotime('tomorrow'));
            $validator = Validator::make(
                array(
                    'sort_by'       => $sort_by,
                    'merchant_ids'  => OrbitInput::get('merchant_ids'),
                    'start_date'    => $start_date,
                    'end_date'      => $end_date
                ),
                array(
                    'sort_by'       => 'in:id,ip_address,created,registered_at,email,full_name,object_name,product_name,coupon_name,promotion_name,news_name,promotion_news_name,event_name,action_name,action_name_long,activity_type,gender,staff_name,module_name,retailer_name',
                    'merchant_ids'  => 'orbit.check.merchants',
                    'start_date'    => 'date_format:Y-m-d H:i:s|before:' . $tomorrow,
                    'end_date'      => 'date_format:Y-m-d H:i:s|before:' . $tomorrow,
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.activity_sortby'),
                )
            );

            Event::fire('orbit.activity.getactivity.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.activity.getactivity.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.activity.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.activity.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            // Builder object
            $with = array('user', 'retailer', 'promotion', 'coupon', 'product', 'productVariant', 'children', 'staff');
            // Include other relationship
            OrbitInput::get('with', function($_with) use (&$with) {
                $with = array_merge($with, $_with);
            });
            $tablePrefix = DB::getTablePrefix();
            $activities = Activity::with($with)->select('activities.activity_id',
                                                    'activities.activity_name',
                                                    'activities.activity_name_long',
                                                    'activities.activity_type',
                                                    'activities.module_name',
                                                    'activities.user_id',
                                                    'activities.user_email',
                                                    'activities.full_name',
                                                    'activities.group',
                                                    'activities.role',
                                                    'activities.role_id',
                                                    'activities.object_id',
                                                    'activities.object_name',
                                                    'activities.product_id',
                                                    'activities.product_name',
                                                    'activities.coupon_id',
                                                    'activities.coupon_name',
                                                    'activities.promotion_id',
                                                    'activities.promotion_name',
                                                    'activities.event_id',
                                                    'activities.event_name',
                                                    'activities.location_id',
                                                    'activities.location_name',
                                                    'activities.ip_address',
                                                    'activities.user_agent',
                                                    'activities.staff_id',
                                                    'activities.staff_name',
                                                    'activities.metadata_user',
                                                    'activities.metadata_object',
                                                    'activities.metadata_location',
                                                    'activities.metadata_staff',
                                                    'activities.notes',
                                                    'activities.http_method',
                                                    'activities.request_uri',
                                                    'activities.post_data',
                                                    'activities.status',
                                                    'activities.parent_id',
                                                    'activities.response_status',
                                                    'activities.created_at',
                                                    'activities.updated_at',
                                                    DB::Raw("DATE_FORMAT({$tablePrefix}activities.created_at, '%d-%m-%Y %H:%i:%s') as created_at_reverse"),
                                                    'user_details.gender as gender')
                                            ->leftJoin('user_details', 'user_details.user_id', '=', 'activities.user_id')
                                            ->joinRetailer()
                                            ->joinNews()
                                            ->joinPromotionNews()
                                            ->groupBy('activities.activity_id');

            // Filter by ids
            OrbitInput::get('id', function($activityIds) use ($activities) {
                $activities->whereIn('activities.activity_id', $activityIds);
            });

            // Filter by activity type
            OrbitInput::get('activity_types', function($types) use ($activities) {
                $activities->whereIn('activities.activity_type', $types);
            });

            // Filter by activity name
            if (! empty($_GET['activity_names'])) {
                OrbitInput::get('activity_names', function($names) use ($activities) {
                    $activities->whereIn('activities.activity_name', $names);
                });
            } else {
                $activities->whereNotNull('activities.activity_name');
            }

            // Filter by activity name long
            OrbitInput::get('activity_name_longs', function($nameLongs) use ($activities) {
                $activities->whereIn('activities.activity_name_long', $nameLongs);
            });

            // Filter by matching activity_name_long pattern
            OrbitInput::get('activity_name_long_like', function($name) use ($activities) {
                $activities->where('activities.activity_name_long', 'like', "%$name%");
            });

            // Filter by module_name
            OrbitInput::get('module_names', function($names) use ($activities) {
                $activities->whereIn('activities.module_name', $names);
            });

            // Filter by matching module_name pattern
            OrbitInput::get('module_name_like', function($name) use ($activities) {
                $activities->where('activities.module_name', 'like', "%{$name}%");
            });

            // Filter by merchant ids
            OrbitInput::get('merchant_ids', function($merchantIds) use ($activities) {
                $activities->merchantIds($merchantIds);
            });

            // Filter by retailer ids
            OrbitInput::get('retailer_ids', function($retailerIds) use ($activities) {
                $activities->whereIn('activities.location_id', $retailerIds);
            });

            // Filter by user emails
            OrbitInput::get('user_emails', function($emails) use ($activities) {
                $activities->whereIn('activities.user_email', $emails);
            });

            // Filter by matching user_email pattern
            OrbitInput::get('user_email_like', function($userEmail) use ($activities) {
                $activities->where('activities.user_email', 'like', "%$userEmail%");
            });

            // Filter by gender
            OrbitInput::get('genders', function($genders) use ($activities) {
                // dd($genders[0]);
                $activities->whereIn('activities.gender', $genders);
            });

            // Filter by groups
            if (! empty($_GET['groups'])) {
                OrbitInput::get('groups', function($groups) use ($activities) {
                    $activities->whereIn('activities.group', $groups);
                });
            } else {
                $activities->whereIn('activities.group', ['mobile-ci', 'pos']);
            }

            // Filter by matching group pattern
            OrbitInput::get('group_like', function($group) use ($activities) {
                $activities->where('activities.group', 'like', "%$group%");
            });

            // Filter by role_ids
            OrbitInput::get('role_ids', function($roleIds) use ($activities) {
                $activities->whereIn('activities.role_id', $roleIds);
            });

            // Filter by object ids
            OrbitInput::get('object_ids', function($objectIds) use ($activities) {
                $activities->whereIn('activities.object_id', $objectIds);
            });

            // Filter by object names
            OrbitInput::get('object_names', function($names) use ($activities) {
                $activities->whereIn('activities.object_name', $names);
            });

            // Filter by matching object_name pattern
            OrbitInput::get('object_name_like', function($name) use ($activities) {
                $activities->where('activities.object_name', 'like', "%{$name}%");
            });

            OrbitInput::get('product_names', function($names) use ($activities) {
                $activities->whereIn('activities.product_name', $names);
            });

            // Filter by matching product_name pattern
            OrbitInput::get('product_name_like', function($name) use ($activities) {
                $activities->where('activities.product_name', 'like', "%$name%");
            });

            OrbitInput::get('promotion_names', function($names) use ($activities) {
                $activities->whereIn('activities.promotion_name', $names);
            });

            // Filter by matching promotion_name pattern
            OrbitInput::get('promotion_name_like', function($name) use ($activities) {
                $activities->where('activities.promotion_name', 'like', "%$name%");
            });

            OrbitInput::get('retailer_names', function($names) use ($activities) {
                $activities->whereIn('merchants.name', $names);
            });

            // Filter by matching retailer_name pattern
            OrbitInput::get('retailer_name_like', function($name) use ($activities) {
                $activities->where('merchants.name', 'like', "%$name%");
            });

            OrbitInput::get('news_names', function($names) use ($activities) {
                $activities->whereIn('news.news_name', $names);
            });

            // Filter by matching news_name pattern
            OrbitInput::get('news_name_like', function($name) use ($activities) {
                $activities->where('news.news_name', 'like', "%$name%");
            });

            OrbitInput::get('promotion_news_names', function($names) use ($activities) {
                $activities->whereIn(DB::raw('promotion_news.news_name'), $names);
            });

            // Filter by matching promotion_news_name pattern
            OrbitInput::get('promotion_news_name_like', function($name) use ($activities) {
                $activities->where(DB::raw('promotion_news.news_name'), 'like', "%$name%");
            });

            OrbitInput::get('coupon_names', function($names) use ($activities) {
                $activities->whereIn('activities.coupon_name', $names);
            });

            // Filter by matching coupon_name pattern
            OrbitInput::get('coupon_name_like', function($name) use ($activities) {
                $activities->where('activities.coupon_name', 'like', "%$name%");
            });

            OrbitInput::get('event_names', function($names) use ($activities) {
                $activities->whereIn('activities.event_name', $names);
            });

            // Filter by matching event_name pattern
            OrbitInput::get('event_name_like', function($name) use ($activities) {
                $activities->where('activities.event_name', 'like', "%$name%");
            });

            // Filter by staff Ids
            OrbitInput::get('staff_ids', function($staff) use ($activities) {
                $activities->whereIn('activities.staff_id', $staff);
            });

            // Filter by matching staff_name pattern
            OrbitInput::get('staff_name_like', function($name) use ($activities) {
                $activities->where('activities.staff_name', 'like', "%$name%");
            });

            // Filter by status
            OrbitInput::get('status', function ($status) use ($activities) {
                $activities->whereIn('activities.status', $status);
            });

            // Filter by status
            OrbitInput::get('ip_address_like', function ($ip) use ($activities) {
                $activities->where('activities.ip_address', 'like', "%{$ip}%");
            });

            OrbitInput::get('user_agent_like', function ($ua) use ($activities) {
                $activities->where('activities.user_agent', 'like', "%{$ua}%");
            });

            OrbitInput::get('full_name_like', function ($name) use ($activities) {
                $activities->where('activities.full_name', 'like', "%{$name}%");
            });

            if (! empty($_GET['response_statuses'])) {
                // Filter by response status
                OrbitInput::get('response_statuses', function ($status) use ($activities) {
                    $activities->whereIn('activities.response_status', $status);
                });
            } else {
                $activities->whereIn('activities.response_status', ['OK']);
            }

            // Filter by start date
            OrbitInput::get('start_date', function($_start) use ($activities) {
                $activities->where('activities.created_at', '>=', $_start);
            });

            // Filter by end date
            OrbitInput::get('end_date', function($_end) use ($activities, $user) {
                $activities->where('activities.created_at', '<=', $_end);
            });

            // Only shows activities which belongs to this merchant
            if ($user->isSuperAdmin() !== TRUE) {
                $locationIds = $user->getMyRetailerIds();

                if (empty($locationIds)) {
                    // Just to make sure it query the wrong one.
                    $locationIds = [-1];
                }

                // Filter by user location id
                //$activities->whereIn('activities.location_id', $locationIds);
            } else {
                // Filter by user ids, Super Admin could filter all
                OrbitInput::get('user_ids', function($userIds) use ($activities) {
                    $activities->whereIn('activities.user_id', $userIds);
                });

                // Filter by user location id
                OrbitInput::get('location_ids', function($locationIds) use ($activities) {
                    $activities->whereIn('activities.location_id', $locationIds);
                });
            }

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_activities = clone $activities;

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
            $activities->take($take);

            $skip = 0;
            OrbitInput::get('skip', function ($_skip) use (&$skip, $activities) {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $activities->skip($skip);

            // Default sort by
            $sortBy = 'activities.activity_id';
            // Default sort mode
            $sortMode = 'desc';

            OrbitInput::get('sortby', function ($_sortBy) use (&$sortBy) {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'id'                => 'activities.activity_id',
                    'ip_address'        => 'activities.ip_address',
                    'created'           => 'activities.created_at',
                    'registered_at'     => 'activities.created_at',
                    'email'             => 'activities.user_email',
                    'full_name'         => 'activities.full_name',
                    'object_name'       => 'activities.object_name',
                    'product_name'      => 'activities.product_name',
                    'coupon_name'       => 'activities.coupon_name',
                    'promotion_name'    => 'activities.promotion_name',
                    'news_name'         => 'news.news_name',
                    'promotion_news_name' => DB::raw('promotion_news.news_name'),
                    'event_name'        => 'activities.event_name',
                    'action_name'       => 'activities.activity_name',
                    'action_name_long'  => 'activities.activity_name_long',
                    'activity_type'     => 'activities.activity_type',
                    'staff_name'        => 'activities.staff_name',
                    'gender'            => 'user_details.gender',
                    'module_name'       => 'activities.module_name',
                    'retailer_name'     => 'retailer_name',
                );

                if (array_key_exists($_sortBy, $sortByMapping)) {
                    $sortBy = $sortByMapping[$_sortBy];
                }
            });

            OrbitInput::get('sortmode', function ($_sortMode) use (&$sortMode) {
                if (strtolower($_sortMode) !== 'desc') {
                    $sortMode = 'asc';
                }
            });
            $activities->orderBy($sortBy, $sortMode);

            $totalActivities = RecordCounter::create($_activities)->count();
            $listOfActivities = $activities->get();

            $data = new stdclass();
            $data->total_records = $totalActivities;
            $data->returned_records = count($listOfActivities);
            $data->records = $listOfActivities;

            if ($totalActivities === 0) {
                $data->records = null;
                $this->response->message = Lang::get('statuses.orbit.nodata.activity');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.activity.getactivity.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.activity.getactivity.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.activity.getactivity.query.error', array($this, $e));

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
            Event::fire('orbit.activity.getactivity.general.exception', array($this, $e));

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
        Event::fire('orbit.activity.getactivity.before.render', array($this, &$output));

        return $output;
    }

    public function getSignUpStatistics()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.activity.getactivity.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.activity.getactivity.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.activity.getactivity.before.authz', array($this, $user));

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = ['super admin', 'mall admin', 'mall owner', 'mall customer service'];
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.activity.getactivity.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $start_date = OrbitInput::get('start_date');
            $previous_start_date = OrbitInput::get('previous_start_date');
            $end_date = OrbitInput::get('end_date');

            $tomorrow = date('Y-m-d H:i:s', strtotime('tomorrow'));
            $validator = Validator::make(
                array(
                    'merchant_ids'  => OrbitInput::get('merchant_ids'),
                    'start_date'    => $start_date,
                    'end_date'      => $end_date,
                    'previous_start_date' => $previous_start_date,
                ),
                array(
                    'merchant_ids'  => 'orbit.check.merchants',
                    'start_date'    => 'required|date_format:Y-m-d H:i:s',
                    'end_date'      => 'required|date_format:Y-m-d H:i:s',
                    'previous_start_date'    => 'required|date_format:Y-m-d H:i:s',
                )
            );

            Event::fire('orbit.activity.getactivity.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.activity.getactivity.after.validation', array($this, $validator));

            // registrations from start to end grouped by date part and activity name long.
            // activity name long should include source.
            $tablePrefix = DB::getTablePrefix();
            $activities = DB::table('activities')
                ->select(
                    DB::raw("DATE({$tablePrefix}activities.created_at) as date"),
                    DB::raw('activity_name_long as activity'),
                    DB::raw('COUNT(*) as count')
                )
                ->where('module_name', '=', 'Application')
                ->where('group', '=', 'mobile-ci')
                ->where('activity_type', '=', 'registration')
                ->where('activity_name', '=', 'registration_ok')
                ->where('created_at', '>=', $start_date)
                ->where('created_at', '<=', $end_date)
                ->groupBy(DB::raw('1'), DB::raw('2'))
                ->orderByRaw('1')
                ->orderByRaw('2');

            $previous_period_activities = DB::table('activities')
                ->select(
                    DB::raw('activity_name_long as activity'),
                    DB::raw('COUNT(*) as count')
                )
                ->where('module_name', '=', 'Application')
                ->where('group', '=', 'mobile-ci')
                ->where('activity_type', '=', 'registration')
                ->where('activity_name', '=', 'registration_ok')
                ->where('created_at', '>=', $previous_start_date)
                ->where('created_at', '<=', $start_date)
                ->groupBy(DB::raw('1'))
                ->orderByRaw('1');

            // Only shows activities which belongs to this merchant
            if ($user->isSuperAdmin() !== TRUE) {
                $locationIds = $this->getLocationIdsForUser($user);

                // Filter by user location id
                $activities->whereIn('activities.location_id', $locationIds);
                $previous_period_activities->whereIn('activities.location_id', $locationIds);
            } else {
                // Filter by user location id
                OrbitInput::get('location_ids', function($locationIds) use ($activities, $previous_period_activities) {
                    $activities->whereIn('activities.location_id', $locationIds);
                    $previous_period_activities->whereIn('activities.location_id', $locationIds);
                });
            }


            $signups = $activities->get();
            // basically what we want to calculate is
            // SELECT all_seen_dates.date, all_seen_activities.activity, COALESCE(activity_counts.count, 0)
            // FROM all_seen_dates
            // JOIN all_seen_activities
            // LEFT JOIN activity_counts ON
            //   (all_seen_dates.date = activity_counts.date) AND
            //   (all_seen_activities.activity = activity_counts.activity)
            //
            // ensure for every date in period there exists data.
            // first we gather up activity names
            $names_seen = [];
            foreach ($signups as $sign_up) {
                $names_seen[$sign_up->activity] = true;
            }
            // then we take advantage of the sorted nature of the result set.
            // if we are done with a date, we create the missing records for the activities we did not see
            // on that date
            $sign_ups_result = [];
            $prev_date = null;
            $sign_up_accumulator = [];
            foreach ($signups as $sign_up) {
                $this_date = $sign_up->date;
                if (($this_date !== $prev_date) && ($prev_date !== null)) {
                    // flush
                    $prev_date_seen_names = [];
                    foreach ($sign_up_accumulator as $prev_date_sign_up) {
                        $prev_date_seen_names[$prev_date_sign_up->activity] = true;
                        $sign_ups_result[] = $prev_date_sign_up;
                    }
                    foreach ($names_seen as $name => $seen) {
                        if (!isset($prev_date_seen_names[$name])) {
                            $sign_ups_result[] = (object)['date' => $prev_date, 'activity' => $name, 'count' => 0];
                        }
                    }
                    $sign_up_accumulator = [];
                }
                $sign_up_accumulator[] = $sign_up;
                $prev_date = $this_date;
            }
            // flush
            $prev_date_seen_names = [];
            foreach ($sign_up_accumulator as $prev_date_sign_up) {
                $prev_date_seen_names[$prev_date_sign_up->activity] = true;
                $sign_ups_result[] = $prev_date_sign_up;
            }
            foreach ($names_seen as $name => $seen) {
                if (!isset($prev_date_seen_names[$name])) {
                    $sign_ups_result[] = (object)['date' => $prev_date, 'activity' => $name, 'count' => 0];
                }
            }

            $this->response->data = [
                'this_period' => [
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'signups' => $sign_ups_result,
                ],
                'previous_period' => [
                    'start_date' => $previous_start_date,
                    'end_date' => $start_date,
                    'signups' => $previous_period_activities->get()
                ]
            ];
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.activity.getactivity.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.activity.getactivity.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.activity.getactivity.query.error', array($this, $e));

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
            Event::fire('orbit.activity.getactivity.general.exception', array($this, $e));

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
        Event::fire('orbit.activity.getactivity.before.render', array($this, &$output));

        return $output;
    }

    public function getDeviceOsStatistics()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.activity.getactivity.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.activity.getactivity.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.activity.getactivity.before.authz', array($this, $user));

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = ['super admin', 'mall admin', 'mall owner', 'mall customer service'];
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.activity.getactivity.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $start_date = OrbitInput::get('start_date');
            $end_date = OrbitInput::get('end_date');

            $tomorrow = date('Y-m-d H:i:s', strtotime('tomorrow'));
            $validator = Validator::make(
                array(
                    'merchant_ids'  => OrbitInput::get('merchant_ids'),
                    'start_date'    => $start_date,
                    'end_date'      => $end_date,
                ),
                array(
                    'merchant_ids'  => 'orbit.check.merchants',
                    'start_date'    => 'required|date_format:Y-m-d H:i:s',
                    'end_date'      => 'required|date_format:Y-m-d H:i:s'
                )
            );

            Event::fire('orbit.activity.getactivity.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.activity.getactivity.after.validation', array($this, $validator));

            // group by UA then count distinct users.
            // users may be counted twice if the UA is not an exact match
            $activities = DB::table('activities')
                ->select(
                    'user_agent',
                    DB::raw('COUNT(DISTINCT user_id) as count')
                )
                ->where('module_name', '=', 'Application')
                ->where('group', '=', 'mobile-ci')
                ->where('activity_type', '=', 'login')
                ->where('activity_name', '=', 'login_ok')
                ->where('created_at', '>=', $start_date)
                ->where('created_at', '<=', $end_date)
                ->groupBy(DB::raw('1'))
                ->orderByRaw('1');

            // Only shows activities which belongs to this merchant
            if ($user->isSuperAdmin() !== TRUE) {
                // mall group, not specified: all malls in group
                // mall group, specified: this mall only
                // mall, not specified: this mall only
                // mall, specified: must equal self
                $locationIds = $this->getLocationIdsForUser($user);

                // Filter by user location id
                $activities->whereIn('activities.location_id', $locationIds);
            } else {
                // Filter by user location id
                OrbitInput::get('location_ids', function($locationIds) use ($activities) {
                    $activities->whereIn('activities.location_id', $locationIds);
                });
            }

            $devices = [
                'ios' => 0,
                'android' => 0,
                'blackberry' => 0,
                'windows_phone' => 0,
                'other'  => 0
            ];


            // todo if too much move to PDO and stream rows
            foreach ($activities->get() as $row) {
                $ua = $row->user_agent;
                if (preg_match('/Linux.*?Android/', $ua)) {
                    // not "Windows Phone 10.0; Android"...
                    $devices['android'] += $row->count;
                } elseif (preg_match('/\((iPhone|iPod|iPad)/', $ua)) {
                    $devices['ios'] += $row->count;
                } elseif (preg_match('/BlackBerry/', $ua)) {
                    $devices['blackberry'] += $row->count;
                } elseif (preg_match('/Windows Phone/i', $ua)) {
                    $devices['windows_phone'] += $row->count;
                } else {
                    $devices['other'] += $row->count;
                }
            }

            $this->response->data = [
                'start_date' => $start_date,
                'end_date' => $end_date,
                'devices' => $devices
            ];
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.activity.getactivity.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.activity.getactivity.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.activity.getactivity.query.error', array($this, $e));

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
            Event::fire('orbit.activity.getactivity.general.exception', array($this, $e));

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
        Event::fire('orbit.activity.getactivity.before.render', array($this, &$output));

        return $output;
    }

    public function getUserGenderStatistics()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.activity.getactivity.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.activity.getactivity.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.activity.getactivity.before.authz', array($this, $user));

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = ['super admin', 'mall admin', 'mall owner', 'mall customer service'];
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.activity.getactivity.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $start_date = OrbitInput::get('start_date');
            $end_date = OrbitInput::get('end_date');

            $tomorrow = date('Y-m-d H:i:s', strtotime('tomorrow'));
            $validator = Validator::make(
                array(
                    'merchant_ids'  => OrbitInput::get('merchant_ids'),
                    'start_date'    => $start_date,
                    'end_date'      => $end_date,
                ),
                array(
                    'merchant_ids'  => 'orbit.check.merchants',
                    'start_date'    => 'required|date_format:Y-m-d H:i:s',
                    'end_date'      => 'required|date_format:Y-m-d H:i:s'
                )
            );

            Event::fire('orbit.activity.getactivity.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.activity.getactivity.after.validation', array($this, $validator));

            // registrations from start to end grouped by date part and activity name long.
            // activity name long should include source.
            $tablePrefix = DB::getTablePrefix();
            $activities = DB::table('activities')
                ->join('user_details', 'activities.user_id', '=', 'user_details.user_id')
                ->select(
                    'user_details.gender',
                    DB::raw('COUNT(*) as count')
                )
                ->where('activities.module_name', '=', 'Application')
                ->where('activities.group', '=', 'mobile-ci')
                ->where('activities.activity_type', '=', 'login')
                ->where('activities.activity_name', '=', 'login_ok')
                ->where('activities.created_at', '>=', $start_date)
                ->where('activities.created_at', '<=', $end_date)
                ->groupBy(DB::raw('1'))
                ->orderByRaw('1');

            // Only shows activities which belongs to this merchant
            if ($user->isSuperAdmin() !== TRUE) {
                $locationIds = $this->getLocationIdsForUser($user);

                // Filter by user location id
                $activities->whereIn('activities.location_id', $locationIds);
            } else {
                // Filter by user location id
                OrbitInput::get('location_ids', function($locationIds) use ($activities) {
                    $activities->whereIn('activities.location_id', $locationIds);
                });
            }

            $this->response->data = [
                'start_date' => $start_date,
                'end_date' => $end_date,
                'gender' => $activities->get()
            ];
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.activity.getactivity.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.activity.getactivity.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.activity.getactivity.query.error', array($this, $e));

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
            Event::fire('orbit.activity.getactivity.general.exception', array($this, $e));

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
        Event::fire('orbit.activity.getactivity.before.render', array($this, &$output));

        return $output;
    }

    public function getActiveUserStatistics()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.activity.getactivity.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.activity.getactivity.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.activity.getactivity.before.authz', array($this, $user));

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = ['super admin', 'mall admin', 'mall owner', 'mall customer service'];
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.activity.getactivity.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $dates = [];

            $tomorrow = date('Y-m-d H:i:s', strtotime('tomorrow'));
            $validator = Validator::make(
                array(
                    'merchant_ids'  => OrbitInput::get('merchant_ids'),
                ),
                array(
                    'merchant_ids'  => 'orbit.check.merchants',
                )
            );

            Event::fire('orbit.activity.getactivity.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $periods_json = OrbitInput::get('periods', '{}');
            $periods = @json_decode($periods_json, JSON_OBJECT_AS_ARRAY);
            if (json_last_error() !== JSON_ERROR_NONE) {
                OrbitShopAPI::throwInvalidArgument('invalid json for periods');
            }
            foreach ($periods as $period => $dates) {
                $rules = 'required|date_format:Y-m-d H:i:s';
                $validator = Validator::make(
                    array(
                        'previous_start'  => $dates['previous_start'],
                        'start'  => $dates['start'],
                        'end'  => $dates['end'],
                    ),
                    array(
                        'previous_start' => $rules,
                        'start' => $rules,
                        'end' => $rules,
                    )
                );
                if ($validator->fails()) {
                    $errorMessage = $validator->messages()->first();
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }
            }

            Event::fire('orbit.activity.getactivity.after.validation', array($this, $validator));

            // registrations from start to end grouped by date part and activity name long.
            // activity name long should include source.
            $tablePrefix = DB::getTablePrefix();
            $result = [];
            foreach ($periods as $period => $dates) {
                $previous_start_date = $dates['previous_start'];
                $start_date = $dates['start'];
                $end_date = $dates['end'];

                $parameters = [
                    'current' => [$start_date, $end_date],
                    'previous' => [$previous_start_date, $start_date]
                ];

                $result[$period] = [];

                foreach ($parameters as $period_name => $limits) {
                    $start_limit = $limits[0];
                    $end_limit = $limits[1];
                    $activities = DB::table('activities')
                        ->select(
                            DB::raw('COUNT(DISTINCT user_id) as count')
                        )
                        ->where('module_name', '=', 'Application')
                        ->where('group', '=', 'mobile-ci')
                        ->where('activity_type', '=', 'login')
                        ->where('activity_name', '=', 'login_ok')
                        ->where('created_at', '>=', $start_limit)
                        ->where('created_at', '<=', $end_limit);

                    $duplicate_activities = DB::table('activities')
                        ->select(
                            DB::raw('COUNT(*) as count')
                        )
                        ->where('module_name', '=', 'Application')
                        ->where('group', '=', 'mobile-ci')
                        ->where('activity_type', '=', 'login')
                        ->where('activity_name', '=', 'login_ok')
                        ->where('created_at', '>=', $start_limit)
                        ->where('created_at', '<=', $end_limit);


                    // Only shows activities which belongs to this merchant
                    if ($user->isSuperAdmin() !== TRUE) {
                        $locationIds = $this->getLocationIdsForUser($user);

                        // Filter by user location id
                        $activities->whereIn('activities.location_id', $locationIds);
                        $duplicate_activities->whereIn('activities.location_id', $locationIds);
                    } else {
                        // Filter by user location id
                        OrbitInput::get('location_ids', function($locationIds) use ($activities, $duplicate_activities) {
                            $activities->whereIn('activities.location_id', $locationIds);
                            $duplicate_activities->whereIn('activities.location_id', $locationIds);
                        });
                    }

                    $result[$period][$period_name] = [
                        'start_date' => $start_limit,
                        'end_date' => $end_limit,
                        'count' => $activities->first()->count,
                        'count_with_duplicates' => $duplicate_activities->first()->count
                    ];
                }

            }

            $this->response->data = $result;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.activity.getactivity.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.activity.getactivity.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.activity.getactivity.query.error', array($this, $e));

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
            Event::fire('orbit.activity.getactivity.general.exception', array($this, $e));

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
        Event::fire('orbit.activity.getactivity.before.render', array($this, &$output));

        return $output;
    }

    public function getNewAndReturningUserStatistics()
    {
        try {
            try {
                $httpCode = 200;

                Event::fire('orbit.activity.getactivity.before.auth', array($this));

                // Require authentication
                $this->checkAuth();

                Event::fire('orbit.activity.getactivity.after.auth', array($this));

                // Try to check access control list, does this user allowed to
                // perform this action
                $user = $this->api->user;
                Event::fire('orbit.activity.getactivity.before.authz', array($this, $user));

                // @Todo: Use ACL authentication instead
                $role = $user->role;
                $validRoles = ['super admin', 'mall admin', 'mall owner', 'mall customer service'];
                if (! in_array( strtolower($role->role_name), $validRoles)) {
                    $message = 'Your role are not allowed to access this resource.';
                    ACL::throwAccessForbidden($message);
                }

                Event::fire('orbit.activity.getactivity.after.authz', array($this, $user));

                $this->registerCustomValidation();

                $start_date = OrbitInput::get('start_date');
                $end_date = OrbitInput::get('end_date');

                $tomorrow = date('Y-m-d H:i:s', strtotime('tomorrow'));
                $validator = Validator::make(
                    array(
                        'merchant_ids'  => OrbitInput::get('merchant_ids'),
                        'start_date'    => $start_date,
                        'end_date'      => $end_date,
                    ),
                    array(
                        'merchant_ids'  => 'orbit.check.merchants',
                        'start_date'    => 'required|date_format:Y-m-d H:i:s',
                        'end_date'      => 'required|date_format:Y-m-d H:i:s'
                    )
                );

                Event::fire('orbit.activity.getactivity.before.validation', array($this, $validator));

                // Run the validation
                if ($validator->fails()) {
                    $errorMessage = $validator->messages()->first();
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }
                Event::fire('orbit.activity.getactivity.after.validation', array($this, $validator));

                $sign_ups = DB::table('activities')
                    ->select(
                        DB::raw('COUNT(*) as count')
                    )
                    ->where('module_name', '=', 'Application')
                    ->where('group', '=', 'mobile-ci')
                    ->where('activity_type', '=', 'registration')
                    ->where('activity_name', '=', 'registration_ok')
                    ->where('created_at', '>=', $start_date)
                    ->where('created_at', '<=', $end_date);

                $returning_sign_ins = DB::table('activities')
                    ->select(
                        DB::raw('COUNT(distinct user_id) as count')
                    )
                    ->where('module_name', '=', 'Application')
                    ->where('group', '=', 'mobile-ci')
                    ->where('activity_type', '=', 'login')
                    ->where('activity_name', '=', 'login_ok')
                    ->where('created_at', '>=', $start_date)
                    ->where('created_at', '<=', $end_date)
                    ->whereNotIn('user_id', function ($q) use ($start_date, $end_date) {
                        $q->select('user_id')
                            ->from('activities')
                            ->where('module_name', '=', 'Application')
                            ->where('group', '=', 'mobile-ci')
                            ->where('activity_type', '=', 'registration')
                            ->where('activity_name', '=', 'registration_ok')
                            ->where('created_at', '>=', $start_date)
                            ->where('created_at', '<=', $end_date);
                    });

                // Only shows activities which belongs to this merchant
                if ($user->isSuperAdmin() !== TRUE) {
                    $locationIds = $this->getLocationIdsForUser($user);

                    // Filter by user location id
                    $sign_ups->whereIn('activities.location_id', $locationIds);
                    $returning_sign_ins->whereIn('activities.location_id', $locationIds);
                } else {
                    // Filter by user location id
                    OrbitInput::get('location_ids', function($locationIds) use ($sign_ups, $returning_sign_ins) {
                        $sign_ups->whereIn('activities.location_id', $locationIds);
                        $returning_sign_ins->whereIn('activities.location_id', $locationIds);
                    });
                }

                $sign_up_count = (int)$sign_ups->first()->count;
                $returning_sign_in_count = (int)$returning_sign_ins->first()->count;

                $this->response->data = [
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'new' => $sign_up_count,
                    'returning' => $returning_sign_in_count
                ];
            } catch (ACLForbiddenException $e) {
                Event::fire('orbit.activity.getactivity.access.forbidden', array($this, $e));

                $this->response->code = $e->getCode();
                $this->response->status = 'error';
                $this->response->message = $e->getMessage();
                $this->response->data = null;
                $httpCode = 403;
            } catch (InvalidArgsException $e) {
                Event::fire('orbit.activity.getactivity.invalid.arguments', array($this, $e));

                $this->response->code = $e->getCode();
                $this->response->status = 'error';
                $this->response->message = $e->getMessage();
                $result['total_records'] = 0;
                $result['returned_records'] = 0;
                $result['records'] = null;

                $this->response->data = $result;
                $httpCode = 403;
            } catch (QueryException $e) {
                Event::fire('orbit.activity.getactivity.query.error', array($this, $e));

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
                Event::fire('orbit.activity.getactivity.general.exception', array($this, $e));

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
            Event::fire('orbit.activity.getactivity.before.render', array($this, &$output));

            return $output;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.activity.getactivity.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.activity.getactivity.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.activity.getactivity.query.error', array($this, $e));

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
            Event::fire('orbit.activity.getactivity.general.exception', array($this, $e));

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
        Event::fire('orbit.activity.getactivity.before.render', array($this, &$output));

        return $output;
    }

    protected function registerCustomValidation()
    {
        $user = $this->api->user;
        Validator::extend('orbit.check.merchants', function ($attribute, $value, $parameters) use ($user) {
            $merchants = Merchant::excludeDeleted()
                        ->allowedForUser($user)
                        ->whereIn('merchant_id', $value)
                        ->where('is_mall', 'yes')
                        ->limit(50)
                        ->get();

            $merchantIds = array();

            foreach ($merchants as $id) {
                $merchantIds[] = $id;
            }

            App::instance('orbit.check.merchants', $merchantIds);

            return TRUE;
        });
    }

    /**
     * Get location IDs for user.
     *
     * If user is mall group then if not specified: all malls in group.
     * If specified then must be mall in group.
     * If user is mall then return self.
     * @param User $user
     * @return mixed[] list of IDs
     */
    private function getLocationIdsForUser($user)
    {
        $mall_group = Merchant::excludeDeleted()->where('user_id', '=', $user->user_id)->first(); // todo get() ?
        if (isset($mall_group)) {
            $malls = Retailer::excludeDeleted()
                ->where('parent_id', '=', $mall_group->merchant_id)
                ->where('is_mall', '=', 'yes');
            OrbitInput::get('location_ids', function($locationIds) use ($malls) {
                $malls->whereIn('merchant_id', $locationIds);
            });
            return $malls->lists('merchant_id');
        }
        else {
            $mall = Retailer::excludeDeleted()->where('user_id', '=', $user->user_id)->first();
            if (isset($mall)) {
                return [$mall->merchant_id];
            }
            else {
                return [-1]; // ensure no results
            }
        }
    }
}
