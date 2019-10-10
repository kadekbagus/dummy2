<?php namespace Orbit\Controller\API\v1\Pub\Pulsa;

use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Exception;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use DB;
use UserExtended;
use Validator;

/**
 * Handler for pulsa subscription request.
 *
 * @author Budi <budi@dominopos.com>
 */
class PulsaSubscriptionAPIController extends PubControllerAPI
{
    public function postSubscription()
    {
        $httpCode = 200;
        try {
            $this->checkAuth();
            $user = $this->api->user;

            // should always check the role
            $role = $user->role->role_name;
            if (strtolower($role) !== 'consumer') {
                $message = 'You have to login to continue';
                OrbitShopAPI::throwInvalidArgument($message);
            }

            $subAction = OrbitInput::post('sub_action');
            $subMethod = OrbitInput::post('sub_method');

            $this->registerCustomValidation();

            $validator = Validator::make(
                array(
                    'sub_action'        => $subAction,
                    'sub_method'        => $subMethod,
                    'user_id'           => $user->user_id,
                ),
                array(
                    'sub_action'        => 'required|in:subscribe,unsubscribe',
                    'sub_method'        => 'required_if:sub_action,subscribe|in:email,wa,telegram',
                    'user_id'           => 'orbit.user.can_subscribe',
                ),
                array(
                    'orbit.user.can_subscribe' => 'IS_SUBSCRIBED',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $this->beginTransaction();

            $userDetail = UserExtended::select('extended_user_id', 'user_id', 'pulsa_email_subscription')
                ->where('user_id', $user->user_id)
                ->firstOrFail();

            $responseMessage = 'Request OK';
            if ($subAction === 'subscribe') {
                if ($userDetail->pulsa_email_subscription === 'no') {
                    $userDetail->pulsa_email_subscription = 'yes';
                    $userDetail->save();
                    $responseMessage = 'SUBSCRIBE_SUCCESS';
                }
            }
            else if ($subAction === 'unsubscribe') {
                $userDetail->pulsa_email_subscription = 'no';
                $userDetail->save();
                $responseMessage = 'UNSUBSCRIBE_SUCCESS';
            }

            $this->commit();

            // Might fire event here...

            $this->response->data = $userDetail;
            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = $responseMessage;

        } catch (ACLForbiddenException $e) {
            $this->rollBack();
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

        } catch (InvalidArgsException $e) {
            $this->rollBack();
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;

        } catch (QueryException $e) {
            $this->rollBack();
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
            $this->rollBack();
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 500;
        }

        return $this->render($httpCode);
    }

    /**
     * Register custom validation.
     *
     * @return [type] [description]
     */
    private function registerCustomValidation()
    {
        // Validate that user can do subscription/unsubscription.
        Validator::extend('orbit.user.can_subscribe', function ($attribute, $userId, $parameters, $validator) {
            $validatorData = $validator->getData();
            $user = UserExtended::select('user_id', 'pulsa_email_subscription')->where('user_id', $userId)->first();

            if ($validatorData['sub_action'] === 'subscribe') {
                return $user->pulsa_email_subscription === 'no';
            }

            return true;
        });
    }
}
