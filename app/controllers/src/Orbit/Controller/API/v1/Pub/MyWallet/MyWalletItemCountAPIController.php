<?php namespace Orbit\Controller\API\v1\Pub\MyWallet;

use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Config;
use Lang;
use \Exception;

class MyWalletItemCountAPIController extends PubControllerAPI
{
    /**
     * GET - get my wallet item counter
     * (my reward, my coupon, and purchased item counter)
     *
     * @author  Zamroni <zamroni@dominopos.com>
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getItemCount()
    {
        $httpCode = 200;
        $user = NULL;

        try {
            $user = $this->getUser();

            // should always check the role
            $role = $user->role->role_name;
            if (strtolower($role) !== 'consumer') {
                $message = 'You have to login to continue';
                OrbitShopAPI::throwInvalidArgument($message);
            }

            $itemCount = [
                (object) [
                    'my_coupon_count' => 0,
                    'my_reward_count' => 0,
                    'my_purchase_count' => 0,
                ],
            ];

            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Request Ok';
            $this->response->data = $itemCount;
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

        $output = $this->render($httpCode);

        return $output;
    }
}
