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
use Log;

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

            // Only allow for particular IPs
            $allowedIPs = $this->getAllowedIPs('user-login');
            $this->checkIPs($allowedIPs);

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
     *   - external_user_id
     *   - user_email
     *   - user_firstname
     *   - user_lastname
     *   - ...
     *
     * Prepare the response as:
     *   - user_id
     *   - user_email
     *   - membership_number
     *   - membership_since
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return ResponseProvider
     */
    public function NotifyUpdateMemberHandler()
    {
        try {
            $httpCode = 200;

            // Only allow for particular IPs
            $allowedIPs = $this->getAllowedIPs('user-update');
            $this->checkIPs($allowedIPs);

            // Assuming the data comes always correct
            $userId = OrbitInput::post('user_id', 0);
            $email = OrbitInput::post('user_email', 'email@example.com');

            $customer = User::excludeDeleted()->find($userId);

            $membershipNumber = 'M' . $userId;
            $membershipSince = date('Y-m-d H:i:s', strtotime('last week'));
            if (! empty($customer)) {
                if (trim($customer->membership_number) !== '') {
                    // Use old one
                    $membershipNumber = $customer->membership_number;
                    $membershipSince = $customer->membership_since;
                }
                $email = $customer->user_email;
            }

            // Build the response object
            // This was just actually dummy things
            $data = new stdClass();
            $data->user_id = $userId;
            $data->user_email = $email;
            $data->membership_number = $membershipNumber;
            $data->membership_since = $membershipSince;
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
     * Act as a handler for /orbit-notify/lucky-draw-number.
     *
     * Expect to retrieve POST data:
     *   - user_id
     *   - external_user_id
     *   - lucky_draw_id
     *   - external_lucky_draw_id
     *   - membership_number
     *   - receipt_group
     *   - receipts (JSON string)
     *
     * Prepare the response as:
     *   - lucky_draw_id
     *   - receipt_group
     *   - lucky_draw_number_start
     *   - lucky_draw_number_end
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return ResponseProvider
     */
    public function NotifyLuckyDrawNumberHandler()
    {
        try {
            $httpCode = 200;

            // Only allow for particular IPs
            $allowedIPs = $this->getAllowedIPs('lucky-draw-number');
            $this->checkIPs($allowedIPs);

            // Assuming the data comes always correct
            $userId = OrbitInput::post('user_id', 0);
            $extUserId = OrbitInput::post('external_user_id', 0);
            $luckyDrawId = OrbitInput::post('lucky_draw_id', 0);
            $extLuckyDrawId = OrbitInput::post('lucky_draw_id', 0);
            $membershipNumber = OrbitInput::post('membership_number', 0);
            $receiptGroup = OrbitInput::post('receipt_group', 0);

            $customer = User::excludeDeleted()->find($userId);
            if (empty($customer)) {
                $errorMessage = sprintf('Customer ID %s or membership number %s not found.', $userId, $membershipNumber);
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $luckyDraw = LuckyDraw::excludeDeleted()->find($luckyDrawId);
            if (empty($luckyDraw)) {
                $errorMessage = sprintf('Lucky draw ID %s not found.', $luckyDrawId);
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $luckyDrawNumber = LuckyDrawNumber::excludeDeleted()
                                              ->where('hash', $receiptGroup)
                                              ->where('lucky_draw_id', $luckyDrawId)
                                              ->first();
            if (! empty($luckyDrawNumber)) {
                $errorMessage = sprintf('Receipt gorup %s already been used.', $receiptGroup);
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // Get first non-used number
            $number = LuckyDrawNumber::excludeDeleted()
                                     ->where('lucky_draw_id', $luckyDrawId)
                                     ->where(function($query) {
                                        $query->where('user_id', '=', 0);
                                        $query->orWhereNull('user_id');
                                     })
                                     ->orderBy('lucky_draw_number_code', 'asc')
                                     ->first();

            // Number of lucky draw generated are made by random
            // It just a dummy response, I don't f*cking care
            $rangeNumber = mt_rand(2, 10);
            $startNumber = $number->lucky_draw_number_code;
            $endNumber = $startNumber +  $rangeNumber;

            // Build the response object
            // This was just actually dummy things
            $data = new stdClass();
            $data->lucky_draw_id = $luckyDrawId;
            $data->receipt_group = $receiptGroup;
            $data->lucky_draw_number_start = $startNumber;
            $data->lucky_draw_number_end = $endNumber;
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
     * @param string|array $ips List of ip address
     * @return void
     * @throws ACLForbidden Exception
     */
    protected function checkIPs($ips)
    {
        if (is_string($ips) && $ips === '*') {
            return TRUE;
        }

        $clientIP = $_SERVER['REMOTE_ADDR'];
        if (! in_array($clientIP, $ips)) {
            $message = 'Your IP address are not allowed to access this resource.';
            ACL::throwAccessForbidden($message);
        }
    }

    /**
     * Get allowed IPs to access this resource.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string $configName
     * @return mixed
     */
    protected function getAllowedIPs($configName)
    {
        $retailerId = Config::get('orbit.shop.id');
        $config = sprintf('orbit-notifier.%s.%s.internal.allowed_ips', $configName, $retailerId);
        $allowedIPs = Config::get($config);

        return $allowedIPs;
    }
}