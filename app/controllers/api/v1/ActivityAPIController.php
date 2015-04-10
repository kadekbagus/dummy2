<?php
/**
 * An API controller for managing history which happens on Orbit.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
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

            if (! ACL::create($user)->isAllowed('view_activity')) {
                Event::fire('orbit.activity.getactivity.authz.notallowed', array($this, $user));

                $errorMessage = Lang::get('validation.orbit.actionlist.view_activity');
                $message = Lang::get('validation.orbit.access.view_activity', array('action' => $errorMessage));

                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.activity.getactivity.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sortby');
            $start_date = OrbitInput::get('start_date');
            $end_date = OrbitInput::get('end_date');

            $tomorrow = date('Y-m-d', strtotime('tomorrow'));
            $validator = Validator::make(
                array(
                    'sort_by'       => $sort_by,
                    'merchant_ids'  => OrbitInput::get('merchant_ids'),
                    'start_date'     => $start_date,
                    'end_date'      => $end_date
                ),
                array(
                    'sort_by'       => 'in:id,ip_address,created,registered_at,email,full_name,object_name,product_name,coupon_name,promotion_name,event_name,action_name,action_name_long,activity_type,gender,staff_name,module_name',
                    'merchant_ids'  => 'orbit.check.merchants',
                    'start_date'    => 'date_format:Y-m-d|before:' . $tomorrow,
                    'end_date'      => 'date_format:Y-m-d|before:' . $tomorrow,
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
            $activities = Activity::with($with)->select('activities.*',
                                                        DB::Raw("DATE_FORMAT({$tablePrefix}activities.created_at, '%d-%m-%Y %H:%i:%s') as created_at_reverse"));

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

            // Filter by groups
            if (! empty($_GET['groups'])) {
                OrbitInput::get('groups', function($groups) use ($activities) {
                    $activities->whereIn('activities.group', $groups);
                });
            } else {
                $activities->whereIn('activities.group', ['mobile-ci', 'pos']);
            }

            // Filter by role_ids
            OrbitInput::get('role_ids', function($roleIds) use ($activities) {
                $activities->whereIn('activities.role_id', $roleIds);
            });

            // Filter by object ids
            OrbitInput::get('object_ids', function($objectIds) use ($activities) {
                $activities->whereIn('activities.object_id', $roleIds);
            });

            // Filter by object names
            OrbitInput::get('object_names', function($names) use ($activities) {
                $activities->whereIn('activities.object_name', $names);
            });

            OrbitInput::get('product_names', function($names) use ($activities) {
                $activities->whereIn('activities.product_name', $names);
            });

            OrbitInput::get('promotion_names', function($names) use ($activities) {
                $activities->whereIn('activities.promotion_name', $names);
            });

            OrbitInput::get('coupon_names', function($names) use ($activities) {
                $activities->whereIn('activities.coupon_name', $names);
            });

            OrbitInput::get('event_names', function($names) use ($activities) {
                $activities->whereIn('activities.event_name', $names);
            });

            // Filter by staff Ids
            OrbitInput::get('staff_ids', function($staff) use ($activities) {
                $activities->whereIn('activities.staff_id', $staff);
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
                $activities->whereIn('activities.location_id', $locationIds);
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
            $sortBy = 'activities.created_at';
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
                    'event_name'        => 'activities.event_name',
                    'action_name'       => 'activities.activity_name',
                    'action_name_long'  => 'activities.activity_name_long',
                    'activity_type'     => 'activities.activity_type',
                    'staff_name'        => 'activities.staff_name',
                    'gender'            => 'activities.gender',
                    'module_name'       => 'activities.module_name',
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

            if ($listOfActivities === 0) {
                $data->records = null;
                $this->response->message = Lang::get('statuses.orbit.nodata.attribute');
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

    protected function registerCustomValidation()
    {
        $user = $this->api->user;
        Validator::extend('orbit.check.merchants', function ($attribute, $value, $parameters) use ($user) {
            $merchants = Merchant::excludeDeleted()
                        ->allowedForUser($user)
                        ->whereIn('merchant_id', $value)
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
}
