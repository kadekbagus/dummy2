<?php
/**
 * Internal integration controllers to act like external system which
 * being called (notified) by orbit. Such as on /orbit-notify/v1/check-member
 * and friends.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use Net\MacAddr;

class InternalIntegrationAPIController extends ControllerAPI
{
    /**
     * Act as a handler for /orbit-notify/check-member.
     *
     * Expect to retrieve POST data:
     *   - user_id
     *   - user_email
     *   - created_at
     *
     * Prepare the response as:
     *   - user_id
     *   - external_user_id
     *   - user_email
     *   - user_firstname
     *   - user_lastname
     *   - membership_number
     *   - membership_since
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return ResponseProvider
     */
    public function NotifyCheckMemberHandler()
    {
        try {
            $httpCode = 200;

            // Only allow for IP 127.0.0.1
            $this->checkIPs(['127.0.0.1']);

            // Assuming the data comes always correct
            $userId = OrbitInput::post('user_id', 0);
            $email = OrbitInput::post('user_email', 'email@example.com');
            $createdAt = OrbitInput::post('created_at', NULL);

            // Build the response object
            // This was just actually dummy things
            $data = new stdClass();
            $data->user_id = $userId;
            $data->user_email = $email;
            $data->external_user_id = 'ORB-' . $userId;
            $data->user_firstname = 'John-' . substr(md5(microtime()), 0, 6);
            $data->user_lastname = 'Doe-' . substr(md5(microtime()), 0, 6);
            $data->membership_number = 'M' . $userId;
            $data->membership_since = date('Y-m-d H:i:s', strtotime('last week'));

            $this->response->data = $data;

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
            $httpCode = 400;
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

            if (Config::get('app.debug')) {
                $this->response->data = $e->__toString();
            } else {
                $this->response->data = null;
            }
        }

        return $this->render($httpCode);
    }

    /**
     * Act as a handler for /orbit-notify/update-member.
     *
     * Expect to retrieve POST data:
     *   - user_id
     *   - user_email
     *   - created_at
     *
     * Prepare the response as:
     *   - user_id
     *   - external_user_id
     *   - user_email
     *   - user_firstname
     *   - user_lastname
     *   - membership_number
     *   - membership_since
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return ResponseProvider
     */
    public function NotifyCheckMemberHandler()
    {
        try {
            $httpCode = 200;

            // Only allow for IP 127.0.0.1
            $this->checkIPs(['127.0.0.1']);

            // Assuming the data comes always correct
            $userId = OrbitInput::post('user_id', 0);
            $email = OrbitInput::post('user_email', 'email@example.com');
            $createdAt = OrbitInput::post('created_at', NULL);

            // Build the response object
            // This was just actually dummy things
            $data = new stdClass();
            $data->user_id = $userId;
            $data->user_email = $email;
            $data->external_user_id = 'ORB-' . $userId;
            $data->user_firstname = 'John-' . substr(md5(microtime()), 0, 6);
            $data->user_lastname = 'Doe-' . substr(md5(microtime()), 0, 6);
            $data->membership_number = 'M' . $userId;
            $data->membership_since = date('Y-m-d H:i:s', strtotime('last week'));

            $this->response->data = $data;

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
            $httpCode = 400;
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

            if (Config::get('app.debug')) {
                $this->response->data = $e->__toString();
            } else {
                $this->response->data = null;
            }
        }

        return $this->render($httpCode);
    }

    /**
     * Request only allowed from some of IP address.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param array $ips List of ip address
     * @return void
     * @throws ACLForbidden Exception
     */
    protected function checkIPs(array $ips)
    {
        $clientIP = $_SERVER['REMOTE_ADDR'];
        if (! in_array($clientIP, $ips)) {
            $message = 'Your IP address are not allowed to access this resource.';
            ACL::throwAccessForbidden($message);
        }
    }
}