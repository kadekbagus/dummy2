<?php

namespace Orbit\Controller\API\v1\Product\GameVoucherPromotion;

use Exception;
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use GameVoucherPromotion;
use Validator;

/**
 * Get detail of Game Voucher Promotion.
 *
 * @author ahmad <ahmad@gotomalls.com>
 */
class PromotionDetailAPIController extends ControllerAPI
{
    protected $validRoles = ['product manager'];

    public function getDetail ()
    {
        $user = NULL;
        try {
            $httpCode = 200;

            $this->checkAuth();

            $user = $this->api->user;

            $role = $user->role;
            $validRoles = $this->validRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            $validation_data = [
                'game_voucher_promotion_id' => OrbitInput::get('game_voucher_promotion_id')
            ];

            $validation_error = [
                'game_voucher_promotion_id' => 'required',
            ];

            $validator = Validator::make(
                $validation_data,
                $validation_error
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $records = GameVoucherPromotion::with(['details.transaction'])
                ->where('game_voucher_promotion_id', OrbitInput::get('game_voucher_promotion_id'))
                ->where('status', '<>', 'deleted')
                ->firstOrFail();

            foreach ($records->details as $detail) {
                $detail->user_email = null;
                $detail->user_name = null;
                $detail->transaction_date = null;

                if (is_object($detail->transaction)) {
                    $detail->user_email = $detail->transaction->user_email;
                    $detail->user_name = $detail->transaction->user_name;
                    $detail->transaction_date = $detail->transaction->created_at;
                }
                unset($detail->transaction);
            }

            $this->response->data = $records;

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
            if (\Config::get('app.debug')) {
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
            $this->response->data = $e->getLine();
        }

        return $this->render($httpCode);
    }
}
