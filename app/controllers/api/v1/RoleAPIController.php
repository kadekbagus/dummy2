<?php
/**
 * An API controller for managing personal interest.
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

class RoleAPIController extends ControllerAPI
{
    /**
     * GET - List of Roles.
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param array         `role_ids`              (optional) - List of Role IDs
     * @param array         `role_names`            (optional) - List of Role Name
     * @param array         `with`                  (optional) - relationship included
     * @param integer       `take`                  (optional) - limit
     * @param integer       `skip`                  (optional) - limit offset
     * @param string        `sort_by`               (optional) - column order by
     * @param string        `sort_mode`             (optional) - asc or desc
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchRole()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.role.getrole.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.role.getrole.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.role.getrole.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('view_role')) {
                Event::fire('orbit.role.getrole.authz.notallowed', array($this, $user));

                $errorMessage = Lang::get('validation.orbit.actionlist.view_role');
                $message = Lang::get('validation.orbit.access.view_role', array('action' => $errorMessage));

                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.role.getrole.after.authz', array($this, $user));

            $validator = Validator::make(
                array(
                    'role_ids'      => OrbitInput::get('role_ids'),
                    'role_names'    => OrbitInput::get('role_names'),
                    'with'          => OrbitInput::get('with'),
                ),
                array(
                    'role_ids'      => 'array|min:1',
                    'role_name'     => 'array|min:1',
                    'with'          => 'array|min:1'
                )
            );

            Event::fire('orbit.role.getrole.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.role.getrole.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.role.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.role.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            // Builder object
            $roles = Role::select('roles.*');

            // Include other relationship
            OrbitInput::get('with', function($with) use ($roles) {
                $roles->with($with);
            });

            // Filter by ids
            OrbitInput::get('role_ids', function($ids) use ($roles) {
                $roles->whereIn('roles.role_ids', $ids);
            });

            // Filter by role names
            OrbitInput::get('role_names', function($roleNames) use ($roles) {
                $roles->role_name($roleNames);
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_roles = clone $roles;

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
            $roles->take($take);

            $skip = 0;
            OrbitInput::get('skip', function ($_skip) use (&$skip, $roles) {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $roles->skip($skip);

            // Default sort by
            $sortBy = 'roles.role_name';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function ($_sortBy) use (&$sortBy) {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'id'            => 'roles.role_id',
                    'name'          => 'roles.role_name',
                    'created'       => 'roles.created_at',
                    'registered_at' => 'roles.created_at'
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function ($_sortMode) use (&$sortMode) {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });
            $roles->orderBy($sortBy, $sortMode);

            $totalRole = RecordCounter::create($_roles)->count();
            $listOfRole = $roles->get();

            $data = new stdclass();
            $data->total_records = $totalRole;
            $data->returned_records = count($listOfRole);
            $data->records = $listOfRole;

            if ($totalRole === 0) {
                $data->records = null;
                $this->response->message = Lang::get('statuses.orbit.nodata.role');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.role.getrole.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.role.getrole.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.role.getrole.query.error', array($this, $e));

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
            Event::fire('orbit.role.getrole.general.exception', array($this, $e));

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
        Event::fire('orbit.role.getrole.before.render', array($this, &$output));

        return $output;
    }
}
