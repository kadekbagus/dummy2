<?php
/**
 * An API controller for Advert Placement list.
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

class AdvertPlacementAPIController extends ControllerAPI
{
    /**
     * Flag to return the query builder.
     *
     * @var Builder
     */
    protected $returnBuilder = FALSE;

    protected $viewRoles = ['super admin', 'mall admin', 'mall owner', 'campaign owner', 'campaign employee', 'campaign admin'];
    protected $modifiyRoles = ['super admin', 'mall admin', 'mall owner', 'campaign owner', 'campaign employee'];

    /**
     * GET - Search Advert Placement
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string   `sortby`                (optional) - column order by
     * @param string   `sortmode`              (optional) - asc or desc
     * @param integer  `take`                  (optional) - limit
     * @param integer  `skip`                  (optional) - limit offset
     * @param integer  `advert_placement_id`   (optional) - advert placement id
     * @param integer  `placement_name`        (optional) - placement name
     * @param string   `status`                (optional) - status. Valid value: active, inactive, deleted.
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchAdvertPlacement()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.placement.getsearchadvertplacement.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.placement.getsearchadvertplacement.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.placement.getsearchadvertplacement.before.authz', array($this, $user));

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->viewRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.placement.getsearchadvertplacement.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sortby');
            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                ),
                array(
                    'sort_by' => 'in:advert_placement_id,placement_name,status,created_at,updated_at',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.advert_placement_sortby'),
                )
            );

            Event::fire('orbit.placement.getsearchadvertplacement.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.placement.getsearchadvertplacement.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.placement.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.placement.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            $filterName = OrbitInput::get('placement_name_like', '');

            // Builder object
            $prefix = DB::getTablePrefix();
            $placement = AdvertPlacement::where('status', 'active');

            // Filter advert by Id
            OrbitInput::get('advert_placement_id', function($placementId) use ($placement)
            {
                $placement->where('advert_placement_id', $placementId);
            });

            // Filter advert by advert name
            OrbitInput::get('placement_name', function($placementName) use ($placement)
            {
                $placement->where('placement_name', '=', $placementName);
            });

            // Filter advert by matching advert name pattern
            OrbitInput::get('placement_name_like', function($placementName) use ($placement)
            {
                $placement->where('advert_name', 'like', "%$placementName%");
            });

            // Filter advert by status
            OrbitInput::get('status', function($status) use ($placement)
            {
                $placement->where('status', '=', $status);
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_advert = clone $placement;

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
                $placement->take($take);

                $skip = 0;
                OrbitInput::get('skip', function($_skip) use (&$skip, $placement)
                {
                    if ($_skip < 0) {
                        $_skip = 0;
                    }

                    $skip = $_skip;
                });
                $placement->skip($skip);
            }

            // Default sort by
            $sortBy = 'campaign_status';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'advert_placement_id' => 'advert_placement_id',
                    'placement_name'      => 'placement_name',
                    'created_at'          => 'created_at',
                    'status'              => 'campaign_status'
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });
            $placement->orderBy($sortBy, $sortMode);

            //with name
            if ($sortBy !== 'placement_name') {
                $placement->orderBy('placement_name', 'asc');
            }

            // Return the instance of Query Builder
            if ($this->returnBuilder) {
                return ['builder' => $placement, 'count' => RecordCounter::create($_advert)->count()];
            }

            $totalAdvert = RecordCounter::create($_advert)->count();
            $listOfAdvert = $placement->get();

            $data = new stdclass();
            $data->total_records = $totalAdvert;
            $data->returned_records = count($listOfAdvert);
            $data->records = $listOfAdvert;

            if ($totalAdvert === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.advertplacement');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.placement.getsearchadvertplacement.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.placement.getsearchadvertplacement.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.placement.getsearchadvertplacement.query.error', array($this, $e));

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
            Event::fire('orbit.placement.getsearchadvertplacement.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.placement.getsearchadvertplacement.before.render', array($this, &$output));

        return $output;
    }


    protected function registerCustomValidation()
    {
        // Check the existance of advert placement id
        Validator::extend('orbit.empty.advert_placement_id', function ($attribute, $value, $parameters) {
            $placement = AdvertPlacement::where('status', 'active')
                        ->where('advert_placement_id', $value)
                        ->first();

            if (empty($placement)) {
                return false;
            }

            App::instance('orbit.empty.advert_placement_id', $placement);

            return true;
        });
    }
}