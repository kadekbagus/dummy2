<?php
/**
 * An API controller for managing account type
 * @author Irianto <irianto@dominopos.com>
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

class AccountTypeAPIController extends ControllerAPI
{
    protected $accountTypeViewRoles = ['super admin', 'campaign admin', 'campaign owner', 'campaign employee'];

    /**
     * GET - List of Account Types.
     *
     * @author Irianto <irianto@dominopos.com>
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchAccountType()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.accounttype.getaccounttype.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.accounttype.getaccounttype.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.accounttype.getaccounttype.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('view_account_type')) {
                Event::fire('orbit.accounttype.getaccounttype.authz.notallowed', array($this, $user));

                $errorMessage = Lang::get('validation.orbit.actionlist.view_account_type');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $errorMessage));

                ACL::throwAccessForbidden($message);
            }
*/

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->accountTypeViewRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.accounttype.getaccounttype.after.authz', array($this, $user));

            $maxRecord = (int) Config::get('orbit.pagination.account_type.max_record');
            if ($maxRecord <= 0) {
                $maxRecord = 20;
            }

            $perPage = (int) Config::get('orbit.pagination.account_type.per_page');
            if ($perPage <= 0) {
                $perPage = 20;
            }

            // Builder object
            $account_type = AccountType::excludeDeleted();

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_account_type = clone $account_type;

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
            $account_type->take($take);

            $skip = 0;
            OrbitInput::get('skip', function ($_skip) use (&$skip, $account_type) {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $account_type->skip($skip);

            // Default sort by
            $sortBy = 'account_types.account_order';
            // Default sort mode
            $sortMode = 'asc';

            $account_type->orderBy($sortBy, $sortMode);

            $totalAccountType = RecordCounter::create($_account_type)->count();
            $listOfAccountType = $account_type->get();

            $data = new stdclass();
            $data->total_records = $totalAccountType;
            $data->returned_records = count($listOfAccountType);
            $data->records = $listOfAccountType;

            if ($totalAccountType === 0) {
                $data->records = null;
                $this->response->message = 'No account type available.';
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.accounttype.getaccounttype.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.accounttype.getaccounttype.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.accounttype.getaccounttype.query.error', array($this, $e));

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
            Event::fire('orbit.accounttype.getaccounttype.general.exception', array($this, $e));

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
        Event::fire('orbit.accounttype.getaccounttype.before.render', array($this, &$output));

        return $output;
    }
}