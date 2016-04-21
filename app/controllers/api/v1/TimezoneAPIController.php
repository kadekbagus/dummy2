<?php
/**
 * An API controller for managing Campaign Locations.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Helper\EloquentRecordCounter as RecordCounter;

class TimezoneAPIController extends ControllerAPI
{
    protected $viewRoles = ['super admin', 'mall admin', 'mall owner', 'campaign owner', 'campaign employee', 'mall customer service', 'campaign admin'];

    /**
     * GET - Get Timezone
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string   `campaign_id            (required) - Campaign id (news_id, promotion_id, coupon_id)
     * @param string   `campaign_type          (required) - news, promotion, coupon
     *
     * @return Illuminate\Support\Facades\Response
     */

    public function getTimezone()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.timezone.gettimezone.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.timezone.gettimezone.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.timezone.gettimezone.before.authz', array($this, $user));

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->viewRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.timezone.gettimezone.after.authz', array($this, $user));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.coupon.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }

            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.coupon.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            $timezone = Timezone::select('timezone_id','timezone_name');

            // Filter timezone by timezone name
            OrbitInput::get('timezone_name', function($timezone_name) use ($timezone)
            {
                $timezone->where('timezone_name', 'like', "%$timezone_name%");
            });


            $_timezone = clone $timezone;

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
            $timezone->take($take);

            $skip = 0;
            OrbitInput::get('skip', function($_skip) use (&$skip, $timezone)
            {
                if ($_skip < 0) {
                    $_skip = 0;
                }
                $skip = $_skip;
            });
            $timezone->skip($skip);

            $listOftimezone = $timezone->get();

            $totaltimezone = RecordCounter::create($_timezone)->count();
            $totalReturnedRecords = count($listOftimezone);

            $data = new stdclass();
            $data->total_records = $totaltimezone;
            $data->returned_records = $totalReturnedRecords;
            $data->records = $listOftimezone;

            if ($totaltimezone === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.timezone');
            }

            $this->response->data = $data;

        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.timezone.gettimezone.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.timezone.gettimezone.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 400;
        } catch (QueryException $e) {
            Event::fire('orbit.timezone.gettimezone.query.error', array($this, $e));

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
            Event::fire('orbit.timezone.gettimezone.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = 'null';
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.timezone.gettimezone.before.render', array($this, &$output));

        return $output;
    }
}