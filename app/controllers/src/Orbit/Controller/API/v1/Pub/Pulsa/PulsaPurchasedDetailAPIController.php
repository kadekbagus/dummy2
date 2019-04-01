<?php namespace Orbit\Controller\API\v1\Pub\Pulsa;

use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Config;
use Pulsa;
use stdClass;
use Orbit\Helper\Util\PaginationNumber;
use DB;
use Validator;
use Activity;
use Mall;
use Lang;
use \Exception;
use Orbit\Helper\Util\CdnUrlGenerator;
use PaymentTransaction;
use Helper\EloquentRecordCounter as RecordCounter;

class PulsaPurchasedDetailAPIController extends PubControllerAPI
{
    /**
     * GET - get detail of pulsa transaction detail
     *
     * @author Zamroni <zamroni@dominopos.com>
     *
     * TODO: refactor
     *
     * List of API Parameters
     * ----------------------
     * @param string sortby
     * @param string sortmode
     * @param string take
     * @param string skip
     * @param string filter_name
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getPulsaPurchasedDetail()
    {
        $httpCode = 200;
        $activity = Activity::mobileci()->setActivityType('view');
        $user = NULL;
        $mall = NULL;

        try {
            $user = $this->getUser();

            // should always check the role
            $role = $user->role->role_name;
            if (strtolower($role) !== 'consumer') {
                $message = 'You have to login to continue';
                OrbitShopAPI::throwInvalidArgument($message);
            }

            $language = OrbitInput::get('language', 'id');
            $payment_transaction_id = OrbitInput::get('payment_transaction_id');
            $pulsaValidator = PulsaValidator::create();
            $pulsaValidator->registerValidator();
            $validator = Validator::make(
                array(
                    'language'               => $language,
                    'payment_transaction_id' => $payment_transaction_id
                ),
                array(
                    'language'               => 'required|orbit.empty.language_default',
                    'payment_transaction_id' => 'required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $valid_language = $pulsaValidator->getValidLanguage();

            $prefix = DB::getTablePrefix();

            $pulsa = PaymentTransaction::select(DB::raw("
                                    {$prefix}payment_transactions.payment_transaction_id,
                                    {$prefix}payment_transactions.external_payment_transaction_id,
                                    {$prefix}payment_transactions.user_name,
                                    {$prefix}payment_transactions.user_email,
                                    {$prefix}payment_transactions.currency,
                                    {$prefix}payment_transactions.amount,
                                    {$prefix}pulsa.price,
                                    FORMAT({$prefix}payment_transactions.amount / {$prefix}pulsa.price, 0) as qty,
                                    {$prefix}payment_transactions.status,
                                    {$prefix}payment_midtrans.payment_midtrans_info,
                                    {$prefix}pulsa.pulsa_item_id as item_id,
                                    {$prefix}telco_operators.name as telco_name,
                                    {$prefix}telco_operators.telco_operator_id,
                                    'pulsa' as item_type,
                                    {$prefix}pulsa.pulsa_display_name AS display_name,
                                    {$prefix}payment_transactions.created_at,
                                    convert_tz( {$prefix}payment_transactions.created_at, '+00:00', {$prefix}payment_transactions.timezone_name) as date_tz,
                                    {$prefix}payment_transactions.payment_method,
                                    CASE WHEN {$prefix}media.cdn_url is null THEN {$prefix}media.path ELSE {$prefix}media.cdn_url END as telco_logo,
                                    {$prefix}payment_transactions.extra_data as phone_number
                            "))

                            ->leftJoin('payment_transaction_details', 'payment_transaction_details.payment_transaction_id', '=', 'payment_transactions.payment_transaction_id')
                            ->leftJoin('payment_midtrans', 'payment_midtrans.payment_transaction_id', '=', 'payment_transactions.payment_transaction_id')
                            ->join('pulsa', 'pulsa.pulsa_item_id', '=', 'payment_transaction_details.object_id')
                            ->join('telco_operators', 'telco_operators.telco_operator_id', '=', 'pulsa.telco_operator_id')
                            ->leftJoin('media', function ($q) {
                                $q->on('media.object_id', '=', 'telco_operators.telco_operator_id');
                                $q->on('media.media_name_long', '=', DB::raw("'telco_operator_logo_orig'"));
                            })
                            ->where('payment_transactions.user_id', $user->user_id)
                            ->where('payment_transaction_details.object_type', 'pulsa')
                            ->where('payment_transactions.payment_method', '!=', 'normal')
                            ->where('pulsa.status', 'active')

                            // payment_transaction_id is value of payment_transaction_id or external_payment_transaction_id
                            ->where(function($query) use($payment_transaction_id) {
                                $query->where('payment_transactions.payment_transaction_id', '=', $payment_transaction_id)
                                      ->orWhere('payment_transactions.external_payment_transaction_id', '=', $payment_transaction_id);
                              })
                            ->first();

            if (!$pulsa) {
                OrbitShopAPI::throwInvalidArgument('purchased detail not found');
            }

            // Fallback to IDR by default?
            if (empty($pulsa->currency)) {
                $pulsa->currency = 'IDR';
            }


            // get Imahe from local when image cdn is null
            if ($pulsa->cdnPath == null) {
                $cdnConfig = Config::get('orbit.cdn');
                $imgUrl = CdnUrlGenerator::create(['cdn' => $cdnConfig], 'cdn');
                $localPath = (! empty($pulsa->localPath)) ? $pulsa->localPath : '';
                $cdnPath = (! empty($pulsa->cdnPath)) ? $pulsa->cdnPath : '';
                $pulsa->cdnPath = $imgUrl->getImageUrl($localPath, $cdnPath);
            }

            $pulsa->payment_midtrans_info = json_decode(unserialize($pulsa->payment_midtrans_info));

            $this->response->data = $pulsa;
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
    //
    // protected function quote($arg)
    // {
    //     return DB::connection()->getPdo()->quote($arg);
    // }
}
