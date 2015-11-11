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
    private $returnQuery = false;

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
                    'start_date'    => 'date_format:Y-m-d H:i:s',
                    'end_date'      => 'date_format:Y-m-d H:i:s',
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
                $activities->whereIn('activities.location_id', $merchantIds);
            });

            // Filter by retailer ids
            // OrbitInput::get('retailer_ids', function($retailerIds) use ($activities) {
            //     $activities->whereIn('activities.location_id', $retailerIds);
            // });

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
                $activities->whereIn('user_details.gender', $genders);
            });

            // Filter by groups
            if (! empty($_GET['groups'])) {
                OrbitInput::get('groups', function($groups) use ($activities) {
                    $activities->whereIn('activities.group', $groups);
                });
            } else {
                $activities->where(function($q) {
                    $q->whereIn('activities.group', ['mobile-ci', 'pos'])
                      ->orWhere(function($q) {
                            $q->where('activities.activity_name', 'registration_ok')
                              ->where('activities.group', 'cs-portal');
                      });
                    });
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

            // Prevent query leak, we select only field which should guarantee to be indexed
            $_activities->select('activities.activity_id');

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
                $devices[$this->categorizeUserAgent($ua)] += $row->count;
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
            $format = OrbitInput::get('format', 'old');

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
                ->leftJoin('user_details', 'activities.user_id', '=', 'user_details.user_id')
                ->select(
                    'user_details.gender',
                    DB::raw('COUNT(DISTINCT ' . $tablePrefix . 'activities.user_id) as count')
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

            if ($format === 'new') {
                $male = 0;
                $female = 0;
                $unknown = 0;
                foreach ($activities->get() as $r) {
                    if ($r->gender === 'f' || $r->gender === 'F') {
                        $female += (int) $r->count;
                    } elseif  ($r->gender === 'm' || $r->gender === 'M') {
                        $male += (int) $r->count;
                    } else {
                        $unknown += (int) $r->count;
                    }
                }
                $this->response->data = [
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'male' => $male,
                    'female' => $female,
                    'unknown' => $unknown,
                ];
            } else {
                $this->response->data = [
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'gender' => $activities->get()
                ];
            }
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

    /**
     * Dashboard Customer Today
     *
     * Displays the number of customers who signed in to Orbit Mall mobile CI for the day. The chart at the bottom displays the trend of "customers today" for the last 30 days.
     *
     * @author Tian <tian@dominopos.com>
     *
     * @param datetime   `start_date`                   (required) - Start date
     * @param datetime   `end_date`                     (required) - End date
     * @param array      `location_ids`                 (optional) - Location ID
     */
    public function getUserTodayStatistics()
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

            $validator = Validator::make(
                array(
                    'start_date'    => $start_date,
                    'end_date'      => $end_date,
                ),
                array(
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
                ->select(
                    DB::raw("DATE({$tablePrefix}activities.created_at) as date"),
                    DB::raw("COUNT(DISTINCT {$tablePrefix}activities.user_id) as count")
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
                'end_date'   => $end_date,
                'today'      => $activities->get()
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

    public function getConnectedNowStatistics()
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

            $active_minutes = OrbitInput::get('active_minutes');

            $validator = Validator::make(
                array(
                    'merchant_ids'  => OrbitInput::get('merchant_ids'),
                    'active_minutes'    => $active_minutes,
                ),
                array(
                    'merchant_ids'  => 'orbit.check.merchants',
                    'active_minutes' => 'required|integer'
                )
            );

            Event::fire('orbit.activity.getactivity.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.activity.getactivity.after.validation', array($this, $validator));

            // Only shows activities which belongs to this merchant
            $locationIds = [];
            if ($user->isSuperAdmin() !== TRUE) {
                // Filter by user location id
                $locationIds = $this->getLocationIdsForUser($user);
                if (count($locationIds) === 0) {
                    // not authorized for any locations?
                    $filterLocationIds = 'AND 1 = 0';
                } else {
                    $filterLocationIds = 'AND a.location_id IN (';
                    $filterLocationIds .= join(',', array_fill(0, count($filterLocationIds), '?'));
                    $filterLocationIds .= ')';
                }
            } else {
                // by default allow all
                $filterLocationIds = 'AND 1 = 1';
                // except if filtered explicitly
                OrbitInput::get('location_ids', function($paramLocationIds) use (&$filterLocationIds, &$locationIds) {
                    $paramLocationIds = (array)$paramLocationIds;
                    if (count($paramLocationIds) === 0) {
                        $filterLocationIds = 'AND 1 = 0';
                    } else {
                        $filterLocationIds = 'AND a.location_id IN (';
                        $filterLocationIds .= join(',', array_fill(0, count($paramLocationIds), '?'));
                        $filterLocationIds .= ')';
                    }
                    $locationIds = $paramLocationIds;
                });
            }

            // "connected now" = last login/logout activity within the period was login
            $tablePrefix = DB::getTablePrefix();
            $activities = DB::select("
                select COUNT(DISTINCT user_id) num
                from
                    {$tablePrefix}activities a
                where
                    a.user_id != '0' and
                    a.group = 'mobile-ci' and
                    a.created_at >= DATE_ADD(CURRENT_TIMESTAMP, INTERVAL ? MINUTE)
                    {$filterLocationIds}
            ", array_merge([-1 * (int)$active_minutes], $locationIds));

            $users_count = 0;
            foreach ($activities as $activity) {
                $users_count = (int)$activity->num;
            }

            $this->response->data = [
                'active_minutes' => (int)$active_minutes,
                'connected_now' => $users_count,
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

    public function getUserAgeStatistics()
    {
        $httpCode = 200;
        try {

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

            $this_year = date('Y');
            $this_month = date('n');

            $tablePrefix = DB::getTablePrefix();
            $activities = DB::table('activities')
                ->leftJoin('user_details', 'activities.user_id', '=', 'user_details.user_id')
                ->select(
                    DB::raw(
                        $this_year . ' - EXTRACT(YEAR FROM birthdate)
                     -
                    CASE
                      WHEN EXTRACT(MONTH FROM birthdate) > ' . $this_month . ' THEN 1
                      ELSE 0
                    END as age
                    '),
                    DB::raw('COUNT(DISTINCT ' . $tablePrefix . 'activities.user_id) as count')
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

            $buckets = [
                15 => 0,
                25 => 0,
                35 => 0,
                45 => 0,
                55 => 0,
                65 => 0,
                0 => 0
            ];
            $unknown = 0;

            foreach ($activities->get() as $age) {
                if ($age->age === null) {
                    $unknown += (int)$age->count;
                } else {
                    foreach ($buckets as $limit => $count) {
                        if ($limit === 0) {
                            $buckets[$limit] += (int)$age->count;
                        } elseif ((int)$age->age < $limit) {
                            $buckets[$limit] += (int)$age->count;
                            break;
                        }
                    }
                }
            }

            $result = [];
            $prev = 0;
            foreach ($buckets as $limit => $count) {
                if ($limit === 0) {
                    $key = sprintf('%d+', $prev);
                } else {
                    $key = sprintf('%d - %d', $prev, $limit - 1);
                }
                $result[$key] = $count;
                $prev = $limit;
            }
            $result['unknown'] = $unknown;

            $this->response->data = [
                'start_date' => $start_date,
                'end_date' => $end_date,
                'ages' => $result,
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

                $multiple_period = false;
                if (OrbitInput::get('periods', null) !== null) {
                    $multiple_period = true;
                    $validator = null;
                    $periods_json = OrbitInput::get('periods', '{}');
                    $periods = @json_decode($periods_json, JSON_OBJECT_AS_ARRAY);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        OrbitShopAPI::throwInvalidArgument('invalid json for periods');
                    }
                    foreach ($periods as $dates) {
                        $rules = 'required|date_format:Y-m-d H:i:s';
                        $validator = Validator::make(
                            array(
                                'start_date'  => $dates['start_date'],
                                'end_date'  => $dates['end_date'],
                            ),
                            array(
                                'start_date' => $rules,
                                'end_date' => $rules,
                            )
                        );
                        if ($validator->fails()) {
                            $errorMessage = $validator->messages()->first();
                            OrbitShopAPI::throwInvalidArgument($errorMessage);
                        }
                    }

                } else {
                    // requesting single period
                    $start_date = OrbitInput::get('start_date');
                    $end_date = OrbitInput::get('end_date');

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
                    $periods = [
                        [
                            'start_date' => $start_date,
                            'end_date' => $end_date,
                        ]
                    ];
                }
                Event::fire('orbit.activity.getactivity.before.validation', array($this, $validator));

                // Run the validation
                if ($validator->fails()) {
                    $errorMessage = $validator->messages()->first();
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }
                Event::fire('orbit.activity.getactivity.after.validation', array($this, $validator));

                $responses = [];
                foreach ($periods as $period) {

                    $start_date = $period['start_date'];
                    $end_date = $period['end_date'];

                    $sign_ups = DB::table('user_acquisitions')
                        ->select(
                            DB::raw('COUNT(*) as count')
                        )
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
                        ->where('created_at', '<=', $end_date);

                    // Only shows activities which belongs to this merchant
                    if ($user->isSuperAdmin() !== TRUE) {
                        $locationIds = $this->getLocationIdsForUser($user);

                        // Filter by user location id
                        $sign_ups->whereIn('acquirer_id', $locationIds);
                        $returning_sign_ins->whereIn('activities.location_id', $locationIds);
                    } else {
                        // Filter by user location id
                        OrbitInput::get('location_ids', function($locationIds) use ($sign_ups, $returning_sign_ins) {
                            $sign_ups->whereIn('acquirer_id', $locationIds);
                            $returning_sign_ins->whereIn('activities.location_id', $locationIds);
                        });
                    }

                    $sign_up_count = (int)$sign_ups->first()->count;
                    $returning_sign_in_count = (int)$returning_sign_ins->first()->count;

                    $responses[] = [
                        'start_date' => $start_date,
                        'end_date' => $end_date,
                        'new' => $sign_up_count,
                        'returning' => max(0, $returning_sign_in_count - $sign_up_count)
                    ];
                }

                if ($multiple_period) {
                    $this->response->data = $responses;
                } else {
                    $this->response->data = $responses[0];
                }
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

    public function getCaptivePortalReport()
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
            $first_name = OrbitInput::get('first_name');
            $last_name = OrbitInput::get('last_name');
            $email = OrbitInput::get('email');
            $gender = OrbitInput::get('gender');
            $os = OrbitInput::get('os');
            $sign_up_method = OrbitInput::get('sign_up_method');
            $sort_by = OrbitInput::get('sortby');
            $sort_mode = OrbitInput::get('sortmode', 'asc');


            $validator = Validator::make(
                array(
                    'start_date'    => $start_date,
                    'end_date'      => $end_date,
                    'first_name'    => $first_name,
                    'last_name'     => $last_name,
                    'email'         => $email,
                    'gender'        => $gender,
                    'os'            => $os,
                    'sign_up_method' => $sign_up_method,
                    'sort_by'       => $sort_by,
                    'sort_mode'     => $sort_mode,
                ),
                array(
                    'sort_by'       => 'in:first_name,last_name,os,age,gender,total_visits,sign_up_method,email,first_visit,last_visit',
                    'sort_mode'     => 'in:asc,desc',
                    'start_date'    => 'date_format:Y-m-d H:i:s',
                    'end_date'      => 'date_format:Y-m-d H:i:s',
                    'name'          => '',
                    'gender'        => 'in:m,f,unknown',
                    'email'         => '',
                    'os'            => 'in:android,ios,blackberry,windows_phone,other',
                    'sign_up_method' => 'in:email,facebook',
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

            $take = $perPage;

            OrbitInput::get('take', function ($_take) use (&$take, $maxRecord) {
                if ($_take > $maxRecord) {
                    $_take = $maxRecord;
                }
                $take = (int)$_take;

                if ((int)$take <= 0) {
                    $take = $maxRecord;
                }
            });

            $skip = 0;
            OrbitInput::get('skip', function ($_skip) use (&$skip) {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = (int)$_skip;
            });

            $limit_clause = sprintf(' LIMIT %d OFFSET %d ', $take, $skip);

            $prefix = DB::getTablePrefix();

            $binds = [];

            $first_name_condition = '';
            OrbitInput::get('first_name', function ($name) use (&$binds, &$first_name_condition) {
                $binds['first_name_like'] = '%' . $name . '%';
                $first_name_condition = ' and (user_data.user_firstname LIKE :first_name_like) ';
            });

            $last_name_condition = '';
            OrbitInput::get('last_name', function ($name) use (&$binds, &$last_name_condition) {
                $binds['last_name_like'] = '%' . $name . '%';
                $last_name_condition = ' and (user_data.user_lastname LIKE :last_name_like) ';
            });

            $email_condition = '';
            OrbitInput::get('email', function ($email) use (&$binds, &$email_condition) {
                $binds['email_like'] = '%' . $email . '%';
                $email_condition = ' and (user_data.user_email LIKE :email_like) ';
            });

            $gender_condition = '';
            OrbitInput::get('gender', function ($gender) use (&$binds, &$gender_condition) {
                if ($gender == 'unknown') {
                    $binds['gender_1'] = 'm';
                    $binds['gender_2'] = 'f';
                    $gender_condition = ' and (user_details.gender IS NULL OR user_details.gender NOT IN ( :gender_1, :gender_2 )) ';
                } else {
                    $binds['gender'] = $gender;
                    $gender_condition = ' and (user_details.gender = :gender) ';
                }
            });

            $start_date_condition_1 = '';
            $start_date_condition_2 = '';
            OrbitInput::get('start_date', function ($start_date) use (&$binds, &$start_date_condition_1, &$start_date_condition_2) {
                $binds['start_date_1'] = $binds['start_date_2'] = $start_date;
                $start_date_condition_1 = ' and (created_at >= :start_date_1) ';
                $start_date_condition_2 = ' and (created_at >= :start_date_2) ';
            });

            $end_date_condition_1 = '';
            $end_date_condition_2 = '';
            OrbitInput::get('end_date', function ($end_date) use (&$binds, &$end_date_condition_1, &$end_date_condition_2) {
                $binds['end_date_1'] = $binds['end_date_2'] = $end_date;
                $end_date_condition_1 = ' and (created_at <= :end_date_1) ';
                $end_date_condition_2 = ' and (created_at <= :end_date_2) ';
            });

            $sign_up_method_condition = '';
            OrbitInput::get('sign_up_method', function ($sign_up_method) use (&$binds, &$sign_up_method_condition) {
                if ($sign_up_method === 'facebook') {
                    $sign_up_method_condition = ' and (registration.registration = :sign_up_method) ';
                    $binds['sign_up_method'] = 'Sign up via mobile (Facebook)';
                } else if ($sign_up_method === 'email') {
                    $sign_up_method_condition = ' and ((registration.registration = :sign_up_method_1) OR (registration.registration = :sign_up_method_2))';
                    $binds['sign_up_method_1'] = 'Sign Up with email address';
                    $binds['sign_up_method_2'] = 'Sign Up';
                }
                else {
                    $sign_up_method_condition = ' and (1 = 0) ';
                }
            });


            $os_condition = '';
            OrbitInput::get('os', function ($os) use (&$binds, &$os_condition) {
                $regexes['android'] = 'Linux.*Android';
                $regexes['ios'] = '\\((iPhone|iPod|iPad)';
                $regexes['blackberry'] = 'BlackBerry';
                $regexes['windows_phone'] = 'Windows Phone';
                if (isset($regexes[$os])) {
                    $os_condition = ' and (last_visit.user_agent RLIKE :ua_like) ';
                    $binds['ua_like'] = $regexes[$os];
                } else {
                    // no better way to do this?
                    $os_condition = ' and (NOT (
                    (last_visit.user_agent RLIKE :ua_like_1) OR
                    (last_visit.user_agent RLIKE :ua_like_2) OR
                    (last_visit.user_agent RLIKE :ua_like_3) OR
                    (last_visit.user_agent RLIKE :ua_like_4)
                    )) ';
                    $binds['ua_like_1'] = $regexes['android'];
                    $binds['ua_like_2'] = $regexes['ios'];
                    $binds['ua_like_3'] = $regexes['blackberry'];
                    $binds['ua_like_4'] = $regexes['windows_phone'];
                }
            });

            // Only shows activities which belongs to this merchant
            if ($user->isSuperAdmin() !== TRUE) {
                $locationIds = $this->getLocationIdsForUser($user);
            } else {
                // Filter by user location id
                $locationIds = OrbitInput::get('location_ids', []);
            }
            if (count($locationIds) == 0) {
                if ($user->isSuperAdmin() !== TRUE) {
                    // not admin and getLocationIdsForUser returns 0 locations
                    $location_id_condition_1 = ' and 1 = 0 ';
                    $location_id_condition_2 = ' and 1 = 0 ';
                    $location_id_condition_3 = ' and 1 = 0 ';
                } else {
                    // admin does not provide, view all locations
                    $location_id_condition_1 = '';
                    $location_id_condition_2 = '';
                    $location_id_condition_3 = '';
                }
            } else {
                // overwritten later just so it does not complain
                $location_id_condition_1 = ' and 1 = 0 ';
                $location_id_condition_2 = ' and 1 = 0 ';
                $location_id_condition_3 = ' and 1 = 0 ';
                for ($condition_index = 1; $condition_index <= 3; $condition_index++) {
                    $var_name = 'location_id_condition_' . $condition_index;
                    $$var_name = ' and location_id in ( ';
                    $i = 0;
                    foreach ($locationIds as $location_id) {
                        $bind_name = sprintf('location_id_%d_%d', $condition_index, $i++);
                        $binds[$bind_name] = $location_id;
                        $$var_name .= ":{$bind_name},";
                    }
                    // remove last , and close paren
                    $$var_name = substr($$var_name, 0, strlen($$var_name) - 1) . ') ';
                }
            }

            $login_activity_conditions = " where module_name = 'Application'
                    and `group` = 'mobile-ci'
                    and activity_type = 'login'
                    and activity_name = 'login_ok'
                    ";

            $registration_activity_conditions = " where module_name = 'Application'
                    and `group` = 'mobile-ci'
                    and activity_type = 'registration'
                    and activity_name = 'registration_ok'
                    ";

            $count_fields = "SELECT COUNT(*) as count ";
            $query_fields = "SELECT user_data.user_id,
                user_data.user_firstname as first_name,
                user_data.user_lastname as last_name,
                case
                    when last_visit.user_agent rlike 'Linux.*Android' then 'android'
                    when last_visit.user_agent rlike '\\\\(iPhone|iPod|iPad)' then 'ios'
                    when last_visit.user_agent rlike 'BlackBerry' then 'blackberry'
                    when last_visit.user_agent rlike 'Windows Phone' then 'windows_phone'
                    else 'other'
                end
                as os,
                user_details.birthdate,
                user_details.gender,
                (
                   select
                   count(DISTINCT DATE(created_at)) as total_visits
                   from {$prefix}activities total_visits_activity
                   {$login_activity_conditions}
                   and user_id = user_data.user_id
                   {$location_id_condition_2}
                   {$start_date_condition_1}
                   {$end_date_condition_1}
                )
                as total_visits,
                case
                    when registration.registration like '%Facebook%' then 'facebook'
                    when registration.registration is null then 'unknown'
                    else 'email'
                end
                as sign_up_method,
                user_data.user_email as email,
                (
                    select
                    min(created_at) as first_visit
                    from {$prefix}activities first_visit_activity
                    {$login_activity_conditions}
                    and user_id = user_data.user_id
                    {$location_id_condition_1}
                ) as first_visit,
                last_visit.last_visit as last_visit
             ";

            $order_clause = ' ORDER BY last_visit DESC ';

            $fields = ['__NOT_USED__', 'user_id', 'first_name','last_name','os','age','gender','total_visits','sign_up_method','email','first_visit','last_visit'];
            $order_by_index = array_search($sort_by, $fields, true);
            if ($order_by_index !== FALSE) {
                $direction = strtolower($sort_mode) == 'desc' ? 'desc' : 'asc';
                if ($sort_by === 'age') {
                    // order by age = order by birthdate with direction reversed, but if direction reversed the nulls
                    // are sorted the wrong way. using datediff with the birthdate on the right hand side will sort the
                    // nulls the right way.
                    $order_clause = " ORDER BY DATEDIFF('2000-01-01', user_details.birthdate) $direction ";
                } else {
                    $order_clause = sprintf(' ORDER BY %d %s ', $order_by_index, $direction);
                }
            }


            $query_without_fields = "
                from {$prefix}users user_data
                inner join
                (
                   select
                   last_visit.user_id, max(last_visit.created_at) as last_visit, max(a1.user_agent) as user_agent
                   from {$prefix}activities a1
                   inner join
                   (
                      select
                      user_id, max(created_at) as created_at
                      from {$prefix}activities a2
                      {$login_activity_conditions}
                      {$location_id_condition_3}
                      {$start_date_condition_2}
                      {$end_date_condition_2}
                      group by 1
                   )
                   last_visit on (a1.user_id = last_visit.user_id)
                   and (a1.created_at = last_visit.created_at)
                   group by 1
                )
                last_visit on (user_data.user_id = last_visit.user_id)
                left join {$prefix}user_details user_details on (user_data.user_id = user_details.user_id)
                left join
                (
                   select
                   user_id, min(activity_name_long) as registration
                   from {$prefix}activities total_visits_activity
                   {$registration_activity_conditions}
                   group by user_id
                )
                registration on (user_data.user_id = registration.user_id)
                where (1 = 1)
                {$first_name_condition}
                {$last_name_condition}
                {$gender_condition}
                {$email_condition}
                {$os_condition}
                {$sign_up_method_condition}
                ";

            // binds for count are without $location_id_condition_1, $location_id_condition_2, $start_date_condition_1, $end_date_condition_1
            $count_binds = [];
            foreach ($binds as $k => $v) {
                if (preg_match('/^(location_id_[12]|(start|end)_date_1)/', $k)) {
                    continue;
                }
                $count_binds[$k] = $v;
            }
            if ($this->returnQuery) {
                return [
                    'query' => $query_fields . $query_without_fields . $order_clause,
                    'binds' => $binds,
                    'count_query' => $count_fields . $query_without_fields,
                    'count_binds' => $count_binds,
                ];
            }

            $data = DB::select(DB::raw($query_fields . $query_without_fields . $order_clause . $limit_clause), $binds);
            $count = DB::select(DB::raw($count_fields . $query_without_fields), $count_binds);

            $today_year = (int) date("Y");
            $today_date = date("m-d");
            foreach ($data as $row) {
                $row->age = $this->calculateAge($row->birthdate, $today_date, $today_year);
            }


            $this->response->data = [
                'total_records' => (int)$count[0]->count,
                'returned_records' => count($data),
                'records' => $data
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


    /**
     * GET - Cutomer averege connected time , Default data is 14 days before today
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List Of Parameters
     * ------------------
     * @param integer `merchant_id`              (optional) - limit by merchant id
     * @param date    `start_date`               (optional) - filter date begin
     * @param date    `end_date`                 (optional) - filter date end
     *
     * @return Illuminate\Support\Facades\Response
     */

    public function getCustomerAverageConnectedTime()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.activity.getcustomeraverageconnectedtime.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.activity.getcustomeraverageconnectedtime.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.activity.getcustomeraverageconnectedtime.before.authz', array($this, $user));

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = ['super admin', 'mall admin', 'mall owner', 'mall customer service'];
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.activity.getcustomeraverageconnectedtime.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $current_mall = OrbitInput::get('current_mall');
            $start_date = OrbitInput::get('start_date'); //2015-11-20
            $end_date = OrbitInput::get('end_date'); //2015-11-25

            $validator = Validator::make(
                array(
                    'current_mall'        => $current_mall,
                    'start_date'          => $start_date,
                    'end_date'            => $end_date,
                ),
                array(
                    'current_mall'        => 'orbit.empty.merchant',
                    'start_date'          => 'required|date_format:Y-m-d',
                    'end_date'            => 'required|date_format:Y-m-d',
                )
            );

            Event::fire('orbit.activity.getcustomeraverageconnectedtime.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.activity.getcustomeraverageconnectedtime.after.validation', array($this, $validator));

            // registrations from start to end grouped by date part and activity name long.
            // activity name long should include source.
            $tablePrefix = DB::getTablePrefix();

            $activities = DB::select( DB::raw("
                    SELECT DATE(created_at) as date, ROUND(avg(TIMESTAMPDIFF(MINUTE, mindate, maxdate))) as total_minutes FROM (
                          SELECT
                          activity_name, activity_name_long, activity_type, user_email, role, location_id, created_at, updated_at, session_id,
                          MIN(created_at) mindate, MAX(created_at) maxdate
                          FROM {$tablePrefix}activities WHERE 1=1
                          AND (activity_name = 'login_ok' OR activity_name = 'logout_ok')
                          AND location_id = '" . $current_mall . "'
                          AND role = 'Consumer'
                          AND session_id IS NOT NULL
                          AND DATE_FORMAT(created_at, '%Y-%m-%d') >= '" . $start_date . "'
                          AND DATE_FORMAT(created_at, '%Y-%m-%d') <= '" . $end_date . "'
                          GROUP BY session_id
                    ) as A
                    group by date
                    order by created_at asc
                ") );

            // Check interval
            $begin = new DateTime($start_date);
            $endtime = new DateTime($end_date);
            // Plus one day endtime
            $end = $endtime->add(new DateInterval('P1D'));

            // get periode per 1 day
            $interval = DateInterval::createFromDateString('1 day');
            $period = new DatePeriod($begin, $interval, $end);

            $activitiesObj = null;

            // Check exist data and handling null data
            foreach ( $period as $keyPeriode => $valPeriode ){
                foreach ($activities as $keyAct => $valAct) {
                    if (  $valPeriode->format( "Y-m-d" ) ==  $valAct->date) {
                        $activitiesObj[$keyPeriode] = new stdClass();
                        $activitiesObj[$keyPeriode]->date = $valPeriode->format( "Y-m-d" );
                        $activitiesObj[$keyPeriode]->total_minutes = $valAct->total_minutes;
                        break;
                    } else {
                        $activitiesObj[$keyPeriode] = new stdClass();
                        $activitiesObj[$keyPeriode]->date = $valPeriode->format( "Y-m-d" );
                        $activitiesObj[$keyPeriode]->total_minutes = 0;
                    }
                }
            }

            $this->response->data = [
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'connected_time' => $activitiesObj,
            ];
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.activity.getcustomeraverageconnectedtime.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.activity.getcustomeraverageconnectedtime.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.activity.getcustomeraverageconnectedtime.query.error', array($this, $e));

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
            Event::fire('orbit.activity.getcustomeraverageconnectedtime.general.exception', array($this, $e));

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
        Event::fire('orbit.activity.getcustomeraverageconnectedtime.before.render', array($this, &$output));

        return $output;
    }

    /**
     * GET - Cutomer averege connected time , Default data is 14 days before today
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List Of Parameters
     * ------------------
     * @param integer `current_mall`              (optional) - limit by merchant id
     * @param date    `start_date`               (optional) - filter date begin
     * @param date    `end_date`                 (optional) - filter date end
     *
     * @return Illuminate\Support\Facades\Response
     */

    public function getCustomerConnectedHourly()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.activity.getcustomerconnectedhourly.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.activity.getcustomerconnectedhourly.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.activity.getcustomerconnectedhourly.before.authz', array($this, $user));

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = ['super admin', 'mall admin', 'mall owner', 'mall customer service'];
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.activity.getcustomerconnectedhourly.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $current_mall = OrbitInput::get('current_mall');
            $start_date = OrbitInput::get('start_date'); // '2015-11-03 00:00:00'
            $end_date = OrbitInput::get('end_date'); // '2015-11-10 23:59:59'

            $validator = Validator::make(
                array(
                    'current_mall'        => $current_mall,
                    'start_date'          => $start_date,
                    'end_date'            => $end_date,
                ),
                array(
                    'current_mall'        => 'orbit.empty.merchant',
                    'start_date'          => 'required|date_format:Y-m-d H:i:s',
                    'end_date'            => 'required|date_format:Y-m-d H:i:s',
                )
            );

            Event::fire('orbit.activity.getcustomerconnectedhourly.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.activity.getcustomerconnectedhourly.after.validation', array($this, $validator));

            // registrations from start to end grouped by date part and activity name long.
            // activity name long should include source.
            $tablePrefix = DB::getTablePrefix();

            $activities = DB::select( DB::raw("
                    SELECT
                        SUM(case when starthr <= '00' and endhr >= '00' then 1 else 0 end) as '0',
                        SUM(case when starthr <= '01' and endhr >= '01' then 1 else 0 end) as '1',
                        SUM(case when starthr <= '02' and endhr >= '02' then 1 else 0 end) as '2',
                        SUM(case when starthr <= '03' and endhr >= '03' then 1 else 0 end) as '3',
                        SUM(case when starthr <= '04' and endhr >= '04' then 1 else 0 end) as '4',
                        SUM(case when starthr <= '05' and endhr >= '05' then 1 else 0 end) as '5',
                        SUM(case when starthr <= '06' and endhr >= '06' then 1 else 0 end) as '6',
                        SUM(case when starthr <= '07' and endhr >= '07' then 1 else 0 end) as '7',
                        SUM(case when starthr <= '08' and endhr >= '08' then 1 else 0 end) as '8',
                        SUM(case when starthr <= '09' and endhr >= '09' then 1 else 0 end) as '9',
                        SUM(case when starthr <= '10' and endhr >= '10' then 1 else 0 end) as '10',
                        SUM(case when starthr <= '11' and endhr >= '11' then 1 else 0 end) as '11',
                        SUM(case when starthr <= '12' and endhr >= '12' then 1 else 0 end) as '12',
                        SUM(case when starthr <= '13' and endhr >= '13' then 1 else 0 end) as '13',
                        SUM(case when starthr <= '14' and endhr >= '14' then 1 else 0 end) as '14',
                        SUM(case when starthr <= '15' and endhr >= '15' then 1 else 0 end) as '15',
                        SUM(case when starthr <= '16' and endhr >= '16' then 1 else 0 end) as '16',
                        SUM(case when starthr <= '17' and endhr >= '17' then 1 else 0 end) as '17',
                        SUM(case when starthr <= '18' and endhr >= '18' then 1 else 0 end) as '18',
                        SUM(case when starthr <= '19' and endhr >= '19' then 1 else 0 end) as '19',
                        SUM(case when starthr <= '20' and endhr >= '20' then 1 else 0 end) as '20',
                        SUM(case when starthr <= '21' and endhr >= '21' then 1 else 0 end) as '21',
                        SUM(case when starthr <= '22' and endhr >= '22' then 1 else 0 end) as '22',
                        SUM(case when starthr <= '23' and endhr >= '23' then 1 else 0 end) as '23'
                    FROM (
                        SELECT
                            activity_name, activity_name_long, activity_type, user_email, role, location_id, session_id,
                            DATE_FORMAT(MIN(created_at), '%H') starthr, DATE_FORMAT(MAX(created_at), '%H') endhr
                        FROM {$tablePrefix}activities WHERE 1=1
                            AND created_at BETWEEN '" . $start_date . "' AND '" . $end_date . "'
                            AND location_id = '" . $current_mall . "'
                            AND (activity_name = 'login_ok' OR activity_name = 'logout_ok')
                            AND role = 'Consumer'
                            AND session_id IS NOT NULL
                        GROUP BY session_id
                    )  as A
                ") );

            // Get different times
            $datetime1 = new DateTime($start_date);
            $datetime2 = new DateTime($end_date);

            $interval = $datetime1->diff($datetime2);
            $totalRangeDays = $interval->format('%a') + 1;

            // Re-Format for response result
            $dataArray = array();
            for ($i=0; $i <= 23 ; $i++) {
                $starttime = $i;
                $endtime = $i + 1;
                if ( $starttime < 10 ) {
                    $starttime = '0'.$i;
                }
                if ( $endtime < 10) {
                    $endtime = '0'.$endtime;
                }

                $dataArray[$i]['start_time'] = $starttime.':00';
                $dataArray[$i]['end_time'] = $endtime.':00';
                $dataArray[$i]['score'] = round($activities[0]->$i / $totalRangeDays, 2);
            }

            $this->response->data = [
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'hourly_record' => $dataArray,
            ];
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.activity.getcustomerconnectedhourly.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.activity.getcustomerconnectedhourly.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.activity.getcustomerconnectedhourly.query.error', array($this, $e));

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
            Event::fire('orbit.activity.getcustomerconnectedhourly.general.exception', array($this, $e));

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
        Event::fire('orbit.activity.getcustomerconnectedhourly.before.render', array($this, &$output));

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

        // Check exist merchant
        Validator::extend('orbit.empty.merchant', function ($attribute, $value, $parameters) {
            $merchant = Mall::excludeDeleted()
                        ->where('merchant_id', $value)
                        ->where('is_mall', 'yes')
                        ->first();
            if (empty($merchant)) {
                return FALSE;
            }

            App::instance('orbit.empty.merchant', $merchant);

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
        $mall_group = MallGroup::excludeDeleted()->where('user_id', '=', $user->user_id)->first(); // todo get() ?
        if (isset($mall_group)) {
            $malls = Mall::excludeDeleted()
                         ->where('parent_id', '=', $mall_group->merchant_id)
                         ->where('is_mall', '=', 'yes');
            OrbitInput::get('location_ids', function($locationIds) use ($malls) {
                $malls->whereIn('merchant_id', $locationIds);
            });
            return $malls->lists('merchant_id');
        } elseif ($user->isMallOwner()) {
            $mall = Mall::excludeDeleted()->where('user_id', '=', $user->user_id)->first();
            if (isset($mall)) {
                return [$mall->merchant_id];
            } else {
                return [-1]; // ensure no results
            }
        } elseif ($user->isMallAdmin()) {
            $mall = $user->employee->retailers->first();
            if (empty($mall)) {
                return [-1]; // ensure no results
            } else {
                return [$mall->merchant_id];
            }
        }
    }

    public function categorizeUserAgent($ua)
    {
        if (preg_match('/Linux.*?Android/', $ua)) {
            // not "Windows Phone 10.0; Android"...
            return 'android';
        } elseif (preg_match('/\((iPhone|iPod|iPad)/', $ua)) {
            return 'ios';
        } elseif (preg_match('/BlackBerry/', $ua)) {
            return 'blackberry';
        } elseif (preg_match('/Windows Phone/i', $ua)) {
            return 'windows_phone';
        } else {
            return 'other';
        }
    }

    public function calculateAge($birth_date, $today_date, $today_year)
    {
        if ($birth_date === null) {
            return null;
        }
        $birth_year = (int)substr($birth_date, 0, 4);
        $age = $today_year - $birth_year;
        if (substr($birth_date, 5) < $today_date) {
            $age -= 1;
        }
        return $age;
    }

    public function setReturnQuery($bool) {
        $this->returnQuery = $bool;
    }

}
