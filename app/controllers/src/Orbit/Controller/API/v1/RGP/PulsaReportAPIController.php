<?php
namespace Orbit\Controller\API\v1\RGP;

use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use Illuminate\Database\QueryException;
use Helper\EloquentRecordCounter as RecordCounter;
use Validator;
use Config;
use PaymentTransaction;
use stdclass;

class PulsaReportAPIController extends ControllerAPI
{
	public function getAllTransactionsPerDay()
	{
		try {
			$httpCode = 200;
			// @todo: authenticate
			// $sessionKey = Config::get('orbit.session.app_list.rgp_portal', 'X-OMS-RGP');
			// $sessionString = OrbitInput::get($sessionKey);

			// $session = Session::where('session_id', $sessionString)
			// 	->firstOrFail();

			// if (! is_object($session)) {
			// 	throw new Exception("You need to login to continue.", 1);
			// }

			// $sessionData = unserialize($session->session_data);


			// @todo: get inputs
			$startDateInput = OrbitInput::get('start_date');
			$endDateInput = OrbitInput::get('end_date');
			$startDate = Carbon::parse($startDateInput)->format('Y-m-d');
			$endDate = Carbon::parse($endDateInput)->format('Y-m-d');
			$startDateMinOneDay = $startDate->subDay();
			$take = $endDate->diffInDays($startDateMinOneDay);
			$skip = 0;

			// @todo: validate


			// @todo: query
			$prefix = DB::getTablePrefix();
			$result = DB::raw(
				"select
					start_date as dt,
				    CASE WHEN total_amount IS NULL THEN '0' ELSE total_amount END as total_amount,
				    CASE WHEN total_unique_user IS NULL THEN '0' ELSE total_unique_user END as total_unique_user,
				    CASE WHEN counter IS NULL THEN '0' ELSE counter END as total_transactions
				from (
					select
						@startDate := '{$startDate}',
						DATE_FORMAT(DATE_ADD(@startDate, INTERVAL sequence_number DAY), '%Y-%m-%d 00:00:00') AS start_date
					from {$prefix}sequence
				) as p1
				left join
					(
						SELECT
							SUM(amount) AS total_amount,
							COUNT(pt.payment_transaction_id) AS counter,
							DATE_FORMAT(pt.created_at, '%Y-%m-%d 00:00:00') AS view_date,
				            count(distinct user_id) as total_unique_user
						FROM
							{$prefix}payment_transactions pt
						LEFT JOIN
							{$prefix}payment_transaction_details ptd
				            ON ptd.payment_transaction_id = pt.payment_transaction_id
						WHERE
							status = 'success'
							AND ptd.object_type = 'pulsa'
						GROUP BY view_date
						ORDER BY view_date DESC
					) as p2
					on p1.start_date = p2.view_date
				limit {$skip}, {$take}"
			);

			$this->response->data = $result;
			// @todo: response
		} catch (Exception $e) {
			$this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 500;
		}

        return $this->render($httpCode);
	}
}
