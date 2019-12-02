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

class PulsaReportUserDailyAPIController extends ControllerAPI
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
			$userId = OrbitInput::get('user_id');

			$validator = Validator::make(
                array(
                    'start_date' => $startDateInput,
                    'end_date' => $endDateInput,
                    'user_id' => $userId,
                ),
                array(
                    'start_date' => 'required|date_format:Y-m-d',
                    'end_date' => 'required|date_format:Y-m-d',
                    'user_id' => 'required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                throw new Exception($errorMessage, 1);
            }

            $startDate = new Carbon($startDateInput);
			$endDate = new Carbon($endDateInput);
			$startDateMinOneDay = $startDate->subDay();
			$take = $endDate->diffInDays($startDateMinOneDay);
			$skip = 0;

			// query
			$prefix = DB::getTablePrefix();
			$result = DB::select(
				DB::raw(
					"select
						start_date as dt,
					    CASE WHEN total_amount IS NULL THEN '0' ELSE total_amount END as total_amount,
					    CASE WHEN counter IS NULL THEN '0' ELSE counter END as total_transactions
					from (
						select
							@startDate := {$this->quote($startDateMinOneDay)},
							DATE_FORMAT(DATE_ADD(@startDate, INTERVAL sequence_number DAY), '%Y-%m-%d 00:00:00') AS start_date
						from {$prefix}sequence
					) as p1
					left join
						(
							SELECT
								SUM(amount) AS total_amount,
								COUNT(pt.payment_transaction_id) AS counter,
								DATE_FORMAT(pt.updated_at, '%Y-%m-%d 00:00:00') AS view_date
							FROM
								{$prefix}payment_transactions pt
							LEFT JOIN
								{$prefix}payment_transaction_details ptd ON ptd.payment_transaction_id = pt.payment_transaction_id
							WHERE
								pt.user_id = {$this->quote($userId)}
									AND pt.status = 'success'
					                AND ptd.object_type = 'pulsa'
							GROUP BY view_date, user_id
							ORDER BY view_date DESC
						) as p2
						on p1.start_date = p2.view_date
					limit {$skip}, {$take}"
				)
			);

			$data = new stdClass();
			$data->records = $result;

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
