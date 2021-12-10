<?php namespace Orbit\Controller\API\v1\Pub\DigitalProduct;

use DB;
use Lang;
use Config;
use Activity;
use stdClass;
use Validator;
use \Exception;
use PaymentTransaction;
use DominoPOS\OrbitACL\ACL;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\PubControllerAPI;
use Orbit\Helper\Util\PaginationNumber;
use Illuminate\Database\QueryException;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Helper\EloquentRecordCounter as RecordCounter;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;

class PBBTaxPurchasedListAPIController extends PubControllerAPI
{

    public function getPBBTaxPurchasedList()
    {
        $httpCode = 200;

        try {
            $user = $this->getUser();

            // should always check the role
            $role = $user->role->role_name;
            if (strtolower($role) !== 'consumer') {
                $message = 'You have to login to continue';
                OrbitShopAPI::throwInvalidArgument($message);
            }

            $sort_by = OrbitInput::get('sortby', 'product_name');
            $sort_mode = OrbitInput::get('sortmode','asc');
            $language = OrbitInput::get('language', 'id');

            $validator = Validator::make(
                array(
                    'language' => $language,
                ),
                array(
                    'language' => 'required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $prefix = DB::getTablePrefix();

            $pbbTax = PaymentTransaction::select('payment_transactions.payment_transaction_id',
                                                'payment_transactions.amount',
                                                'payment_transactions.currency',
                                                'payment_transactions.status',
                                                'payment_transactions.notes',
                                                'payment_transactions.payment_method',
                                                'payment_transactions.extra_data as customer_id',
                                                'payment_transactions.created_at',
                                                'payment_transaction_details.object_name as product_name',
                                                'payment_transaction_details.object_type',
                                                'payment_transaction_details.price',
                                                'payment_transaction_details.quantity',
                                                'payment_transaction_details.payload',
                                                'provider_products.provider_name',
                                                DB::raw("(SELECT D.value_in_percent FROM {$prefix}payment_transaction_details PTD
                                                            LEFT JOIN {$prefix}discounts D on D.discount_id = PTD.object_id
                                                            WHERE PTD.object_type = 'discount'
                                                            AND PTD.payment_transaction_id = {$prefix}payment_transactions.payment_transaction_id) as discount_percent"),
                                                DB::raw("(SELECT PTD.price FROM {$prefix}payment_transaction_details PTD
                                                            WHERE PTD.object_type = 'discount'
                                                            AND PTD.payment_transaction_id = {$prefix}payment_transactions.payment_transaction_id) as discount_amount")
                                                )
                                            ->join('payment_transaction_details', 'payment_transaction_details.payment_transaction_id', '=', 'payment_transactions.payment_transaction_id')
                                            ->join('digital_products', 'digital_products.digital_product_id', '=', 'payment_transaction_details.object_id')
                                            ->leftJoin('provider_products', 'payment_transaction_details.provider_product_id', '=', 'provider_products.provider_product_id')
                                            ->where('payment_transactions.user_id', $user->user_id)
                                            ->where('payment_transaction_details.object_type', 'digital_product')
                                            ->where('payment_transactions.payment_method', '!=', 'normal')
                                            ->whereNotIn('payment_transactions.status', array('starting', 'denied', 'abort'))
                                            ->where('digital_products.product_type', 'pbb_tax')
                                            ->groupBy('payment_transactions.payment_transaction_id');


            $pbbTax = $pbbTax->orderBy(DB::raw("{$prefix}payment_transactions.created_at"), 'desc');

            $_pbbTax = clone $pbbTax;

            $take = PaginationNumber::parseTakeFromGet('coupon');
            $pbbTax->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $pbbTax->skip($skip);

            $listPbbTax = $pbbTax->get();
            $count = RecordCounter::create($_pbbTax)->count();

            $this->response->data = new stdClass();
            $this->response->data->total_records = $count;
            $this->response->data->returned_records = count($listPbbTax);
            $this->response->data->records = $listPbbTax;
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
            $httpCode = 500;
        }

        $output = $this->render($httpCode);

        return $output;
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }

}
