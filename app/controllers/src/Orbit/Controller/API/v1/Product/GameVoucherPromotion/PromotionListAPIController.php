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

/**
 * Get list of Game Voucher Promotion.
 *
 * @author ahmad <ahmad@gotomalls.com>
 */
class PromotionListAPIController extends ControllerAPI
{
    protected $viewRole = ['product manager'];

    public function getList ()
    {
        $user = NULL;
        try {
            $httpCode = 200;

            $this->checkAuth();

            $user = $this->api->user;

            $role = $user->role;
            $validRoles = $this->viewRole;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            $sortByMapping = array(
                'game_voucher_promotion_id' => 'game_voucher_promotion_id',
                'start_date' => 'start_date',
                'end_date' => 'end_date'
            );

            $sortBy = $sortByMapping[OrbitInput::get('sortby', 'game_voucher_promotion_id')];
            $sortMode = OrbitInput::get('sortmode', 'desc');

            $records = GameVoucherPromotion::with(['details'])
                ->where('status', '<>', 'deleted');

            $records->orderBy($sortBy, $sortMode);

            $skip = OrbitInput::get('skip', 0);
            $take = OrbitInput::get('take', 25) >= 25 ? 25 : OrbitInput::get('take', 25);

            $total = clone $records;
            $total = $total->count();
            $records = $records
                ->skip($skip)
                ->take($take)
                ->get();

            $responseData = new \stdclass();
            $responseData->records = $records;
            $responseData->total_records = $total;
            $responseData->returned_records = $records->count();

            $this->response->data = $responseData;

        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
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

            // Rollback the changes
            $this->rollBack();
        } catch (Exception $e) {
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = $e->getLine();

            // Rollback the changes
            $this->rollBack();
        }

        return $this->render($httpCode);
    }
}
