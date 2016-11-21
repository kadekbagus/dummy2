<?php namespace Orbit\Controller\API\v1\Pub;

/**
 * An API controller for share gotomalls landing page via email
 */
use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Text\Util\LineChecker;
use Config;
use stdClass;
use Validator;
use \Queue;

class LandingPageShareEmailAPIController extends PubControllerAPI
{
    /**
     * post - share gotomalls landing page via email
     *
     * @author kadek <kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string email
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postLandingPageShareEmail()
    {

        $httpCode = 200;
        try {

            $this->checkAuth();
            $user = $this->api->user;

            $email = OrbitInput::post('email');

            $this->registerCustomValidation();

            $validator = Validator::make(
                array(
                    'email'     => $email,
                ),
                array(
                    'email'     => 'required|email',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // send the email via queue
            Queue::push('Orbit\\Queue\\LandingPageShareMail', [
                'email'              => $email,
                'userId'             => $user->user_id,
            ]);

            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Success';
            $this->response->data = null;

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
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 500;

        } catch (Exception $e) {

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 500;
        }

        return $this->render($httpCode);
    }

}