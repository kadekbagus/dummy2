<?php namespace Orbit\Controller\API\v1\Merchant;

use OrbitShop\API\v1\ResponseProvider;
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Helper\EloquentRecordCounter as RecordCounter;
use Orbit\Helper\Util\PaginationNumber;
use PaymentProvider;
use Validator;
use Lang;
use DB;
use Config;
use stdclass;

class PaymentProviderListAPIController extends ControllerAPI
{
    protected $merchantViewRoles = ['super admin', 'merchant database admin'];

    /**
     * GET Search Payment Provider
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     */
    public function getSearchPaymentProvider()
    {
        try {
            $httpCode = 200;

            // Require authentication
            $this->checkAuth();

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->merchantViewRoles;
            if (! in_array(strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            $sort_by = OrbitInput::get('sortby');

            $validator = Validator::make(
                array(
                    'sortby' => $sort_by,
                ),
                array(
                    'sortby' => 'in:payment_name',
                ),
                array(
                    'sortby.in' => 'The sort by argument you specified is not valid, the valid values are: payment_name',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $prefix = DB::getTablePrefix();

            $paymentProvider = PaymentProvider::excludedeleted();

            // Filter by payment_name
            OrbitInput::get('payment_name', function($payment_name) use ($paymentProvider)
            {
                $paymentProvider->where('payment_name', $payment_name);
            });

            // Filter by matching name pattern
            OrbitInput::get('payment_name_like', function($payment_name_like) use ($paymentProvider)
            {
                $paymentProvider->where('payment_name', 'like', "%$payment_name_like%");
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_paymentProvider = clone $paymentProvider;

            $take = PaginationNumber::parseTakeFromGet('country');
            $paymentProvider->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $paymentProvider->skip($skip);

            // Default sort by
            $sortBy = 'payment_name';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'payment_name' => 'payment_name'
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
            $paymentProvider->orderBy($sortBy, $sortMode);

            $totalRecord = RecordCounter::create($_paymentProvider)->count();
            $list = $paymentProvider->get();

            $data = new stdclass();
            $data->total_records = $totalRecord;
            $data->returned_records = count($list);
            $data->records = $list;

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {

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

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);

        return $output;
    }
}
