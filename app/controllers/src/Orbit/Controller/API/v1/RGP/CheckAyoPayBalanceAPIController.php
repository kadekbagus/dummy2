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

class CheckAyoPayBalanceAPIController extends ControllerAPI
{
    /**
     * Check the latest GTM balance in AyoPay
     */
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

            // query
            $prefix = DB::getTablePrefix();
            $result = DB::select(
                DB::raw(
                    "select payload from {$prefix}payment_transactions p
                    left join {$prefix}payment_transaction_details pt
                        on p.payment_transaction_id = pt.payment_transaction_id
                    where object_type = 'digital_product'
                        and status = 'success'
                    order by p.created_at desc
                    limit 1
                    ;"
                )
            );

            $balance = 'N/A';
            if (count($result) > 0) {
                $xmlPayload = $result[0]->payload;
                $payload = @simplexml_load_string($xmlPayload);
                if ($payload instanceof \SimpleXMLElement) {
                    $balance = $payload->saldo->__toString();
                }
            }

            $this->response->data = $balance;

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
