<?php
/**
 * An API controller for managing Affected Group Name.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Helper\EloquentRecordCounter as RecordCounter;

class AffectedGroupNameAPIController extends ControllerAPI
{
    protected $viewAffectedGroupNameRoles = ['super admin', 'mall admin', 'mall owner'];
    protected $viewPartnerAffectedGroupRoles = ['super admin', 'mall admin', 'mall owner', 'campaign owner', 'campaign employee', 'mall customer service', 'campaign admin'];

    /**
     * GET - Search Affected Group Name
     *
     * @author Irianto <irianto@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string            `sort_by`                       (optional) - column order by
     * @param string            `sort_mode`                     (optional) - asc or desc
     * @param integer           `take`                          (optional) - limit
     * @param integer           `skip`                          (optional) - limit offset
     * @param string|array      `with`                          (optional) - Relation which need to be included
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchAffectedGroupName()
    {
        $httpCode = 200;
        try {

            Event::fire('orbit.affectedgroupname.getsearchaffectedgroupname.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.affectedgroupname.getsearchaffectedgroupname.after.auth', array($this));

            // Try to check access control list, does this mall allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.affectedgroupname.getsearchaffectedgroupname.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('view_mall')) {
                Event::fire('orbit.affectedgroupname.getsearchaffectedgroupname.authz.notallowed', array($this, $user));
                $viewUserLang = Lang::get('validation.orbit.actionlist.view_mall');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewUserLang));
                ACL::throwAccessForbidden($message);
            }
*/
            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->viewAffectedGroupNameRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.affectedgroupname.getsearchaffectedgroupname.after.authz', array($this, $user));

            $sort_by = OrbitInput::get('sortby');

            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                ),
                array(
                    'sort_by' => 'in:group_name,group_order,status',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.affectedgroupname_sortby'),
                )
            );

            Event::fire('orbit.affectedgroupname.getsearchaffectedgroupname.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            Event::fire('orbit.affectedgroupname.getsearchaffectedgroupname.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.affected_group_name.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.affected_group_name.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            $prefix = DB::getTablePrefix();

            $affected_group_names = AffectedGroupName::excludeDeleted('affected_group_names')
                        ->select(
                            'affected_group_names.affected_group_name_id',
                            'affected_group_names.group_name'
                        );

            // Filter affected group names by Ids
            OrbitInput::get('affected_group_name_id', function ($affectedGroupNameIds) use ($affected_group_names) {
                $affected_group_names->whereIn('affected_group_names.affected_group_name_id', $affectedGroupNameIds);
            });

            // Filter affected group names by name
            OrbitInput::get('group_name', function ($group_name) use ($affected_group_names) {
                $affected_group_names->where('affected_group_names.group_name', $group_name);
            });

            // Filter affected group names by name like
            OrbitInput::get('group_name_like', function ($group_name) use ($affected_group_names) {
                $affected_group_names->where('affected_group_names.group_name', 'like', "%{$group_name}%");
            });

            // Filter affected group names by status
            OrbitInput::get('status', function ($status) use ($affected_group_names) {
                $affected_group_names->where('affected_group_names.status', $status);
            });

            // Filter affected group names by partner_id
            OrbitInput::get('partner_id', function ($partner_id) use ($affected_group_names, $prefix) {
                $affected_group_names->join('partner_affected_group', 'partner_affected_group.affected_group_name_id', '=', 'affected_group_names.affected_group_name_id')
                    ->leftJoin('object_partner', function ($qJoin) use ($prefix) {
                        $qJoin->on('object_partner.partner_id', '=', 'partner_affected_group.partner_id')
                            ->on('object_partner.object_type', '=', DB::raw("{$prefix}affected_group_names.group_type"));
                    })
                    ->addSelect(DB::raw('count(object_type) as item_count'))
                    ->where('partner_affected_group.partner_id', $partner_id)
                    ->groupBy('object_partner.partner_id', 'group_name');
            });

            // Add new relation based on request
            OrbitInput::get('with', function ($with) use ($affected_group_names) {
                $with = (array) $with;

                foreach ($with as $relation) {
                    $affected_group_names->with($relation);
                }
            });


            $_affected_group_names = clone $affected_group_names;

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

            $affected_group_names->take($take);

            $skip = 0;
            OrbitInput::get('skip', function ($_skip) use (&$skip, $affected_group_names) {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $affected_group_names->skip($skip);

            // Default sort by
            $sortBy = 'affected_group_names.group_order';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function ($_sortBy) use (&$sortBy) {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'group_order' => 'affected_group_names.group_order',
                    'group_name'  => 'affected_group_names.group_name',
                    'status'      => 'affected_group_names.status',
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function ($_sortMode) use (&$sortMode) {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });
            $affected_group_names->orderBy($sortBy, $sortMode);

            $totalRec = RecordCounter::create($_affected_group_names)->count();
            $listOfRec = $affected_group_names->get();

            $data = new stdclass();
            $data->total_records = $totalRec;
            $data->returned_records = count($listOfRec);
            $data->records = $listOfRec;

            if ($totalRec === 0) {
                $data->records = null;
                $this->response->message = Lang::get('statuses.orbit.nodata.affectedgroupname');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.affectedgroupname.getsearchaffectedgroupname.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.affectedgroupname.getsearchaffectedgroupname.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.affectedgroupname.getsearchaffectedgroupname.query.error', array($this, $e));

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
            Event::fire('orbit.affectedgroupname.getsearchaffectedgroupname.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $httpCode = 500;
        }
        $output = $this->render($httpCode);

        Event::fire('orbit.affectedgroupname.getsearchaffectedgroupname.before.render', array($this, &$output));

        return $output;
    }


    /**
     * GET - Partner list based on affected group
     *
     * @author kadek <kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string            `sort_by`       (optional) - column order by
     * @param string            `sort_mode`     (optional) - asc or desc
     * @param string            `group_name`    (optional) - 'promotions','coupons','events', 'malls', 'stores'
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchPartnerAffectedGroup()
    {
        $httpCode = 200;
        try {

            Event::fire('orbit.affectedgroupname.getsearchpartneraffectedgroup.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.affectedgroupname.getsearchpartneraffectedgroup.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.affectedgroupname.getsearchpartneraffectedgroup.before.authz', array($this, $user));

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->viewPartnerAffectedGroupRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.affectedgroupname.getsearchpartneraffectedgroup.after.authz', array($this, $user));

            $sort_by = OrbitInput::get('sortby');
            $group_name = OrbitInput::get('group_name');

            $validator = Validator::make(
                array(
                    'sortby'     => $sort_by,
                    'group_name' => $group_name,
                ),
                array(
                    'sortby'     => 'in:partner_id,partner_name,partner_city,partner_start_date,partner_end_date,partner_created_at,partner_updated_at',
                    'group_name' => 'required|in:promotions,events,coupons,malls,stores',
                ),
                array(
                    'sortby.in' => Lang::get('validation.orbit.empty.retailer_sortby'),
                )
            );

            Event::fire('orbit.affectedgroupname.getsearchpartneraffectedgroup.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.affectedgroupname.getsearchpartneraffectedgroup.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.affected_group_name.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.affected_group_name.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            $prefix = DB::getTablePrefix();

            $partners = Partner::excludeDeleted('partners')
                                ->select('partners.partner_id', 'partners.partner_name')
                                ->join('partner_affected_group', 'partner_affected_group.partner_id', '=', 'partners.partner_id');

            // filter group_name
            if ($group_name === 'promotions') {
                $partners->join('affected_group_names', function($join) {
                        $join->on('affected_group_names.affected_group_name_id', '=', 'partner_affected_group.affected_group_name_id')
                             ->where('affected_group_names.group_type', '=', 'promotion');
                    });
            } else if ($group_name === 'events') {
                $partners->join('affected_group_names', function($join) {
                        $join->on('affected_group_names.affected_group_name_id', '=', 'partner_affected_group.affected_group_name_id')
                             ->where('affected_group_names.group_type', '=', 'news');
                    });
            } else if ($group_name === 'coupons') {
                $partners->join('affected_group_names', function($join) {
                        $join->on('affected_group_names.affected_group_name_id', '=', 'partner_affected_group.affected_group_name_id')
                             ->where('affected_group_names.group_type', '=', 'coupon');
                    });
            } else if ($group_name === 'malls') {
                $partners->join('affected_group_names', function($join) {
                        $join->on('affected_group_names.affected_group_name_id', '=', 'partner_affected_group.affected_group_name_id')
                             ->where('affected_group_names.group_type', '=', 'mall');
                    });
            } else if ($group_name === 'stores') {
                $partners->join('affected_group_names', function($join) {
                        $join->on('affected_group_names.affected_group_name_id', '=', 'partner_affected_group.affected_group_name_id')
                             ->where('affected_group_names.group_type', '=', 'tenant');
                });
            }

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_partners = clone $partners;

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

            $partners->take($take);

            $skip = 0;
            OrbitInput::get('skip', function ($_skip) use (&$skip, $partners) {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $partners->skip($skip);

            // Default sort by
            $sortBy = 'partner_name';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'partner_id'         => 'partners.partner_id',
                    'partner_name'       => 'partners.partner_name',
                    'partner_city'       => 'partners.city',
                    'partner_start_date' => 'partners.start_date',
                    'partner_end_date'   => 'partners.end_date',
                    'partner_created_at' => 'partners.created_at',
                    'partner_updated_at' => 'partners.updated_at',
                );

                if (array_key_exists($_sortBy, $sortByMapping)) {
                    $sortBy = $sortByMapping[$_sortBy];
                }
            });

            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });
            $partners->orderBy($sortBy, $sortMode);

            $totalPartners = RecordCounter::create($_partners)->count();
            $listOfPartners = $partners->get();

            $data = new stdclass();
            $data->total_records = $totalPartners;
            $data->returned_records = count($listOfPartners);
            $data->records = $listOfPartners;

            if ($totalPartners === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.partner');
            }

            $this->response->data = $data;

        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.affectedgroupname.getsearchpartneraffectedgroup.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.affectedgroupname.getsearchpartneraffectedgroup.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.affectedgroupname.getsearchpartneraffectedgroup.query.error', array($this, $e));

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
            Event::fire('orbit.affectedgroupname.getsearchpartneraffectedgroup.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.affectedgroupname.getsearchpartneraffectedgroup.before.render', array($this, &$output));

        return $output;
    }
}