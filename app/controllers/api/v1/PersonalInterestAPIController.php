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

class PersonalInterestAPIController extends ControllerAPI
{
    /**
     * GET - List of Personal Interests.
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param array         `personal_interest_ids` (optional) - List of widget IDs
     * @param array         `user_ids`              (optional) - List of interests for particular user ids
     * @param array         `with`                  (optional) - relationship included
     * @param integer       `take`                  (optional) - limit
     * @param integer       `skip`                  (optional) - limit offset
     * @param string        `sort_by`               (optional) - column order by
     * @param string        `sort_mode`             (optional) - asc or desc
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchPersonalInterest()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.personalinterest.getpersonalinterest.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.personalinterest.getpersonalinterest.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.personalinterest.getpersonalinterest.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('view_personal_interest')) {
                Event::fire('orbit.personalinterest.getpersonalinterest.authz.notallowed', array($this, $user));

                $errorMessage = Lang::get('validation.orbit.actionlist.view_personal_interest');
                $message = Lang::get('validation.orbit.access.view_personal_interest', array('action' => $errorMessage));

                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.personalinterest.getpersonalinterest.after.authz', array($this, $user));

            $validator = Validator::make(
                array(
                    'personal_interest_ids'    => OrbitInput::get('widget_ids'),
                ),
                array(
                    'personal_interest_ids'    => 'array|min:1',
                )
            );

            Event::fire('orbit.personalinterest.getpersonalinterest.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.personalinterest.getpersonalinterest.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.personal_interest.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.personal_interest.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            // Builder object
            $interests = PersonalInterest::excludeDeleted();

            // Include other relationship
            OrbitInput::get('with', function($with) use ($interests) {
                $interests->with($with);
            });

            // Filter by ids
            OrbitInput::get('personal_interest_ids', function($widgetIds) use ($interests) {
                $interests->whereIn('personal_interests.personal_interest_id', $widgetIds);
            });

            // Filter by user ids
            OrbitInput::get('user_ids', function($userIds) use ($interests) {
                $interests->userIds($userIds);
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_interests = clone $interests;

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
            $interests->take($take);

            $skip = 0;
            OrbitInput::get('skip', function ($_skip) use (&$skip, $interests) {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $interests->skip($skip);

            // Default sort by
            $sortBy = 'personal_interests.personal_interest_name';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function ($_sortBy) use (&$sortBy) {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'id'            => 'personal_interests.personal_interest_id',
                    'name'          => 'personal_interests.personal_interest_name',
                    'created'       => 'personal_interests.created_at'
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function ($_sortMode) use (&$sortMode) {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });
            $interests->orderBy($sortBy, $sortMode);

            $totalPersonalInterest = RecordCounter::create($_interests)->count();
            $listOfPersonalInterest = $interests->get();

            $data = new stdclass();
            $data->total_records = $totalPersonalInterest;
            $data->returned_records = count($listOfPersonalInterest);
            $data->records = $listOfPersonalInterest;

            if ($totalPersonalInterest === 0) {
                $data->records = null;
                $this->response->message = Lang::get('statuses.orbit.nodata.personalinterest');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.personalinterest.getpersonalinterest.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.personalinterest.getpersonalinterest.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.personalinterest.getpersonalinterest.query.error', array($this, $e));

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
            Event::fire('orbit.personalinterest.getpersonalinterest.general.exception', array($this, $e));

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
        Event::fire('orbit.personalinterest.getpersonalinterest.before.render', array($this, &$output));

        return $output;
    }
}
