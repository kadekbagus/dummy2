<?php namespace Orbit\Controller\API\v1\Pub\DigitalProduct;

use DB;
use Config;
use stdClass;
use Validator;
use \Exception;
use PaymentTransaction;
use DominoPOS\OrbitACL\ACL;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\PubControllerAPI;
use Illuminate\Database\QueryException;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;

class BpjsBillPurchasedDetailAPIController extends PubControllerAPI
{

    public function getBpjsBillPurchasedDetail()
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

            $payment_transaction_id = OrbitInput::get('payment_transaction_id');
            $language = OrbitInput::get('language', 'id');

            $validator = Validator::make(
                array(
                    'language' => $language,
                    'payment_transaction_id' => $payment_transaction_id,
                ),
                array(
                    'language' => 'required',
                    'payment_transaction_id' => 'required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $prefix = DB::getTablePrefix();

            $bpjsBill = PaymentTransaction::select('payment_transactions.payment_transaction_id',
                                            'payment_transactions.amount',
                                            'payment_transactions.currency',
                                            'payment_transactions.status',
                                            'payment_transactions.payment_method',
                                            'payment_transactions.notes',
                                            'payment_transactions.extra_data as customer_id',
                                            'payment_transactions.created_at',
                                            DB::raw("convert_tz({$prefix}payment_transactions.created_at, '+00:00', {$prefix}payment_transactions.timezone_name) as date_tz"),
                                            'payment_transactions.external_payment_transaction_id',
                                            'payment_transaction_details.object_name as product_name',
                                            'payment_transaction_details.object_type',
                                            'payment_transaction_details.provider_product_id',
                                            'payment_transaction_details.price',
                                            'payment_transaction_details.quantity',
                                            'payment_midtrans.payment_midtrans_info',
                                            'digital_products.digital_product_id as item_id',
                                            'payment_transaction_details.payload'
                                            )
                                            ->with(['discount_code' => function($discountCodeQuery) {
                                                $discountCodeQuery->select('payment_transaction_id', 'discount_code_id', 'discount_id', 'discount_code as used_discount_code')->with(['discount' => function($discountDetailQuery) {
                                                    $discountDetailQuery->select('discount_id', 'discount_code as parent_discount_code', 'discount_title', 'value_in_percent as percent_discount');
                                                }]);
                                            }])
                                            ->with(['discount' => function($discountQuery) {
                                                $discountQuery->select('payment_transaction_id', 'object_id', 'price as discount_amount')->with(['discount' => function($discountQuery) {
                                                    $discountQuery->select('discount_id', 'discount_code as parent_discount_code', 'discount_title', 'value_in_percent as percent_discount');
                                                }]);
                                            }])
                                            ->with(['auto_issued_coupons'])
                                        ->join('payment_transaction_details', 'payment_transaction_details.payment_transaction_id', '=', 'payment_transactions.payment_transaction_id')
                                        ->join('digital_products', 'digital_products.digital_product_id', '=', 'payment_transaction_details.object_id')
                                        ->leftJoin('payment_midtrans', 'payment_midtrans.payment_transaction_id', '=', 'payment_transactions.payment_transaction_id')
                                        ->where('payment_transactions.user_id', $user->user_id)
                                        ->where('payment_transaction_details.object_type', 'digital_product')
                                        ->where('payment_transactions.payment_method', '!=', 'normal')
                                        ->whereNotIn('payment_transactions.status', array('starting', 'denied', 'abort'))
                                        ->where('digital_products.product_type', 'bpjs_bill')
                                        ->where(function($query) use($payment_transaction_id) {
                                            $query->where('payment_transactions.payment_transaction_id', '=', $payment_transaction_id)
                                                    ->orWhere('payment_transactions.external_payment_transaction_id', '=', $payment_transaction_id);
                                            })
                                        ->first();

            if (! $bpjsBill) {
                OrbitShopAPI::throwInvalidArgument('purchased detail not found');
            }

            $bpjsBill->payment_midtrans_info = json_decode(unserialize($bpjsBill->payment_midtrans_info));
            $bpjsBill->activation_code = unserialize($bpjsBill->payload);

            $bpjsBill->getRewards();

            $this->response->data = $bpjsBill;
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
            $this->response->data = null;
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
