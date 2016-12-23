<?php namespace Orbit\Controller\API\v1\Pub\Coupon;
/**
 * Controller for getting coupon count.
 */
use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Config;
use Lang;
use \Exception;
use IssuedCoupon;

class CouponCountAPIController extends PubControllerAPI
{
    public function getCouponCount()
    {
        $httpCode = 200;
        $user = NULL;

        try{
            $this->checkAuth();

            $user = $this->api->user;

            $role = $user->role->role_name;

            if (strtolower($role) === 'guest') {
                $couponCount = 0;
            } else {
                $couponCount = IssuedCoupon::where('status', 'issued')
                    ->where('user_id', $user->user_id)
                    ->count();
            }

            $this->response->data = $couponCount;
            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Success';

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
}
