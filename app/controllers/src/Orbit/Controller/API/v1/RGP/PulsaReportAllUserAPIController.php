<?php
namespace Orbit\Controller\API\v1\RGP;

use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use Illuminate\Database\QueryException;
use Helper\EloquentRecordCounter as RecordCounter;
use Validator;
use Config;
use stdclass;
use Carbon\Carbon;
use DB;
use Exception;
use RgpUser;

class PulsaReportAllUserAPIController extends ControllerAPI
{
    public function get()
    {
        try {
            $httpCode = 200;
            // authenticate
            $sessionKey = Config::get('orbit.session.app_list.rgp_portal', 'X-OMS-RGP');
            $sessionString = OrbitInput::get($sessionKey);

            if (empty($sessionKey) || empty($sessionString)) {
                throw new Exception("Error Processing Request", 1);
            }

            $session = DB::table('sessions')
                ->where('session_id', $sessionString)
                ->first();

            if (! is_object($session)) {
                throw new Exception("You need to login to continue.", 1);
            }

            $sessionData = unserialize($session->session_data);

            if (! isset($sessionData->value['email'])) {
                throw new Exception("You need to login to continue.", 1);
            }

            $userEmail = $sessionData->value['email'];
            $user = RgpUser::active()->where('email', $userEmail)->first();

            if (! is_object($user)) {
                throw new Exception("You need to login to continue.", 1);
            }

            // get inputs
            $startDateInput = OrbitInput::get('start_date');
            $endDateInput = OrbitInput::get('end_date');
            $page = OrbitInput::get('page', 1);

            $validator = Validator::make(
                array(
                    'start_date' => $startDateInput,
                    'end_date' => $endDateInput,
                ),
                array(
                    'start_date' => 'required|date_format:Y-m-d',
                    'end_date' => 'required|date_format:Y-m-d',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                throw new Exception($errorMessage, 1);
            }

            $startDate = new Carbon($startDateInput);
            $endDate = new Carbon($endDateInput);
            $take = 20;
            $skip = ($page - 1) * $take;

            // query
            $prefix = DB::getTablePrefix();
            $result = DB::select(
                DB::raw(
                    "select user_id, user_email, count(pt.payment_transaction_id) as total_transactions, sum(amount) as total_amount
                    from {$prefix}payment_transactions pt
                    LEFT JOIN
                        {$prefix}payment_transaction_details ptd
                        ON ptd.payment_transaction_id = pt.payment_transaction_id
                    where pt.status = 'success'
                    and ptd.object_type = 'pulsa'
                    and pt.updated_at between {$this->quote($startDate)} and {$this->quote($endDate->endOfDay())}
                    group by user_id
                    order by total_transactions desc
                    limit {$skip}, {$take}"
                )
            );

            $counter = DB::select(
                DB::raw(
                    "select count(user_id) as counter
                    from (
                        select
                            user_id, user_email, count(pt.payment_transaction_id) as total_transactions, sum(amount) as total_amount
                            from {$prefix}payment_transactions pt
                            LEFT JOIN
                                {$prefix}payment_transaction_details ptd
                                ON ptd.payment_transaction_id = pt.payment_transaction_id
                            where pt.status = 'success'
                            and ptd.object_type = 'pulsa'
                            and pt.updated_at between {$this->quote($startDate)} and {$this->quote($endDate->endOfDay())}
                            group by user_id
                    ) as list_query"
                )
            );

            $data = new stdclass();
            $data->records = $result;
            $data->returned_records = count($result);
            $data->total_records = $counter[0]->counter;

            $this->response->data = $data;


        } catch (Exception $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 500;
        }

        return $this->render($httpCode);
    }

    private function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }
}
