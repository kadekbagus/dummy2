<?php namespace Orbit\Controller\API\v1\MerchantTransaction;

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
use PaymentTransaction;
use Validator;
use Lang;
use DB;
use UserMerchantTransaction;
use Config;
use stdclass;
use Orbit\Controller\API\v1\MerchantTransaction\MerchantTransactionHelper;
use \Carbon\Carbon as Carbon;

class MerchantTransactionReportAPIController extends ControllerAPI
{
    protected $merchantViewRoles = ['super admin', 'merchant transaction admin'];
    /**
     * GET Search Merchant Transactions
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     */

    /**
     * Flag to return the query builder.
     *
     * @var Builder
     */
    protected $returnBuilder = FALSE;

    public function getSearchMerchantTransactionReport()
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

            // Get user type and merchant id
            $merchanOrStoretId = null;
            $userType = null;

            $userMerchantTransaction = UserMerchantTransaction::where('user_id', $user->user_id)->first();
            if (! empty($userMerchantTransaction) > 0) {
                $merchanOrStoretId = $userMerchantTransaction->merchant_id;
                $userType = $userMerchantTransaction->object_type;
            }

            // $merchantHelper = MerchantHelper::create();
            // $merchantHelper->merchantCustomValidator();

            $sort_by = OrbitInput::get('sortby');

            $validator = Validator::make(
                array(
                    'sortby' => $sort_by,
                ),
                array(
                    'sortby' => 'in:merchant_id,merchant_name,store_id,store_at_building,building_name,object_name,object_id,coupon_redemption_code,created_at,payment_transaction_id,external_payment_transaction_id,payment_method,amount,currency,status',
                ),
                array(
                    'sortby.in' => 'The sort by argument you specified is not valid, the valid values are: merchant_id, merchant_name, store_id, store_at_building, building_name, object_name, object_id, coupon_redemption_code, created_at, payment_transaction_id, external_payment_transaction_id, payment_method, amount, currency, status',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $prefix = DB::getTablePrefix();

            $merchantTransactions = PaymentTransaction::select(
                                                                'merchant_id',
                                                                'merchant_name',
                                                                'store_id',
                                                                DB::raw("CONCAT(store_name,' @ ', building_name) as store_at_building"),
                                                                'building_name',
                                                                'object_name',
                                                                'object_id',
                                                                'coupon_redemption_code',
                                                                DB::raw("DATE_FORMAT(convert_tz( created_at, '+00:00', timezone_name)  , '%d/%m/%Y %H:%i:%s') as date_tz"),
                                                                'payment_transaction_id',
                                                                'external_payment_transaction_id',
                                                                'payment_method',
                                                                'amount',
                                                                'currency',
                                                                'status'
                                                            );

            // Filtering by user store or merchant
            if ($userType === 'merchant') {
                $merchantTransactions->where('merchant_id', $merchanOrStoretId);
            } elseif ($userType === 'store') {
                $merchantTransactions->where('store_id', $merchanOrStoretId);
            }

            // Filtering
            OrbitInput::get('payment_transaction_id', function($payment_transaction_id) use ($merchantTransactions)
            {
                $merchantTransactions->where('payment_transactions.payment_transaction_id', 'like', "%$payment_transaction_id%");
            });

            OrbitInput::get('object_name', function($object_name) use ($merchantTransactions)
            {
                $merchantTransactions->where('payment_transactions.object_name', 'like', "%$object_name%");
            });

            OrbitInput::get('building_id', function($building_id) use ($merchantTransactions)
            {
                $merchantTransactions->where('payment_transactions.building_id', $building_id);
            });

            OrbitInput::get('building_name', function($building_name) use ($merchantTransactions)
            {
                $merchantTransactions->where('payment_transactions.building_name', 'like', "%$building_name%");
            });

            OrbitInput::get('status', function($status) use ($merchantTransactions)
            {
                $merchantTransactions->where('payment_transactions.status', $status);
            });

            OrbitInput::get('merchant_id', function($merchant_id) use ($merchantTransactions)
            {
                $merchantTransactions->where('payment_transactions.merchant_id', $merchant_id);
            });

            OrbitInput::get('payment_method', function($payment_method) use ($merchantTransactions)
            {
                $merchantTransactions->where('payment_transactions.payment_method', 'like', "%$payment_method%");
            });

            $start_date = OrbitInput::get('start_date');
            $end_date = OrbitInput::get('end_date');

            if ($start_date != '' && $end_date != ''){
                $merchantTransactions->where(function ($q) use ($start_date, $end_date) {
                    $q->WhereRaw("DATE_FORMAT({$this->quote($start_date)}, '%Y-%m-%d %H:%i:%s') <= convert_tz( created_at, '+00:00', timezone_name) and DATE_FORMAT({$this->quote($end_date)}, '%Y-%m-%d %H:%i:%s') >= convert_tz( created_at, '+00:00', timezone_name)");
                });
            }

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_merchantTransactions = clone $merchantTransactions;

            $take = PaginationNumber::parseTakeFromGet('merchant');
            $merchantTransactions->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $merchantTransactions->skip($skip);

            // Default sort by
            $sortBy = 'payment_transactions.created_at';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'merchant_id' => 'merchant_id',
                    'merchant_name' => 'merchant_name',
                    'store_id' => 'store_id',
                    'store_at_building' => 'store_at_building',
                    'building_name' => 'building_name',
                    'object_name' => 'object_name',
                    'object_id' => 'object_id',
                    'coupon_redemption_code' => 'coupon_redemption_code',
                    'created_at' => 'date_tz',
                    'payment_transaction_id' => 'payment_transaction_id',
                    'external_payment_transaction_id' => 'external_payment_transaction_id',
                    'payment_method' => 'payment_method',
                    'amount' => 'amount',
                    'currency' => 'currency',
                    'status' => 'status'
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
            $merchantTransactions->orderBy($sortBy, $sortMode);

            $totalMerchantTransactions = RecordCounter::create($_merchantTransactions)->count();

            // Return the instance of Query Builder
            if ($this->returnBuilder) {
                return [
                            'builder' => $merchantTransactions,
                            'count' => $totalMerchantTransactions
                        ];
            }

            $listOfMerchants = $merchantTransactions->get();

            $data = new stdclass();
            $data->total_records = $totalMerchantTransactions;
            $data->returned_records = count($listOfMerchants);
            $data->records = $listOfMerchants;

            if ($totalMerchantTransactions === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.merchant');
            }

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

    public function setReturnBuilder($bool)
    {
        $this->returnBuilder = $bool;

        return $this;
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }

}
