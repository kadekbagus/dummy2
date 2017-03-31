<?php

use DominoPOS\OrbitACL\ACL;
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Helper\EloquentRecordCounter as RecordCounter;

/**
 * Controller to handle listing reward codes.
 *
 * Read only methods do not check ACL.
 */
class RewardDetailAPIController extends ControllerAPI
{
    protected $rewardDetailViewRoles = ['super admin', 'mall admin', 'mall owner', 'campaign owner', 'campaign employee', 'campaign admin'];

    /**
     * Returns reward detail codes.
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchRewardDetailCode()
    {
        $httpCode = 200;
        try {
            $this->checkAuth();

            // Try to check access control list, does this user allowed to
            // perform this action
            $user_login = $this->api->user;

            // @Todo: Use ACL authentication instead
            $user_role = $user_login->role;

            $validRoles = $this->rewardDetailViewRoles;
            if (! in_array( strtolower($user_role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            $this->registerCustomValidation();

            $reward_detail_id = OrbitInput::get('reward_detail_id');

            $validator_value = ['reward_detail_id' => $reward_detail_id];
            $validator_check = ['reward_detail_id' => 'required|orbit.empty.reward_detail'];
            $validator_message = ['orbit.empty.reward_detail' => 'not found reward detail'];

            $validator = Validator::make(
                $validator_value,
                $validator_check,
                $validator_message
            );

            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $prefix = DB::getTablePrefix();
            $reward_codes = RewardDetailCode::where('reward_detail_id', '=', $reward_detail_id);

            OrbitInput::get('status', function($status) use ($reward_codes) {
                $reward_codes->where('reward_detail_codes.status', '=', $status);
            });

            $_reward_codes = clone $reward_codes;

            $list_reward_codes = $reward_codes->get();
            $count = RecordCounter::create($_reward_codes)->count();

            $this->response->data = new stdClass();
            $this->response->data->total_records = $count;
            $this->response->data->returned_records = count($list_reward_codes);
            $this->response->data->records = $list_reward_codes;
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

        return $this->render($httpCode);
    }

    private function registerCustomValidation()
    {
        Validator::extend('orbit.empty.reward_detail', function ($attribute, $value, $parameters) {
            $reward_detail = RewardDetail::where('reward_detail_id', '=', $value)->first();
            if (empty($reward_detail)) {
                return false;
            }
            App::instance('orbit.empty.reward_detail', $reward_detail);
            return true;
        });
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }

}
