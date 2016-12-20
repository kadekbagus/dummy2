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
}